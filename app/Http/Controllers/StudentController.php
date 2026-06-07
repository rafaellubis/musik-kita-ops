<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\InvoiceService;
use App\Services\ManualSessionService;
use App\Services\StudentLifecycleService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentLifecycleService $lifecycle,
    ) {}

    public function index(Request $request)
    {
        // Build query bertahap berdasarkan filter
        $query = Student::query()
            ->with([
                'activeEnrollments.package.instrument',
                'activeEnrollments.teacher',
                'activeEnrollments.schedules' => fn ($q) => $q->where('is_active', true),
            ]);

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter instrumen (via enrollments aktif — kolom instrument ada di packages)
        if ($request->filled('instrument_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('status', 'ACTIVE')
                ->whereHas('package', fn ($pq) => $pq->where('instrument_id', $request->instrument_id)));
        }

        // Filter paket (via enrollments aktif — kolom package_id sudah tidak ada di students)
        if ($request->filled('package_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('status', 'ACTIVE')->where('package_id', $request->package_id));
        }

        // Search by name atau code
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('student_code', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        // Default: urut by code descending
        $students = $query->orderBy('student_code', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Stats per status (untuk header)
        $stats = Student::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Data dropdown filter
        $instruments = Instrument::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $packages = Package::where('is_active', true)
            ->with('instrument')
            ->orderBy('sort_order')
            ->get();

        return view('students.index', compact(
            'students', 'stats', 'instruments', 'packages'
        ));
    }

    public function show(string $id)
    {
        $student = Student::with([
            'primaryEnrollment.package.instrument',
            'primaryEnrollment.teacher',
            'histories.changedBy',
            // M03: enrollment ACTIVE + schedules + room
            'enrollments' => fn ($q) => $q->latest('effective_date'),
            'enrollments.package',
            'enrollments.package.instrument',
            'enrollments.teacher',
            'enrollments.schedules.room',
        ])->findOrFail($id);

        // Data untuk dropdown di panel aksi lifecycle + form schedule
        $packages = Package::where('is_active', true)
            ->with('instrument')
            ->orderBy('sort_order')
            ->get();
        $teachers = Teacher::where('is_active', true)
            ->with('instruments')
            ->orderBy('name')
            ->get();
        $rooms = Room::where('is_active', true)->orderBy('code')->get();

        // Data untuk Alpine.js auto-suggest ruangan di form jadwal
        // $roomsForFilter: berisi supported_instruments untuk filter client-side
        $roomsForFilter = Room::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'capacity', 'supported_instruments']);

        // $bookedSchedules: jadwal aktif yang sudah ada room_id — untuk cek konflik client-side.
        // Field 'id' wajib agar Alpine.js bisa exclude jadwal yang sedang diedit dari conflict check.
        $bookedSchedules = Schedule::active()
            ->whereNotNull('room_id')
            ->get(['id', 'room_id', 'day_of_week', 'start_time', 'end_time']);

        // M03: Sesi mendatang (5 terdekat dari hari ini)
        $upcomingSessions = $student->classSessions()
            ->with('room', 'substituteTeacher', 'originSession')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        // M05: 5 invoice terbaru + total saldo outstanding (UNPAID + PARTIAL)
        $recentInvoices = $student->invoices()
            ->with('items')
            ->limit(5)
            ->get();

        $outstandingBalance = (int) $student->invoices()
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->get()
            ->sum(fn ($inv) => $inv->balance);

        $unpaidCount = $student->invoices()
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->count();

        // M-Kelas: Kelas berjalan — enrollment ACTIVE diurutkan: utama dulu
        $activeEnrollments = $student->enrollments()
            ->active()
            ->with([
                'package.instrument',
                'teacher',
                'schedules' => fn ($q) => $q->where('is_active', true)->with('room'),
            ])
            ->orderByDesc('is_primary')
            ->get();

        // M05: Data cicilan Kids Bundle (BR-10.10) — null jika bukan KIDS_CLASS_BUNDLE INSTALLMENT.
        // Ditampilkan sebagai kartu progress di tab tagihan.
        $kidsInstallments = null;
        $primaryEnrollment = $student->primaryEnrollment;
        if ($primaryEnrollment && $primaryEnrollment->package?->class_type === 'KIDS_CLASS_BUNDLE') {
            $latestGroup = \App\Models\Invoice::where('student_id', $student->id)
                ->where('payment_mode', 'INSTALLMENT')
                ->whereNotNull('installment_group_id')
                ->latest('id')
                ->value('installment_group_id');

            if ($latestGroup) {
                $kidsInstallments = \App\Models\Invoice::where('installment_group_id', $latestGroup)
                    ->orderBy('installment_number')
                    ->get(['id', 'installment_number', 'total_amount', 'paid_amount', 'status', 'due_date']);
            }
        }

        // M-Kelas: Riwayat kelas — enrollment yang sudah tidak aktif
        $historyEnrollments = $student->enrollments()
            ->whereIn('status', ['INACTIVE', 'COMPLETED'])
            ->with(['package.instrument', 'teacher'])
            ->orderByDesc('end_date')
            ->get();

        // Data untuk modal "Tambah Kelas" — dipindah dari query inline Blade
        // agar tidak ada Eloquent query di dalam template (separation of concerns)
        $allPackages = Package::where('is_active', true)
            ->with('instrument')
            ->orderBy('sort_order')
            ->get();

        $allTeachers = Teacher::where('is_active', true)
            ->orderBy('name')
            ->get();

        $allRooms = Room::where('is_active', true)
            ->orderBy('code')
            ->get();

        // Cek apakah tombol Generate Final Project perlu ditampilkan
        // Hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP
        $tampilKidsFpButton = $student->primaryEnrollment?->package?->class_type === 'KIDS_CLASS'
            && ! InvoiceItem::whereHas('invoice', fn ($q) =>
                $q->where('student_id', $student->id)
            )->where('item_code', 'KIDS_FP')->exists();

        // Ambil fee KIDS_FP dari konstanta InvoiceService (BR: Rp 140.000)
        $kidsFpFee = InvoiceService::FEE_KIDS_FP;

        // M03: Ringkasan slot sesi per enrollment (panel sesi manual)
        $manualSessionService = app(ManualSessionService::class);
        $enrollmentSlotSummaries = [];
        foreach ($activeEnrollments as $enrollment) {
            $attrYear  = now()->year;
            $attrMonth = now()->month;
            $enrollmentSlotSummaries[$enrollment->id] = [
                'year'          => $attrYear,
                'month'         => $attrMonth,
                'month_label'   => \Carbon\Carbon::create($attrYear, $attrMonth, 1)
                    ->locale('id')->translatedFormat('F Y'),
                'slots'         => $manualSessionService->slotSummary($enrollment, $attrYear, $attrMonth),
                'next_sequence' => $manualSessionService->suggestNextSequence($enrollment, $attrYear, $attrMonth),
            ];
        }

        return view('students.show', compact(
            'student', 'packages', 'teachers', 'rooms',
            'roomsForFilter', 'bookedSchedules',
            'upcomingSessions',
            'recentInvoices', 'outstandingBalance', 'unpaidCount',
            'activeEnrollments', 'historyEnrollments',
            'allPackages', 'allTeachers', 'allRooms',
            'kidsInstallments',
            'tampilKidsFpButton', 'kidsFpFee',
            'enrollmentSlotSummaries',
        ));
    }

    public function create()
    {
        return view('students.create');
    }

    /**
     * Bikin murid baru. Status awal selalu 'Calon' di tabel students,
     * lalu kalau form pilih Trial / Aktif, langsung dipanggil service
     * yang akan mengubah status sambil menulis history.
     *
     * Pendekatan ini memastikan SETIAP murid punya minimal 1 baris
     * di student_status_histories — tidak ada "ghost" murid yang
     * langsung Aktif tanpa jejak.
     */
    public function store(StoreStudentRequest $request)
    {
        $validated = $request->validated();

        // Auto-generate student_code (format M-YYYY-NNNN)
        $code = Student::generateCode();

        // Buat dulu sebagai Calon, biar lifecycle service yang transisi.
        $student = Student::create([
            'student_code'        => $code,
            'full_name'           => $validated['full_name'],
            'nickname'            => $validated['nickname'] ?? null,
            'gender'              => $validated['gender'],
            'birth_date'          => $validated['birth_date'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'address'             => $validated['address'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'parent_name'         => $validated['parent_name'] ?? null,
            'parent_phone'        => $validated['parent_phone'] ?? null,
            'parent_email'        => $validated['parent_email'] ?? null,
            'parent_relationship' => $validated['parent_relationship'] ?? null,
            'status' => 'Calon',
        ]);

        return redirect()->route('students.show', $student->id)
            ->withFragment('tab-kelas')
            ->with('success', "Murid {$student->full_name} ({$student->student_code}) berhasil didaftarkan. Silakan tambahkan kelas via Tab Kelas.");
    }

    public function edit(string $id)
    {
        $student = Student::findOrFail($id);

        $packages = Package::where('is_active', true)
            ->with('instrument')
            ->orderBy('sort_order')
            ->get();

        $teachers = Teacher::where('is_active', true)
            ->orderBy('name')
            ->get();

        $rooms = Room::where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('students.edit', compact('student', 'packages', 'teachers', 'rooms'));
    }

    public function update(UpdateStudentRequest $request, string $id)
    {
        $student = Student::findOrFail($id);
        $validated = $request->validated();

        // Status TIDAK di-update lewat form edit — hanya lewat lifecycle action.
        $student->update($validated);

        return redirect()->route('students.show', $student->id)
            ->with('success', "Data murid {$student->full_name} berhasil diperbarui.");
    }

    public function destroy(string $id)
    {
        // Redirect ke detail — hard delete dinonaktifkan.
        // Status terminal (Mengundurkan Diri) lewat lifecycle action di show page.
        return redirect()->route('students.show', $id)
            ->with('error', 'Untuk mengakhiri status murid, gunakan tombol "Mundur" di halaman detail.');
    }

    /**
     * API internal: daftar guru aktif yang mengajar instrumen tertentu.
     * Dipanggil AJAX dari form tambah/edit murid.
     */
    public function teachersByInstrument(string $instrumentId)
    {
        $teachers = Teacher::where('is_active', true)
            ->whereHas('instruments', function ($q) use ($instrumentId) {
                $q->where('instruments.id', $instrumentId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($teachers);
    }

    // =================================================================
    // LIFECYCLE ACTIONS (M02)
    // Setiap method delegate ke StudentLifecycleService.
    // Validasi input pakai inline rules — sederhana, mudah dilihat
    // bareng business intent. Refactor ke FormRequest kalau membesar.
    // =================================================================

    public function startTrial(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'trial_date'          => 'required|date|after:now',
            'package_id'          => 'required|exists:packages,id',
            'assigned_teacher_id' => 'required|exists:teachers,id',
            'assigned_room_id'    => 'nullable|exists:rooms,id',
            'notes'               => 'nullable|string|max:500',
        ], [
            'trial_date.required'          => 'Tanggal trial wajib diisi.',
            'trial_date.after'             => 'Jadwal trial harus setelah sekarang.',
            'package_id.required'          => 'Paket yang diminati wajib dipilih.',
            'assigned_teacher_id.required' => 'Guru trial wajib dipilih.',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->mulaiTrial($student, $data),
            $student,
            'Jadwal trial berhasil disimpan. Status sekarang: Trial.'
        );
    }

    public function convertActive(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'package_id'          => 'required|exists:packages,id',
            'assigned_teacher_id' => 'required|exists:teachers,id',
            'assigned_room_id'    => 'nullable|exists:rooms,id',
            'notes'               => 'nullable|string|max:500',
            'payment_mode'        => 'nullable|in:FULL,INSTALLMENT',
        ], [
            'package_id.required'          => 'Paket wajib dipilih sebelum konversi Aktif.',
            'assigned_teacher_id.required' => 'Guru wajib dipilih sebelum konversi Aktif.',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->konversiAktif($student, $data),
            $student,
            'Murid dikonversi jadi Aktif. Tagihan REG + SPP sudah diterbitkan.'
        );
    }

    public function skipTrial(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'reason_code'         => 'required|in:walk_in,migrasi,reaktivasi,lulus_kids',
            'reason'              => 'required|string|max:500',
            'package_id'          => 'required|exists:packages,id',
            'assigned_teacher_id' => 'required|exists:teachers,id',
            'assigned_room_id'    => 'nullable|exists:rooms,id',
            'payment_mode'        => 'nullable|in:FULL,INSTALLMENT',
        ], [
            'reason_code.required' => 'Pilih alasan skip trial (walk-in / migrasi / reaktivasi / lulus kids).',
            'reason.required'      => 'Penjelasan alasan wajib diisi untuk audit.',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->skipTrial($student, $data),
            $student,
            'Murid langsung jadi Aktif (skip trial). Alasan tercatat di riwayat status.'
        );
    }

    public function startCuti(Request $request, string $id)
    {
        $student     = Student::findOrFail($id);
        $isExtension = $student->status === 'Cuti';

        // Perpanjang: hanya butuh cuti_until baru (cuti_from tetap dari pengajuan awal).
        // Baru: butuh cuti_from dan cuti_until.
        $rules    = ['reason' => 'required|string|max:500'];
        $messages = ['reason.required' => 'Alasan cuti wajib diisi.'];

        if ($isExtension) {
            $rules['cuti_until']             = 'required|date|after:today';
            $messages['cuti_until.required'] = 'Tanggal akhir cuti baru wajib diisi.';
            $messages['cuti_until.after']    = 'Tanggal akhir cuti harus setelah hari ini.';
        } else {
            $rules['cuti_from']              = 'required|date|after_or_equal:today';
            $rules['cuti_until']             = 'required|date|after:cuti_from';
            $messages['cuti_from.required']  = 'Tanggal mulai cuti wajib diisi.';
            $messages['cuti_until.required'] = 'Tanggal akhir cuti wajib diisi.';
            $messages['cuti_until.after']    = 'Tanggal akhir cuti harus setelah tanggal mulai.';
        }

        $data = $request->validate($rules, $messages);

        return $this->runLifecycle(
            fn () => $this->lifecycle->ajukanCuti($student, $data),
            $student,
            'Pengajuan cuti tercatat. Tagihan biaya cuti Rp 100.000 telah diterbitkan.'
        );
    }

    public function withdraw(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan mundur wajib diisi.',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->mundurkan($student, $data),
            $student,
            'Murid ditandai Mengundurkan Diri.'
        );
    }

    public function complete(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->selesai($student, $data),
            $student,
            'Murid Kids Class ditandai Selesai (lulus 6 bulan).'
        );
    }

    public function returnFromCuti(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        return $this->runLifecycle(
            fn () => $this->lifecycle->aktifkanDariCuti($student, $data),
            $student,
            'Cuti diakhiri. Murid kembali Aktif.'
        );
    }

    public function reactivate(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'package_id'          => 'required|exists:packages,id',
            'assigned_teacher_id' => 'required|exists:teachers,id',
            'assigned_room_id'    => 'nullable|exists:rooms,id',
            'notes'               => 'nullable|string|max:500',
            'payment_mode'        => 'nullable|in:FULL,INSTALLMENT',
        ], [
            'package_id.required'          => 'Paket wajib dipilih untuk re-aktivasi.',
            'assigned_teacher_id.required' => 'Guru wajib dipilih untuk re-aktivasi.',
        ]);

        $result = $this->runLifecycle(
            fn () => $this->lifecycle->aktifkanKembali($student, $data),
            $student,
            'Murid diaktifkan kembali.'
        );

        // Flash warning dari service jika ada hutang lama (diset oleh aktifkanKembali).
        if ($this->lifecycle->lastWarning) {
            session()->flash('warning', $this->lifecycle->lastWarning);
        }

        return $result;
    }

    /**
     * Wrapper umum untuk semua lifecycle action: jalankan callback service,
     * tangkap InvalidArgumentException (transisi tidak valid), redirect ke
     * detail dengan flash message yang sesuai.
     */
    private function runLifecycle(callable $action, Student $student, string $successMessage)
    {
        $statusBefore = $student->status;
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('students.show', $student->id)
                ->with('error', $e->getMessage());
        }

        // Catat perubahan status ke audit log
        $student->refresh();
        AuditLog::record(
            action: AuditLog::ACTION_LIFECYCLE,
            entity: $student,
            entityLabel: $student->full_name . ' (' . $student->student_code . ')',
            oldValues: ['status' => $statusBefore],
            newValues: ['status' => $student->status],
            notes: $successMessage,
        );

        return redirect()->route('students.show', $student->id)
            ->with('success', $successMessage);
    }
}
