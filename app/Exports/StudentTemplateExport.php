<?php

namespace App\Exports;

use App\Exports\Sheets\DataMuridSheet;
use App\Exports\Sheets\ReferensiKodeSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StudentTemplateExport implements WithMultipleSheets
{
    /**
     * Mengembalikan daftar sheet untuk file Excel template import murid.
     * Sheet 1: Data Murid — kolom yang diisi admin
     * Sheet 2: Referensi Kode — daftar kode paket, guru, dan enum valid
     */
    public function sheets(): array
    {
        return [
            new DataMuridSheet(),
            new ReferensiKodeSheet(),
        ];
    }
}
