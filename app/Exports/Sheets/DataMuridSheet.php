<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataMuridSheet implements FromArray, WithTitle, WithStyles
{
    public function title(): string
    {
        return 'Data Murid';
    }

    public function array(): array
    {
        return [
            // Baris header — kolom yang wajib/opsional diisi admin
            [
                'full_name', 'nickname', 'gender', 'birth_date', 'phone', 'email',
                'address', 'notes', 'parent_name', 'parent_phone', 'parent_email',
                'parent_relationship', 'status', 'package_code', 'teacher_code',
                'preferred_day', 'preferred_time', 'active_since',
            ],
            // Baris contoh — admin hapus sebelum upload
            [
                'Budi Santoso', 'Budi', 'L', '2010-05-15', '08111111111',
                'budi@email.com', 'Jl. Contoh No.1', 'Catatan contoh',
                'Ayah Budi', '08111111112', 'ayahbudi@email.com', 'Ayah',
                'Aktif', 'PIANO-REG-BASIC', 'T-ADI',
                'Senin', '15:30', '2026-01-15',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Baris header ditebalkan
            1 => ['font' => ['bold' => true]],
        ];
    }
}
