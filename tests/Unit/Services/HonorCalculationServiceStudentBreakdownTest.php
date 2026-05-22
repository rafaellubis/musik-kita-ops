<?php

namespace Tests\Unit\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\HonorSlip;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\HonorCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HonorCalculationServiceStudentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private HonorCalculationService $service;
    private Teacher $teacher;
    private HonorSlip $slip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HonorCalculationService();

        $this->teacher = Teacher::create([
            'code'      => 'T01',
            'name'      => 'Daniel',
            'is_active' => true,
        ]);

        $this->slip = HonorSlip::create([
            'slip_number'     => 'SLIP/2026/05/0001',
            'teacher_id'      => $this->teacher->id,
            'year'            => 2026,
            'month'           => 5,
            'base_honor'      => 0,
            'transport_honor' => 0,
            'other_honor'     => 0,
            'total_honor'     => 0,
            'status'          => 'CALCULATED',
            'created_by'      => null, // nullable FK — tidak perlu user nyata di unit test
        ]);
    }

    /** Buat murid + enrollment + sesi privat untuk teacher */
    private function buatSesiPrivat(
        string $namaMurid,
        string $namaInstrumen,
        int $jumlahSesi,
        int $honorPerSesi
    ): void {
        $instrumen = Instrument::firstOrCreate(['name' => $namaInstrumen, 'code' => strtoupper(substr($namaInstrumen, 0, 3))]);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-' . $instrumen->id],
            [
                'instrument_id'   => $instrumen->id,
                'class_type'      => 'REGULER',
                'duration_min'    => 30,
                'price_per_month' => 400000,
                'is_active'       => true,
                'sort_order'      => 1,
            ]
        );
        $student = Student::create([
            'student_code'        => 'M-2026-' . rand(1000, 9999),
            'full_name'           => $namaMurid,
            'gender'              => 'L',
            'parent_relationship' => 'Ayah',
            'status'              => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $this->teacher->id,
            'effective_date' => '2026-01-01',
            'status'         => 'ACTIVE',
        ]);

        for ($i = 1; $i <= $jumlahSesi; $i++) {
            ClassSession::create([
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $this->teacher->id,
                'session_date'  => Carbon::create(2026, 5, $i * 6),
                'start_time'    => '10:00:00',
                'end_time'      => '10:30:00',
                'status'        => 'HADIR',
                'honor_code'    => 'H_REG',
                'honor_amount'  => $honorPerSesi,
            ]);
        }
    }

    /** Buat murid + sesi Kids Class untuk teacher */
    private function buatSesiKids(string $namaMurid, int $jumlahSesi): void
    {
        $instrumen = Instrument::firstOrCreate(['name' => 'Kids Class', 'code' => 'KID']);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-KIDS'],
            [
                'instrument_id'   => $instrumen->id,
                'class_type'      => 'KIDS_CLASS',
                'duration_min'    => 45,
                'price_per_month' => 340000,
                'is_active'       => true,
                'sort_order'      => 10,
            ]
        );
        $student = Student::create([
            'student_code'        => 'M-2026-' . rand(1000, 9999),
            'full_name'           => $namaMurid,
            'gender'              => 'P',
            'parent_relationship' => 'Ibu',
            'status'              => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $this->teacher->id,
            'effective_date' => '2026-01-01',
            'status'         => 'ACTIVE',
        ]);

        for ($i = 1; $i <= $jumlahSesi; $i++) {
            ClassSession::create([
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $this->teacher->id,
                'session_date'  => Carbon::create(2026, 5, $i * 6),
                'start_time'    => '09:00:00',
                'end_time'      => '09:45:00',
                'status'        => 'HADIR',
                'honor_code'    => 'H_KIDS',
                'honor_amount'  => 42500,
            ]);
        }
    }

    /** T1: Guru privat 2 murid */
    public function test_privat_dua_murid_menghasilkan_dua_baris(): void
    {
        $this->buatSesiPrivat('Aditya', 'Piano', 4, 50000);
        $this->buatSesiPrivat('Bintang', 'Gitar', 3, 48750);

        $result = $this->service->getStudentBreakdown($this->slip);

        $this->assertCount(2, $result);

        $aditya = $result->firstWhere('student_name', 'Aditya');
        $this->assertEquals(4, $aditya['session_count']);
        $this->assertEquals(200000, $aditya['total_amount']);
        $this->assertEquals('Piano', $aditya['instrument']);
        $this->assertFalse($aditya['is_kids']);

        $bintang = $result->firstWhere('student_name', 'Bintang');
        $this->assertEquals(3, $bintang['session_count']);
        $this->assertEquals(146250, $bintang['total_amount']);
        $this->assertFalse($bintang['is_kids']);
    }

    /** T2: Kids Class 4 murid, 4 sesi → tiap murid 4 sesi × 42.500 */
    public function test_kids_class_tiap_murid_dapat_baris_sendiri(): void
    {
        $this->buatSesiKids('Andi', 4);
        $this->buatSesiKids('Budi', 4);
        $this->buatSesiKids('Cici', 4);
        $this->buatSesiKids('Dodi', 4);

        $result = $this->service->getStudentBreakdown($this->slip);

        $this->assertCount(4, $result);
        $result->each(function ($row) {
            $this->assertEquals(4, $row['session_count']);
            $this->assertEquals(170000, $row['total_amount']);
            $this->assertEquals('Kids Class', $row['instrument']);
            $this->assertTrue($row['is_kids']);
        });
    }

    /** T3: Murid Kids Class yang hanya hadir 3 sesi dari 4 */
    public function test_kids_murid_dengan_sesi_berbeda(): void
    {
        $this->buatSesiKids('Andi', 4);
        $this->buatSesiKids('Cici', 3);

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(2, $result);

        $andi = $result->firstWhere('student_name', 'Andi');
        $this->assertEquals(4, $andi['session_count']);
        $this->assertEquals(170000, $andi['total_amount']);

        $cici = $result->firstWhere('student_name', 'Cici');
        $this->assertEquals(3, $cici['session_count']);
        $this->assertEquals(127500, $cici['total_amount']);
    }

    /** T4: Campuran privat + Kids Class — privat urut nama dulu, lalu kids */
    public function test_campuran_privat_dan_kids_class_urutan_benar(): void
    {
        $this->buatSesiKids('Zara', 4);
        $this->buatSesiPrivat('Aditya', 'Piano', 4, 50000);

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(2, $result);

        $this->assertFalse($result->first()['is_kids']);
        $this->assertEquals('Aditya', $result->first()['student_name']);

        $this->assertTrue($result->last()['is_kids']);
        $this->assertEquals('Zara', $result->last()['student_name']);
    }

    /** T5: Sesi bulan lain tidak masuk ke breakdown */
    public function test_sesi_bulan_lain_tidak_masuk(): void
    {
        $instrumen = Instrument::firstOrCreate(['name' => 'Piano', 'code' => 'PNO']);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-PNO'],
            ['instrument_id' => $instrumen->id, 'class_type' => 'REGULER',
             'duration_min' => 30, 'price_per_month' => 400000, 'is_active' => true, 'sort_order' => 1]
        );
        $student = Student::create([
            'student_code' => 'M-2026-9999', 'full_name' => 'Lain Bulan',
            'gender' => 'L', 'parent_relationship' => 'Ayah', 'status' => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $this->teacher->id, 'effective_date' => '2026-01-01', 'status' => 'ACTIVE',
        ]);
        ClassSession::create([
            'enrollment_id' => $enrollment->id, 'student_id' => $student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => '2026-04-10',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => 'HADIR', 'honor_code' => 'H_REG', 'honor_amount' => 50000,
        ]);

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(0, $result);
    }
}
