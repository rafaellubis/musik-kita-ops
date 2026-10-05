<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Package;
use App\Models\Teacher;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        // Pre-fetch IDs dari M01 untuk relasi
        // Pakai whereHas + class_type + grade (lebih defensif dari code string)
        $pianoRegL1 = Package::where('class_type', 'REGULER')
            ->whereHas('instrument', fn($q) => $q->where('code', 'PIANO'))
            ->where('grade', 'L1')
            ->first();

        $gitarHobby30 = Package::where('class_type', 'HOBBY')
            ->whereHas('instrument', fn($q) => $q->where('code', 'GITAR'))
            ->where('duration_min', 30)
            ->first();

        $kidsBundle = Package::where('class_type', 'KIDS_CLASS_BUNDLE')->first();

        $vocalRegBasic = Package::where('class_type', 'REGULER')
            ->whereHas('instrument', fn($q) => $q->where('code', 'VOCAL'))
            ->where('grade', 'BASIC')
            ->first();

        $teacherAdi = Teacher::where('code', 'T02')->first();   // Adi - Piano
        $teacherNael = Teacher::where('code', 'T06')->first();  // Nael - Piano+Gitar
        $teacherIca = Teacher::where('code', 'T16')->first();   // Ica - Kids
        $teacherDevi = Teacher::where('code', 'T10')->first();  // Devi - Vocal

        // ============= 5 MURID DUMMY =============
        // Data kelas disimpan di enrollments, bukan lagi di tabel students.
        $trialDate = now()->addDays(2)->setTime(17, 0);
        $students = [
            [
                'student' => [
                    'student_code' => 'M-2026-0001', 'full_name' => 'Andi Wijaya', 'nickname' => 'Andi',
                    'gender' => 'L', 'birth_date' => '2010-07-14', 'phone' => '081234567890',
                    'email' => 'andi.wijaya@example.com', 'address' => 'Jl. Merdeka No. 12, Jakarta Selatan',
                    'parent_name' => 'Bambang Wijaya', 'parent_phone' => '081111222333',
                    'parent_email' => 'bambang@example.com', 'parent_relationship' => 'Ayah',
                    'status' => 'Aktif', 'active_since' => '2025-09-01', 'last_session_at' => now()->subDays(3),
                    'notes' => 'Lulus dari level Basic, naik ke L1 September 2025',
                ],
                'enrollment' => [$pianoRegL1, $teacherAdi, '2025-09-01', Enrollment::STATUS_ACTIVE],
            ],
            [
                'student' => [
                    'student_code' => 'M-2026-0002', 'full_name' => 'Sari Kusuma', 'nickname' => 'Sari',
                    'gender' => 'P', 'birth_date' => '2008-03-22', 'phone' => '082234567890',
                    'address' => 'Jl. Sudirman No. 45, Jakarta Pusat', 'status' => 'Trial',
                    'trial_date' => $trialDate, 'notes' => 'Daftar trial Senin lalu, jadwal trial Rabu',
                ],
                'enrollment' => [$gitarHobby30, $teacherNael, $trialDate->toDateString(), Enrollment::STATUS_TRIAL],
            ],
            [
                'student' => [
                    'student_code' => 'M-2026-0003', 'full_name' => 'Bobby Santoso', 'nickname' => 'Bob',
                    'gender' => 'L', 'birth_date' => '2021-05-10', 'parent_name' => 'Linda Santoso',
                    'parent_phone' => '083345678901', 'parent_email' => 'linda@example.com',
                    'parent_relationship' => 'Ibu', 'status' => 'Aktif', 'active_since' => '2026-01-15',
                    'last_session_at' => now()->subDays(7), 'notes' => 'Kids Class, paket 6 bulan',
                ],
                'enrollment' => [$kidsBundle, $teacherIca, '2026-01-15', Enrollment::STATUS_ACTIVE],
            ],
            [
                'student' => [
                    'student_code' => 'M-2026-0004', 'full_name' => 'Dewi Pratiwi', 'nickname' => 'Dewi',
                    'gender' => 'P', 'birth_date' => '1995-11-30', 'phone' => '084456789012',
                    'email' => 'dewi.p@example.com', 'address' => 'Jl. Kemang No. 8, Jakarta Selatan',
                    'status' => 'Calon', 'notes' => 'Inquiry via WhatsApp, belum daftar trial',
                ],
                'enrollment' => null,
            ],
            [
                'student' => [
                    'student_code' => 'M-2026-0005', 'full_name' => 'Eka Saputra', 'nickname' => 'Eka',
                    'gender' => 'L', 'birth_date' => '2005-01-15', 'phone' => '085567890123',
                    'email' => 'eka.s@example.com', 'address' => 'Jl. Kebayoran No. 33, Jakarta Selatan',
                    'status' => 'Cuti', 'cuti_from' => now()->startOfMonth()->toDateString(),
                    'cuti_until' => now()->endOfMonth()->toDateString(), 'active_since' => '2024-08-01',
                    'last_session_at' => now()->subDays(20), 'notes' => 'Cuti 2 minggu karena UAS sekolah',
                ],
                'enrollment' => [$vocalRegBasic, $teacherDevi, '2024-08-01', Enrollment::STATUS_ON_LEAVE],
            ],
        ];

        foreach ($students as $data) {
            $studentData = $data['student'];
            $student = Student::updateOrCreate(
                ['student_code' => $studentData['student_code']],
                $studentData,
            );

            if ($data['enrollment'] === null) {
                $student->update(['primary_enrollment_id' => null]);
                continue;
            }

            [$package, $teacher, $effectiveDate, $status] = $data['enrollment'];
            $enrollment = Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'package_id' => $package->id],
                [
                    'teacher_id' => $teacher->id,
                    'effective_date' => $effectiveDate,
                    'end_date' => null,
                    'status' => $status,
                    'is_primary' => true,
                ],
            );

            $student->enrollments()->where('id', '!=', $enrollment->id)->update(['is_primary' => false]);
            $student->update(['primary_enrollment_id' => $enrollment->id]);
        }
    }
}
