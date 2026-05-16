<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Service untuk import data murid dari file Excel.
 *
 * Alur kerja dua tahap:
 *   1. validate()  — dry run, baca Excel, validasi semua baris, TIDAK simpan ke DB.
 *   2. confirm()   — simpan baris yang valid dan overwrite ke DB dalam satu transaksi.
 *
 * Pendekatan dua tahap dipilih agar admin bisa review dulu hasilnya sebelum commit.
 */
class StudentImportService
{
    /**
     * Status murid yang valid (Title Case — sesuai skema enum students.status).
     */
    private const VALID_STATUSES = [
        'Calon', 'Trial', 'Aktif', 'Cuti', 'Selesai', 'Mengundurkan Diri',
    ];

    /**
     * Hari yang valid untuk preferred_day.
     */
    private const VALID_DAYS = [
        'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu',
    ];

    /**
     * Hubungan orang tua/wali yang valid.
     */
    private const VALID_RELATIONSHIPS = ['Ayah', 'Ibu', 'Wali'];

    // ============= PUBLIC API =============

    /**
     * Parse file Excel, validasi tiap baris, return hasil dry run.
     * Tidak menyimpan apapun ke database.
     *
     * Kolom Excel yang didukung (baris pertama = header):
     *   full_name, nickname, gender, birth_date, phone, email, address, notes,
     *   parent_name, parent_phone, parent_email, parent_relationship,
     *   status, package_code, teacher_code, preferred_day, preferred_time,
     *   trial_date, active_since
     *
     * @return array{valid: array, overwrite: array, errors: array}
     */
    public function validate(UploadedFile $file): array
    {
        $rows = Excel::toArray([], $file)[0] ?? [];

        // Baris pertama adalah header — pisahkan dari data
        $headers = array_map('trim', array_shift($rows) ?? []);

        $valid     = [];
        $overwrite = [];
        $errors    = [];

        // Cache kode package & guru aktif untuk efisiensi (hindari N+1 query)
        $packageCodes = Package::where('is_active', true)->pluck('id', 'code');
        $teacherCodes = Teacher::where('is_active', true)->pluck('id', 'code');

        foreach ($rows as $index => $row) {
            // +2: row 1 = header, index mulai dari 0
            $rowNum = $index + 2;

            // Skip baris yang seluruh kolomnya kosong
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            // Map array numerik -> associative berdasarkan header
            $data = array_combine($headers, array_pad($row, count($headers), null));

            // Trim whitespace semua nilai string
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

            $result = $this->validateRow(
                $rowNum,
                $data,
                $packageCodes->toArray(),
                $teacherCodes->toArray()
            );

            if (is_string($result)) {
                // Validasi gagal — catat error beserta nomor baris
                $errors[] = [
                    'row'     => $rowNum,
                    'name'    => $data['full_name'] ?? '(kosong)',
                    'message' => $result,
                    'data'    => $data,
                ];
            } else {
                // Validasi sukses — cek apakah sudah ada murid dengan nama + nomor HP yang sama
                $existing = $this->findExisting($result['full_name'], $result['phone'] ?? null);

                if ($existing) {
                    // Akan di-overwrite (update) saat confirm()
                    $result['_existing_id'] = $existing->id;
                    $overwrite[] = ['row' => $rowNum, 'data' => $result];
                } else {
                    // Murid baru
                    $valid[] = ['row' => $rowNum, 'data' => $result];
                }
            }
        }

        return compact('valid', 'overwrite', 'errors');
    }

