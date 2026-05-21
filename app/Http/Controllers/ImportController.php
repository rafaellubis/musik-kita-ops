<?php

namespace App\Http\Controllers;

use App\Exports\XlsxBuilder;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function __construct(
        private readonly StudentImportService $service,
    ) {}

    /**
     * Tampilkan halaman import: form upload (step 1) atau preview (step 2).
     */
    public function index(): View
    {
        $preview = session('import_preview');
        return view('imports.index', compact('preview'));
    }

    /**
     * Download template .xlsx siap pakai.
     * Menggunakan XlsxBuilder (generator manual via ZipArchive) agar kompatibel penuh
     * dengan Excel — PhpSpreadsheet menambah elemen XML yang memicu dialog repair di Excel.
     */
    public function downloadTemplate(): Response
    {
        $builder = new XlsxBuilder();
        $content = $builder->build([
            ['name' => 'Data Murid',      'rows' => $this->dataMuridRows()],
            ['name' => 'Referensi Kode',  'rows' => $this->referensiKodeRows()],
        ]);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-murid.xlsx"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Dry run: parse + validasi file, simpan hasil ke session, redirect ke index.
     */
    public function validate(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,zip|max:5120',
        ], [
            'file.required' => 'File Excel wajib diupload.',
            'file.mimes'    => 'File harus berformat .xlsx.',
            'file.max'      => 'Ukuran file maksimal 5MB.',
        ]);

        try {
            $result = $this->service->validate($request->file('file'));
        } catch (\Throwable $e) {
            \Log::error('ImportController::validate gagal', [
                'message' => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return redirect()->route('import.index')
                ->with('error', 'File tidak dapat dibaca. Pastikan file tidak rusak dan menggunakan format .xlsx yang benar.');
        }

        // Hapus field 'data' dari errors sebelum disimpan ke session — hanya perlu row/name/message untuk preview
        $result['errors'] = array_map(fn ($e) => [
            'row'     => $e['row'],
            'name'    => $e['name'],
            'message' => $e['message'],
        ], $result['errors']);

        session(['import_preview' => $result]);

        $totalValid = count($result['valid']) + count($result['overwrite']);
        $totalError = count($result['errors']);

        return redirect()->route('import.index')
            ->with('info', "{$totalValid} baris valid, {$totalError} baris error — periksa preview sebelum konfirmasi.");
    }

    /**
     * Simpan baris valid + overwrite dari session ke database.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $preview = session('import_preview');

        if (!$preview || !isset($preview['valid'], $preview['overwrite'])) {
            return redirect()->route('import.index')
                ->with('error', 'Sesi validasi kadaluarsa. Upload ulang file.');
        }

        try {
            $result = $this->service->confirm($preview['valid'], $preview['overwrite']);
        } catch (\Throwable $e) {
            session()->forget('import_preview');
            return redirect()->route('import.index')
                ->with('error', 'Terjadi kesalahan saat menyimpan data. Import dibatalkan, tidak ada data yang tersimpan.');
        }

        session()->forget('import_preview');

        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entityLabel: 'Import Murid Excel',
            newValues: $result,
            notes: "Diimport: {$result['imported']} murid.",
        );

        return redirect()->route('students.index')
            ->with('success', "{$result['imported']} murid berhasil diimport.");
    }

    /**
     * Batalkan preview — clear session, kembali ke form upload.
     */
    public function cancel(): RedirectResponse
    {
        session()->forget('import_preview');
        return redirect()->route('import.index')
            ->with('info', 'Import dibatalkan. Silakan upload ulang file jika ingin mencoba lagi.');
    }

    // ============= PRIVATE: DATA UNTUK TEMPLATE =============

    /**
     * Baris untuk sheet "Data Murid" — header + 1 baris contoh.
     */
    private function dataMuridRows(): array
    {
        return [
            [
                'full_name', 'nickname', 'gender', 'birth_date', 'phone', 'email',
                'address', 'notes', 'parent_name', 'parent_phone', 'parent_email',
                'parent_relationship', 'status', 'package_code', 'teacher_code',
                'preferred_day', 'preferred_time', 'active_since', 'kode_ruangan', 'cuti_until',
            ],
            [
                'Budi Santoso', 'Budi', 'L', '2010-05-15', '08111111111',
                'budi@email.com', 'Jl. Contoh No.1', 'Catatan contoh',
                'Ayah Budi', '08111111112', 'ayahbudi@email.com', 'Ayah',
                'Aktif', 'KODE-PAKET-CONTOH', 'KODE-GURU-CONTOH',
                'Senin', '15:30', '2026-01-15', 'R2', '',
            ],
        ];
    }

    /**
     * Baris untuk sheet "Referensi Kode" — daftar kode paket, guru, dan enum valid.
     * Di-query live dari DB saat template di-download.
     */
    private function referensiKodeRows(): array
    {
        $rows = [];

        // Kode paket aktif
        $rows[] = ['=== KODE PAKET ==='];
        $rows[] = ['package_code', 'Tipe Kelas', 'Durasi (menit)'];
        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();
        if ($packages->isEmpty()) {
            $rows[] = ['(tidak ada paket aktif)'];
        } else {
            foreach ($packages as $pkg) {
                $rows[] = [$pkg->code, $pkg->class_type, $pkg->duration_min];
            }
        }

        $rows[] = [];

        // Kode guru aktif
        $rows[] = ['=== KODE GURU ==='];
        $rows[] = ['teacher_code', 'Nama Guru'];
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        if ($teachers->isEmpty()) {
            $rows[] = ['(tidak ada guru aktif)'];
        } else {
            foreach ($teachers as $teacher) {
                $rows[] = [$teacher->code, $teacher->name];
            }
        }

        $rows[] = [];

        // Kode ruangan aktif
        $rows[] = ['=== KODE RUANGAN ==='];
        $rows[] = ['kode_ruangan', 'Nama Studio', 'Instrumen yang Didukung'];
        $rooms = Room::where('is_active', true)->orderBy('code')->get();
        if ($rooms->isEmpty()) {
            $rows[] = ['(tidak ada ruangan aktif)'];
        } else {
            foreach ($rooms as $room) {
                $rows[] = [
                    $room->code,
                    $room->name,
                    implode(', ', $room->supported_instruments ?? []),
                ];
            }
        }

        $rows[] = [];

        // Nilai enum
        $rows[] = ['=== NILAI STATUS ==='];
        foreach (['Calon', 'Trial', 'Aktif', 'Cuti', 'Selesai', 'Mengundurkan Diri'] as $s) {
            $rows[] = [$s];
        }

        $rows[] = [];

        $rows[] = ['=== NILAI PREFERRED_DAY ==='];
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $d) {
            $rows[] = [$d];
        }

        $rows[] = [];

        $rows[] = ['=== NILAI PARENT_RELATIONSHIP ==='];
        foreach (['Ayah', 'Ibu', 'Wali'] as $r) {
            $rows[] = [$r];
        }

        $rows[] = [];
        $rows[] = ['=== CATATAN KOLOM CUTI_UNTIL ==='];
        $rows[] = ['Wajib diisi jika status = Cuti (format: YYYY-MM-DD, contoh: 2026-07-31)'];
        $rows[] = ['Kosongkan jika status bukan Cuti'];

        return $rows;
    }
}
