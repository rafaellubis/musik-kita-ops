<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
        $students = [
            [
                'student_code' => 'M-2026-0001',
                'full_name' => 'Andi Wijaya',
                'nickname' => 'Andi',
                'gender' => 'L',
                'birth_date' => '2010-07-14',
                'phone' => '081234567890',
                'email' => 'andi.wijaya@example.com',
                'address' => 'Jl. Merdeka No. 12, Jakarta Selatan',
                'parent_name' => 'Bambang Wijaya',
                'parent_phone' => '081111222333',
                'parent_email' => 'bambang@example.com',
                'parent_relationship' => 'Ayah',
                'status' => 'Aktif',
                'package_id' => $pianoRegL1?->id,
                'assigned_teacher_id' => $teacherAdi?->id,
                'preferred_day' => 'Senin',
                'preferred_time' => '15:00:00',
                'active_since' => '2025-09-01',
                'last_session_at' => now()->subDays(3),
                'notes' => 'Lulus dari level Basic, naik ke L1 September 2025',
            ],
            [
                'student_code' => 'M-2026-0002',
                'full_name' => 'Sari Kusuma',
                'nickname' => 'Sari',
                'gender' => 'P',
                'birth_date' => '2008-03-22',
                'phone' => '082234567890',
                'address' => 'Jl. Sudirman No. 45, Jakarta Pusat',
                'status' => 'Trial',
                'package_id' => $gitarHobby30?->id,
                'assigned_teacher_id' => $teacherNael?->id,
                'preferred_day' => 'Rabu',
                'preferred_time' => '17:00:00',
                'trial_date' => now()->addDays(2)->setTime(17, 0),
                'notes' => 'Daftar trial Senin lalu, jadwal trial Rabu',
            ],
            [
                'student_code' => 'M-2026-0003',
                'full_name' => 'Bobby Santoso',
                'nickname' => 'Bob',
                'gender' => 'L',
                'birth_date' => '2021-05-10',  // 4-5 tahun, kids class
                'parent_name' => 'Linda Santoso',
                'parent_phone' => '083345678901',
                'parent_email' => 'linda@example.com',
                'parent_relationship' => 'Ibu',
                'status' => 'Aktif',
                'package_id' => $kidsBundle?->id,
                'assigned_teacher_id' => $teacherIca?->id,
                'preferred_day' => 'Sabtu',
                'preferred_time' => '10:00:00',
                'active_since' => '2026-01-15',
                'last_session_at' => now()->subDays(7),
                'notes' => 'Kids Class, paket 6 bulan',
            ],
            [
                'student_code' => 'M-2026-0004',
                'full_name' => 'Dewi Pratiwi',
                'nickname' => 'Dewi',
                'gender' => 'P',
                'birth_date' => '1995-11-30',
                'phone' => '084456789012',
                'email' => 'dewi.p@example.com',
                'address' => 'Jl. Kemang No. 8, Jakarta Selatan',
                'status' => 'Calon',
                'preferred_day' => 'Sabtu',
                'preferred_time' => '11:00:00',
                'notes' => 'Inquiry via WhatsApp, belum daftar trial',
            ],
            [
                'student_code' => 'M-2026-0005',
                'full_name' => 'Eka Saputra',
                'nickname' => 'Eka',
                'gender' => 'L',
                'birth_date' => '2005-01-15',
                'phone' => '085567890123',
                'email' => 'eka.s@example.com',
                'address' => 'Jl. Kebayoran No. 33, Jakarta Selatan',
                'status' => 'Cuti',
                'package_id' => $vocalRegBasic?->id,
                'assigned_teacher_id' => $teacherDevi?->id,
                'preferred_day' => 'Selasa',
                'preferred_time' => '19:00:00',
                'active_since' => '2024-08-01',
                'last_session_at' => now()->subDays(20),
                'notes' => 'Cuti 2 minggu karena UAS sekolah',
            ],
        ];

        foreach ($students as $data) {
            Student::firstOrCreate(
                ['student_code' => $data['student_code']],
                $data
            );
        }
    }
}