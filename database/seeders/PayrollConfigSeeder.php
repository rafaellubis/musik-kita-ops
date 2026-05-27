<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\PayrollConfig;
 
class PayrollConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['H_REG', 'Sesi Reguler Terlaksana', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Sesi normal yang dihadiri murid. Honor = 50% harga paket / 4 sesi.'],
            ['H_TRIAL', 'Sesi Trial', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Sesi trial calon murid. NOL bila siswa no-show.'],
            ['H_VIDEO', 'Izin Video Pengganti', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Saat murid izin >1x/bulan, guru kerjakan video pengganti.'],
            ['H_LIBUR', 'Sesi Kena Libur Nasional', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Sesi tidak terjadi karena tanggal merah, guru tetap dibayar.'],
            ['H_HANGUS', 'Sesi Hangus / No-Show', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Murid tidak hadir tanpa kabar / kabar <5 jam. Guru tetap dibayar.'],
            ['H_PENG', 'Sesi Diajar Guru Pengganti', 'PERCENTAGE', 'package_price * 0.5 / 4',
             'Guru pengganti yang menerima honor.'],
            ['H_KIDS', 'Sesi Kids Class Grup', 'PER_STUDENT', 'registered_students * 42500',
             'Honor per murid TERDAFTAR di grup. 4 murid x 42.500 = 170.000/sesi.'],
            ['H_UJIAN', 'Sesi Ujian Kenaikan Grade', 'FIXED', '250000',
             'Honor flat per sesi ujian.'],
            ['H_PENDAMPING', 'Honor Guru Pendamping Konser KITA', 'FIXED', '250000',
             'Honor flat per event untuk guru yang mendampingi murid di Konser KITA. Bisa berbeda dengan H_UJIAN.'],
            ['H_DUO', 'Sesi DUO Terlaksana', 'FIXED', '40000',
             'Honor guru kelas DUO per murid per sesi. Karena 2 murid, total honor satu slot = 2 × nilai ini.'],
            ['H_CUTI', 'Sesi Saat Murid Cuti', 'FIXED', '0',
             'Periode cuti: sesi tidak ter-generate, honor nol.'],
            ['PAYROLL_CALC_DAY', 'Hari Kalkulasi Honor', 'CONSTANT', 'H-2_AKHIR_BULAN',
             'Honor dikalkulasi otomatis 2 hari sebelum akhir bulan.'],
            ['MIN_KIDS_GROUP', 'Min Murid Kids Class', 'CONSTANT', '3',
             'Grup minimal 3 anak.'],
            ['MAX_KIDS_GROUP', 'Max Murid Kids Class', 'CONSTANT', '4',
             'Grup maksimal 4 anak.'],
        ];
 
        foreach ($configs as [$code, $name, $type, $value, $desc]) {
            PayrollConfig::firstOrCreate(
                ['scenario_code' => $code],
                ['scenario_name' => $name, 'formula_type' => $type,
                 'value_or_formula' => $value, 'description' => $desc, 'is_active' => true]
            );
        }
    }
}
