<?php

namespace App\Exports\Sheets;

use App\Models\Package;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReferensiKodeSheet implements FromArray, WithTitle, WithStyles
{
    public function title(): string
    {
        return 'Referensi Kode';
    }

    public function array(): array
    {
        $rows = [];

        // Bagian kode paket — ambil dari DB, kolom: code, class_type, duration_min
        $rows[] = ['=== KODE PAKET ===', '', ''];
        $rows[] = ['package_code', 'Tipe Kelas', 'Durasi (menit)'];
        foreach (Package::where('is_active', true)->orderBy('sort_order')->get() as $pkg) {
            $rows[] = [$pkg->code, $pkg->class_type, $pkg->duration_min];
        }

        $rows[] = ['', '', ''];

        // Bagian kode guru — ambil dari DB, kolom: code, name
        $rows[] = ['=== KODE GURU ===', '', ''];
        $rows[] = ['teacher_code', 'Nama Guru', ''];
        foreach (Teacher::where('is_active', true)->orderBy('name')->get() as $teacher) {
            $rows[] = [$teacher->code, $teacher->name, ''];
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

    public function styles(Worksheet $sheet): array
    {
        return [
            // Kolom A ditebalkan (berisi label kode/header seksi)
            'A' => ['font' => ['bold' => true]],
        ];
    }
}
