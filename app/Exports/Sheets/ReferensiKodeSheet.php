<?php

namespace App\Exports\Sheets;

use App\Models\Package;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReferensiKodeSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Referensi Kode';
    }

    public function array(): array
    {
        $rows = [];

        // Bagian kode paket — ambil dari DB, kolom: code, class_type, duration_min
        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();
        $rows[] = ['=== KODE PAKET ===', '', ''];
        $rows[] = ['package_code', 'Tipe Kelas', 'Durasi (menit)'];
        if ($packages->isEmpty()) {
            $rows[] = ['(tidak ada paket aktif — hubungi admin)', '', ''];
        } else {
            foreach ($packages as $pkg) {
                $rows[] = [$pkg->code, $pkg->class_type, $pkg->duration_min];
            }
        }

        $rows[] = ['', '', ''];

        // Bagian kode guru — ambil dari DB, kolom: code, name
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        $rows[] = ['=== KODE GURU ===', '', ''];
        $rows[] = ['teacher_code', 'Nama Guru', ''];
        if ($teachers->isEmpty()) {
            $rows[] = ['(tidak ada guru aktif — hubungi admin)', '', ''];
        } else {
            foreach ($teachers as $teacher) {
                $rows[] = [$teacher->code, $teacher->name, ''];
            }
        }

        $rows[] = ['', '', ''];

        // Nilai valid untuk kolom 'status'
        $rows[] = ['=== NILAI STATUS ===', '', ''];
        foreach (['Calon', 'Trial', 'Aktif', 'Cuti', 'Selesai', 'Mengundurkan Diri'] as $s) {
            $rows[] = [$s, '', ''];
        }

        $rows[] = ['', '', ''];

        // Nilai valid untuk kolom 'preferred_day'
        $rows[] = ['=== NILAI PREFERRED_DAY ===', '', ''];
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $d) {
            $rows[] = [$d, '', ''];
        }

        $rows[] = ['', '', ''];

        // Nilai valid untuk kolom 'parent_relationship'
        $rows[] = ['=== NILAI PARENT_RELATIONSHIP ===', '', ''];
        foreach (['Ayah', 'Ibu', 'Wali'] as $r) {
            $rows[] = [$r, '', ''];
        }

        return $rows;
    }
}
