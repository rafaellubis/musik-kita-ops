<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Package;
use App\Models\Instrument;
 
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = Instrument::pluck('id', 'code');
        $sort = 1;
 
        // 30 reguler grade: PIANO, GITAR, DRUM, VOCAL, BASS, VIOLIN x BASIC, L1-L4
        $regular = ['PIANO', 'GITAR', 'DRUM', 'VOCAL', 'BASS', 'VIOLIN'];
        $grades = [['BASIC', 340000], ['L1', 370000], ['L2', 400000], ['L3', 430000], ['L4', 460000]];
        foreach ($regular as $instr) {
            foreach ($grades as [$grade, $price]) {
                Package::firstOrCreate(
                    ['code' => "{$instr}_REG_{$grade}"],
                    ['instrument_id' => $instruments[$instr], 'class_type' => 'REGULER',
                     'grade' => $grade, 'duration_min' => 30, 'price_per_month' => $price,
                     'is_active' => true, 'sort_order' => $sort++]
                );
            }
        }
 
        // 12 hobby: 6 instr x 2 durasi (30 menit, 45 menit)
        $hobbyPrices = [30 => 390000, 45 => 450000];
        foreach ($regular as $instr) {
            foreach ($hobbyPrices as $dur => $price) {
                Package::firstOrCreate(
                    ['code' => "{$instr}_HOBBY_{$dur}"],
                    ['instrument_id' => $instruments[$instr], 'class_type' => 'HOBBY',
                     'grade' => null, 'duration_min' => $dur, 'price_per_month' => $price,
                     'is_active' => true, 'sort_order' => $sort++]
                );
            }
        }
 
        // 2 saxophone hobby
        Package::firstOrCreate(['code' => 'SAX_HOBBY_30'], [
            'instrument_id' => $instruments['SAX'], 'class_type' => 'HOBBY', 'grade' => null,
            'duration_min' => 30, 'price_per_month' => 420000, 'is_active' => true, 'sort_order' => $sort++,
        ]);
        Package::firstOrCreate(['code' => 'SAX_HOBBY_45'], [
            'instrument_id' => $instruments['SAX'], 'class_type' => 'HOBBY', 'grade' => null,
            'duration_min' => 45, 'price_per_month' => 470000, 'is_active' => true, 'sort_order' => $sort++,
        ]);
 
        // 2 kids class
        Package::firstOrCreate(['code' => 'KIDS_GRUP_MONTHLY'], [
            'instrument_id' => $instruments['KIDS'], 'class_type' => 'KIDS_CLASS', 'grade' => null,
            'duration_min' => 45, 'price_per_month' => 340000, 'is_active' => true, 'sort_order' => $sort++,
        ]);
        Package::firstOrCreate(['code' => 'KIDS_GRUP_6BULAN'], [
            'instrument_id' => $instruments['KIDS'], 'class_type' => 'KIDS_CLASS_BUNDLE', 'grade' => null,
            'duration_min' => 45, 'price_per_month' => 2180000, 'is_active' => true, 'sort_order' => $sort++,
        ]);
    }
}