    /**
     * Simpan baris valid + overwrite ke database.
     * Semua baris disimpan dalam satu transaksi — jika ada yang gagal, seluruh import dibatalkan.
     * Baris yang diimport sudah divalidasi saat dry run, jadi kegagalan di sini bersifat unexpected.
     *
     * @param  array $valid     Baris baru dari hasil validate()
     * @param  array $overwrite Baris yang akan update murid existing dari hasil validate()
     * @return array{imported: int, skipped: int}
     */
    public function confirm(array $valid, array $overwrite): array
    {
        $imported = 0;

        DB::transaction(function () use ($valid, $overwrite, &$imported) {
            foreach (array_merge($valid, $overwrite) as $item) {
                $this->upsertStudent($item['data']);
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => 0];
    }

    /**
     * Validasi satu baris data Excel.
     *
     * Return array data yang sudah dibersihkan jika valid,
     * atau string pesan error jika ada masalah.
     *
     * Method ini public agar bisa ditest secara unit tanpa file Excel.
     *
     * @param  int   $rowNum       Nomor baris (untuk pesan error yang informatif)
     * @param  array $row          Data baris: ['full_name' => ..., 'gender' => ..., ...]
     * @param  array $packageCodes Cache kode paket aktif: ['PKG-001' => 1, ...]
     * @param  array $teacherCodes Cache kode guru aktif: ['TCH-001' => 1, ...]
     * @return array|string        Data bersih (array) atau pesan error (string)
     */
    public function validateRow(
        int $rowNum,
        array $row,
        array $packageCodes = [],
        array $teacherCodes = []
    ): array|string {
        $errors = [];

        // ---------- KOLOM WAJIB ----------

        // Nama lengkap wajib ada dan maks 100 karakter
        if (empty($row['full_name'])) {
            $errors[] = 'full_name wajib diisi';
        } elseif (strlen($row['full_name']) > 100) {
            $errors[] = 'full_name maks 100 karakter';
        }

        // Gender harus L atau P
        if (!in_array($row['gender'] ?? '', ['L', 'P'], true)) {
            $errors[] = 'gender harus L atau P';
        }

        // Status harus salah satu dari enum yang valid
        if (!in_array($row['status'] ?? '', self::VALID_STATUSES, true)) {
            $errors[] = 'status tidak valid (nilai yang diizinkan: ' . implode(', ', self::VALID_STATUSES) . ')';
        }

        // ---------- KOLOM OPSIONAL DENGAN FORMAT ----------

        // Format email murid
        if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email format tidak valid';
        }

        // Format email orang tua
        if (!empty($row['parent_email']) && !filter_var($row['parent_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'parent_email format tidak valid';
        }

        // Hubungan orang tua harus Ayah / Ibu / Wali
        if (!empty($row['parent_relationship'])
            && !in_array($row['parent_relationship'], self::VALID_RELATIONSHIPS, true)) {
            $errors[] = 'parent_relationship harus Ayah, Ibu, atau Wali';
        }

        // Hari preferensi harus nama hari Indonesia yang valid
        if (!empty($row['preferred_day'])
            && !in_array($row['preferred_day'], self::VALID_DAYS, true)) {
            $errors[] = 'preferred_day tidak valid (contoh: Senin, Selasa, ...)';
        }

        // Jam preferensi harus format HH:MM dengan nilai jam dan menit yang valid
        if (!empty($row['preferred_time'])
            && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['preferred_time'])) {
            $errors[] = 'preferred_time harus format HH:MM (contoh: 15:30)';
        }

        // Tanggal harus format YYYY-MM-DD
        // trial_date sengaja dikecualikan — tidak relevan untuk import massal
        foreach (['birth_date', 'active_since'] as $dateField) {
            if (!empty($row[$dateField])) {
                $parsed = \DateTime::createFromFormat('Y-m-d', $row[$dateField]);
                if (!$parsed || $parsed->format('Y-m-d') !== $row[$dateField]) {
                    $errors[] = "{$dateField} harus format YYYY-MM-DD";
                }
            }
        }

        // ---------- FOREIGN KEY ----------

        // Kode paket harus ditemukan di tabel packages dan statusnya aktif
        if (!empty($row['package_code'])) {
            if (!isset($packageCodes[$row['package_code']])) {
                $errors[] = "package_code '{$row['package_code']}' tidak ditemukan atau tidak aktif";
            }
        }

        // Kode guru harus ditemukan di tabel teachers dan statusnya aktif
        if (!empty($row['teacher_code'])) {
            if (!isset($teacherCodes[$row['teacher_code']])) {
                $errors[] = "teacher_code '{$row['teacher_code']}' tidak ditemukan atau tidak aktif";
            }
        }

        // ---------- KEMBALIKAN HASIL ----------

        if (!empty($errors)) {
            // Format: "Baris 5: full_name wajib diisi; gender harus L atau P"
            return 'Baris ' . $rowNum . ': ' . implode('; ', $errors);
        }

        // Resolve kode paket/guru ke ID agar siap disimpan ke DB
        $data                        = $row;
        $data['package_id']          = !empty($row['package_code'])  ? $packageCodes[$row['package_code']]  : null;
        $data['assigned_teacher_id'] = !empty($row['teacher_code'])  ? $teacherCodes[$row['teacher_code']]  : null;
        unset($data['package_code'], $data['teacher_code']);

        // Normalisasi string kosong ke null agar konsisten dengan schema DB
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * Cari murid existing berdasarkan full_name + phone.
     * Kedua field harus sama persis untuk dianggap duplikat.
     * Jika phone kosong, tidak bisa deteksi duplikat — return null.
     */
    public function findExisting(string $fullName, ?string $phone): ?Student
    {
        // Tanpa nomor HP, matching full_name saja terlalu rawan false positive
        if (!$phone) {
            return null;
        }

        return Student::where('full_name', $fullName)
            ->where('phone', $phone)
            ->first();
    }

    // ============= PRIVATE HELPERS =============

    /**
     * Buat murid baru atau update murid existing dari satu baris data.
     * Key '_existing_id' digunakan untuk menentukan mode (insert vs update).
     */
    private function upsertStudent(array $data): Student
    {
        // Ambil dan hapus key internal sebelum simpan ke DB
        $existingId = $data['_existing_id'] ?? null;
        unset($data['_existing_id']);

        if ($existingId) {
            // Mode update: murid sudah ada di DB
            $student = Student::findOrFail($existingId);
            $student->update($data);
        } else {
            // Mode insert: murid baru — generate kode unik
            $data['student_code'] = Student::generateCode();
            $student = Student::create($data);
        }

        return $student;
    }
}
