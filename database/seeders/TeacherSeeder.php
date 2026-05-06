<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Instrument;
 
class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = Instrument::pluck('id', 'code');
        // Format: [code, name, [array_kode_instrumen — pertama = primary]]
        $teachers = [
            ['T01', 'Thomas',  ['DRUM']],
            ['T02', 'Adi',     ['PIANO']],
            ['T03', 'Debora',  ['PIANO']],
            ['T04', 'Major',   ['DRUM']],
            ['T05', 'Yuan',    ['BASS', 'PIANO', 'GITAR']],
            ['T06', 'Nael',    ['PIANO', 'GITAR']],
            ['T07', 'Arya',    ['PIANO', 'DRUM']],
            ['T08', 'Daniel',  ['PIANO', 'GITAR']],
            ['T09', 'T.Hadi',  ['PIANO', 'GITAR', 'VOCAL']],
            ['T10', 'Devi',    ['VOCAL']],
            ['T11', 'Indri',   ['PIANO']],
            ['T12', 'Pauline', ['VOCAL', 'PIANO']],
            ['T13', 'Ribka',   ['VIOLIN']],
            ['T14', 'Dedo',    ['PIANO']],
            ['T15', 'Charly',  ['DRUM']],
            ['T16', 'Ica',     ['KIDS']],
            ['T17', 'Samuel',  ['PIANO']],
            ['T18', 'Fidel',   ['PIANO', 'VOCAL']],
        ];
 
        foreach ($teachers as [$code, $name, $instrCodes]) {
            $teacher = Teacher::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true]
            );
            $syncData = [];
            foreach ($instrCodes as $i => $instrCode) {
                $syncData[$instruments[$instrCode]] = ['is_primary' => $i === 0];
            }
            $teacher->instruments()->sync($syncData);
        }
    }
}
