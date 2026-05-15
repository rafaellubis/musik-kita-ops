<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceComponent;

class InvoiceComponentSeeder extends Seeder
{
    public function run(): void
    {
        // Kolom tabel: code, name, default_price, description, is_active, sort_order
        $components = [
            [
                'code'          => 'REG',
                'name'          => 'Pendaftaran',
                'default_price' => 250000,
                'description'   => 'Biaya pendaftaran sekali bayar saat status berubah Calon → Trial.',
                'sort_order'    => 1,
            ],
            [
                'code'          => 'SPP',
                'name'          => 'SPP Bulanan',
                'default_price' => 0,
                'description'   => 'Tagihan bulanan sesuai harga paket yang diambil murid. Auto-generate setiap awal bulan.',
                'sort_order'    => 2,
            ],
            [
                'code'          => 'KIDS_FP',
                'name'          => 'Final Project Kids Class',
                'default_price' => 140000,
                'description'   => 'Biaya final project kids class (sekali per murid di akhir program).',
                'sort_order'    => 3,
            ],
            [
                'code'          => 'CUTI',
                'name'          => 'Pengajuan Cuti',
                'default_price' => 100000,
                'description'   => 'Biaya administrasi setiap pengajuan cuti.',
                'sort_order'    => 4,
            ],
            [
                'code'          => 'UJI',
                'name'          => 'Ujian + Mini Concert',
                'default_price' => 395000,
                'description'   => 'Biaya paket ujian kenaikan grade plus tampil di mini concert.',
                'sort_order'    => 5,
            ],
            [
                'code'          => 'MC',
                'name'          => 'Mini Concert',
                'default_price' => 295000,
                'description'   => 'Biaya tampil di mini concert tanpa ujian.',
                'sort_order'    => 6,
            ],
            [
                'code'          => 'DENDA',
                'name'          => 'Denda Keterlambatan',
                'default_price' => 5000,
                'description'   => 'Denda keterlambatan pembayaran SPP, dihitung Rp 5.000 per hari telat.',
                'sort_order'    => 7,
            ],
        ];

        foreach ($components as $c) {
            InvoiceComponent::firstOrCreate(
                ['code' => $c['code']],
                array_merge($c, ['is_active' => true])
            );
        }
    }
}