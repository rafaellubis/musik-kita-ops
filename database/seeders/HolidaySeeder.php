<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Holiday;
 
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['2026-01-01', 'Tahun Baru Masehi', 'Nasional', null],
            ['2026-02-17', 'Tahun Baru Imlek 2577', 'Nasional', 'Konfirmasi SKB'],
            ['2026-03-19', 'Hari Raya Nyepi', 'Nasional', 'Konfirmasi SKB'],
            ['2026-03-20', 'Cuti Bersama Nyepi', 'Cuti Bersama', null],
            ['2026-03-21', 'Idul Fitri 1447 H (Hari ke-1)', 'Nasional', 'PERKIRAAN'],
            ['2026-03-22', 'Idul Fitri 1447 H (Hari ke-2)', 'Nasional', 'PERKIRAAN'],
            ['2026-03-23', 'Cuti Bersama Idul Fitri', 'Cuti Bersama', 'PERKIRAAN'],
            ['2026-03-24', 'Cuti Bersama Idul Fitri', 'Cuti Bersama', 'PERKIRAAN'],
            ['2026-04-03', 'Wafat Yesus Kristus (Jumat Agung)', 'Nasional', null],
            ['2026-05-01', 'Hari Buruh Internasional', 'Nasional', null],
            ['2026-05-14', 'Kenaikan Yesus Kristus', 'Nasional', null],
            ['2026-05-21', 'Hari Raya Waisak 2570', 'Nasional', 'PERKIRAAN'],
            ['2026-05-27', 'Idul Adha 1447 H', 'Nasional', 'PERKIRAAN'],
            ['2026-06-01', 'Hari Lahir Pancasila', 'Nasional', null],
            ['2026-06-16', 'Tahun Baru Islam 1448 H', 'Nasional', 'PERKIRAAN'],
            ['2026-08-17', 'Hari Kemerdekaan RI', 'Nasional', null],
            ['2026-08-25', 'Maulid Nabi Muhammad SAW', 'Nasional', 'PERKIRAAN'],
            ['2026-12-25', 'Hari Raya Natal', 'Nasional', null],
            ['2026-12-26', 'Cuti Bersama Natal', 'Cuti Bersama', null],
        ];
 
        foreach ($holidays as [$date, $name, $type, $notes]) {
            Holiday::firstOrCreate(
                ['date' => $date],
                ['name' => $name, 'type' => $type, 'is_active' => true, 'notes' => $notes]
            );
        }
    }
}
