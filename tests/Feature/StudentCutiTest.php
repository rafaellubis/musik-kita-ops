<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Invoice;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Models\StudentStatusHistory;
use App\Services\InvoiceService;
use App\Services\SessionGeneratorService;
use App\Services\StudentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCutiTest extends TestCase
{
    use RefreshDatabase;

    private StudentLifecycleService $lifecycle;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 7, 1)->startOfDay());
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('Admin'));
        $this->lifecycle = app(StudentLifecycleService::class);
        $this->student = Student::factory()->create(['status' => 'Aktif']);
    }

    public function test_cuti_hanya_membatalkan_kelas_terpilih_dan_invoice_terikat_ke_kelas(): void
    {
        $selected = $this->enrollment();
        $other = $this->enrollment();
        $inside = $this->createSession($selected, '2026-07-10');
        $outside = $this->createSession($selected, '2026-08-01');
        $otherSession = $this->createSession($other, '2026-07-10');
        $attended = $this->createSession($selected, '2026-07-15', ClassSession::STATUS_HADIR);

        $result = $this->applyLeave($selected, '2026-07-01', '2026-07-31');

        $this->assertSame(ClassSession::STATUS_CANCELLED, $inside->fresh()->status);
        $this->assertSame(ClassSession::STATUS_SCHEDULED, $otherSession->fresh()->status);
        $this->assertSame(ClassSession::STATUS_SCHEDULED, $outside->fresh()->status);
        $this->assertSame(ClassSession::STATUS_HADIR, $attended->fresh()->status);
        $this->assertEquals(0, $inside->fresh()->honor_amount);
        $this->assertSame('Aktif', $result->status);
        $this->assertSame(Enrollment::STATUS_ON_LEAVE, $selected->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $other->fresh()->status);
        $invoice = $result->invoices()->whereHas('items', fn ($q) => $q->where('item_code', 'CUTI'))->sole();
        $this->assertEquals($selected->id, $invoice->enrollment_id);
        $this->assertEquals(100000, $invoice->total_amount);
        $history = StudentStatusHistory::where('student_id', $result->id)->latest('id')->firstOrFail();
        $this->assertEquals($selected->id, $history->metadata['enrollment_id']);
        $this->assertEquals($invoice->id, $history->metadata['invoice_id']);
        $audit = AuditLog::where('entity_type', 'Enrollment')->where('entity_id', $selected->id)
            ->whereNotNull('new_values->invoice_id')->sole();
        $this->assertEquals($invoice->id, $audit->new_values['invoice_id']);
        $this->assertEquals($history->metadata['leave_id'], $audit->new_values['leave_id']);
    }

    public function test_cuti_masa_depan_tetap_aktif_hingga_tanggal_mulai(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-10', '2026-07-20');
        $this->assertSame('Aktif', $this->student->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);

        $this->travelTo(now()->setDate(2026, 7, 10));
        $this->lifecycle->synchronizeLeaveStatus($this->student);
        $this->assertSame('Cuti', $this->student->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ON_LEAVE, $enrollment->fresh()->status);

        $this->travelTo(now()->setDate(2026, 7, 21));
        $this->lifecycle->synchronizeLeaveStatus($this->student);
        $this->assertSame('Aktif', $this->student->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);
    }

    public function test_akhir_cuti_inklusif_dan_riwayat_tetap_tersimpan_setelah_kembali(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->travelTo(now()->setDate(2026, 7, 31));
        try {
            $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $enrollment->id]);
            $this->fail('Hari terakhir cuti belum boleh diakhiri.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('belum selesai', $e->getMessage());
        }
        $this->travelTo(now()->setDate(2026, 8, 1));
        $result = $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $enrollment->id]);
        $this->assertSame('Aktif', $result->status);
        $this->assertNull($enrollment->fresh()->currentLeave);
        $leave = $enrollment->leaves()->sole();
        $this->assertSame('2026-07-01', $leave->cuti_from->toDateString());
        $this->assertSame('2026-07-31', $leave->cuti_until->toDateString());
        $this->assertNotNull($leave->completed_at);
        $this->applyLeave($enrollment, '2026-08-01', '2026-08-31');
        $this->assertSame(2, $enrollment->leaves()->count());
        $this->assertSame('2026-08-01', $enrollment->fresh()->currentLeave->cuti_from->toDateString());
    }

    public function test_kembali_dari_satu_kelas_tidak_mengakhiri_cuti_kelas_lain(): void
    {
        $selected = $this->enrollment();
        $other = $this->enrollment();
        $this->applyLeave($selected, '2026-07-01', '2026-07-10');
        $this->applyLeave($other, '2026-07-01', '2026-07-31');
        $this->travelTo(now()->setDate(2026, 7, 11));
        $result = $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $selected->id]);
        $this->assertSame('Aktif', $result->status);
        $this->assertSame(Enrollment::STATUS_ON_LEAVE, $other->fresh()->status);
        $this->assertNull($other->fresh()->currentLeave->completed_at);
    }

    public function test_perpanjangan_sekali_mempertahankan_awal_dan_maksimal_62_hari_inklusif(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->lifecycle->ajukanCuti($this->student, [
            'enrollment_id' => $enrollment->id,
            'cuti_from' => '2026-07-15',
            'cuti_until' => '2026-08-31',
            'reason' => 'Perpanjang karena sakit',
        ]);
        $leave = $enrollment->fresh()->currentLeave;
        $this->assertSame('2026-07-01', $leave->cuti_from->toDateString());
        $this->assertSame('2026-08-31', $leave->cuti_until->toDateString());
        $this->assertSame(1, $leave->extension_count);
        $this->assertSame(2, $this->student->invoices()->where('enrollment_id', $enrollment->id)->count());
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle->ajukanCuti($this->student, [
            'enrollment_id' => $enrollment->id, 'cuti_until' => '2026-09-01', 'reason' => 'Kedua kali',
        ]);
    }

    public function test_perpanjangan_hari_ke_63_ditolak(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle->ajukanCuti($this->student, [
            'enrollment_id' => $enrollment->id, 'cuti_until' => '2026-09-01', 'reason' => 'Terlalu panjang',
        ]);
    }

    public function test_perpanjangan_tidak_boleh_mempersingkat(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle->ajukanCuti($this->student, [
            'enrollment_id' => $enrollment->id, 'cuti_until' => '2026-07-20', 'reason' => 'Persingkat',
        ]);
    }

    public function test_pengajuan_awal_hari_ke_32_ditolak(): void
    {
        $enrollment = $this->enrollment();
        $this->expectException(InvalidArgumentException::class);
        $this->applyLeave($enrollment, '2026-07-01', '2026-08-01');
    }

    public function test_enrollment_id_wajib_dan_tidak_ada_fallback_kelas_utama(): void
    {
        $this->enrollment();
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_from' => '2026-07-01', 'cuti_until' => '2026-07-31', 'reason' => 'Tanpa kelas',
        ]);
    }

    public function test_kelas_murid_lain_ditolak_tanpa_invoice_atau_perubahan_status(): void
    {
        $foreign = Enrollment::factory()->create();
        try {
            $this->applyLeave($foreign, '2026-07-01', '2026-07-31');
            $this->fail('Kelas murid lain harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Aktif', $this->student->fresh()->status);
            $this->assertSame(0, $this->student->invoices()->count());
            $this->assertSame(Enrollment::STATUS_ACTIVE, $foreign->fresh()->status);
        }
    }

    public function test_kelas_nonaktif_ditolak(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->update(['status' => Enrollment::STATUS_INACTIVE]);
        $this->expectException(InvalidArgumentException::class);
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
    }

    public function test_spp_bulan_penuh_dilewati_tetapi_kelas_lain_ditagih_penuh(): void
    {
        $selected = $this->enrollment();
        $other = $this->enrollment();
        $this->applyLeave($selected, '2026-07-01', '2026-07-31');
        $report = app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $this->assertSame(1, $report['created']);
        $this->assertFalse($this->sppQuery($selected, 7)->exists());
        $this->assertEquals(340000, $this->sppQuery($other, 7)->sole()->total_amount);
    }

    public function test_spp_cuti_parsial_bulan_tetap_harga_penuh_walaupun_murid_cuti(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-20');
        $this->assertSame('Cuti', $this->student->fresh()->status);
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $this->assertEquals(340000, $this->sppQuery($enrollment, 7)->sole()->total_amount);
    }

    public function test_spp_terbit_sebelum_cuti_tidak_diubah(): void
    {
        $enrollment = $this->enrollment();
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $invoice = $this->sppQuery($enrollment, 7)->sole();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $this->assertEquals(340000, $invoice->fresh()->total_amount);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->fresh()->status);
        $this->assertSame(1, $this->sppQuery($enrollment, 7)->count());
    }

    public function test_spp_bulan_penuh_dari_dua_periode_berurutan_tetap_dikecualikan(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-10');
        $this->travelTo(now()->setDate(2026, 7, 11));
        $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $enrollment->id]);
        $this->applyLeave($enrollment, '2026-07-11', '2026-07-31');
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $this->assertFalse($this->sppQuery($enrollment, 7)->exists());
    }

    public function test_spp_dua_periode_dengan_satu_hari_aktif_tetap_harga_penuh(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-10');
        $this->travelTo(now()->setDate(2026, 7, 11));
        $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $enrollment->id]);
        $this->applyLeave($enrollment, '2026-07-12', '2026-07-31');
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        $this->assertEquals(340000, $this->sppQuery($enrollment, 7)->sole()->total_amount);
    }

    public function test_riwayat_cuti_tetap_mengecualikan_spp_dan_sesi_saat_generate_ulang(): void
    {
        $enrollment = $this->enrollment();
        Schedule::factory()->create(['enrollment_id' => $enrollment->id, 'day_of_week' => 1]);
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->travelTo(now()->setDate(2026, 8, 1));
        $this->lifecycle->aktifkanDariCuti($this->student, ['enrollment_id' => $enrollment->id]);
        app(InvoiceService::class)->generateMonthlySPP(2026, 7);
        app(SessionGeneratorService::class)->generateForMonth(2026, 7);
        $this->assertFalse($this->sppQuery($enrollment, 7)->exists());
        $this->assertSame(0, $enrollment->classSessions()->count());
    }

    public function test_generator_mengecualikan_tanggal_cuti_terpilih_tanpa_menggeser_slot(): void
    {
        $selected = $this->enrollment();
        $other = $this->enrollment();
        Schedule::factory()->create(['enrollment_id' => $selected->id, 'day_of_week' => 1]);
        Schedule::factory()->create(['enrollment_id' => $other->id, 'day_of_week' => 1]);
        $this->applyLeave($selected, '2026-07-06', '2026-07-13');
        app(SessionGeneratorService::class)->generateForMonth(2026, 7);
        $this->assertSame(['2026-07-20', '2026-07-27'], $selected->classSessions()->orderBy('session_date')->pluck('session_date')->all());
        $this->assertSame([3, 4], $selected->classSessions()->orderBy('session_date')->pluck('session_sequence')->all());
        $this->assertSame(4, $other->classSessions()->count());
    }

    public function test_tanggal_pengganti_dalam_cuti_dikecualikan_meski_bulan_berbeda(): void
    {
        $enrollment = $this->enrollment();
        Schedule::factory()->create(['enrollment_id' => $enrollment->id, 'day_of_week' => 1]);
        Holiday::factory()->create(['date' => '2026-07-06', 'replacement_date' => '2026-08-03']);
        $this->applyLeave($enrollment, '2026-08-01', '2026-08-10');
        $report = app(SessionGeneratorService::class)->generateForMonth(2026, 7);
        $this->assertSame(0, $report['replacements_created']);
        $this->assertFalse($enrollment->classSessions()->whereDate('session_date', '2026-08-03')->exists());
        $this->assertSame(4, $enrollment->classSessions()->count());
    }

    public function test_sinkronisasi_tidak_menghidupkan_kembali_murid_mundur(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->student->update(['status' => 'Mengundurkan Diri']);
        $enrollment->update(['status' => Enrollment::STATUS_INACTIVE]);
        $this->lifecycle->synchronizeLeaveStatus($this->student);
        $this->assertSame('Mengundurkan Diri', $this->student->fresh()->status);
        $this->assertSame(Enrollment::STATUS_INACTIVE, $enrollment->fresh()->status);
    }

    public function test_mundur_menutup_kelas_cuti_tanpa_menghapus_riwayat_periode(): void
    {
        $enrollment = $this->enrollment();
        $schedule = Schedule::factory()->create(['enrollment_id' => $enrollment->id]);
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-31');
        $this->lifecycle->mundurkan($this->student->fresh(), ['reason' => 'Pindah kota']);
        $this->assertSame(Enrollment::STATUS_INACTIVE, $enrollment->fresh()->status);
        $this->assertFalse((bool) $schedule->fresh()->is_active);
        $this->assertSame(1, $enrollment->leaves()->count());
    }

    public function test_pengajuan_satu_hari_diperbolehkan_dan_kembali_tetap_wajib_kelas(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-01');
        $this->assertSame('Cuti', $this->student->fresh()->status);
        $this->travelTo(now()->setDate(2026, 7, 2));
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle->aktifkanDariCuti($this->student, []);
    }

    public function test_periode_berakhir_tidak_boleh_direset_melalui_perpanjangan(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-10');
        $this->travelTo(now()->setDate(2026, 7, 11));
        $this->expectException(InvalidArgumentException::class);
        $this->applyLeave($enrollment, '2026-07-11', '2026-07-31');
    }

    public function test_generator_kelas_on_leave_tetap_membuat_sesi_setelah_akhir_cuti(): void
    {
        $enrollment = $this->enrollment();
        Schedule::factory()->create(['enrollment_id' => $enrollment->id, 'day_of_week' => 1]);
        $this->applyLeave($enrollment, '2026-07-01', '2026-07-13');
        app(SessionGeneratorService::class)->generateForMonth(2026, 7);
        $this->assertSame(['2026-07-20', '2026-07-27'], $enrollment->classSessions()->orderBy('session_date')->pluck('session_date')->all());
    }

    public function test_spp_cuti_bulan_penuh_masa_depan_dilewati_sebelum_status_cuti(): void
    {
        $enrollment = $this->enrollment();
        $this->applyLeave($enrollment, '2026-08-01', '2026-08-31');
        $this->assertSame('Aktif', $this->student->fresh()->status);
        app(InvoiceService::class)->generateMonthlySPP(2026, 8);
        $this->assertFalse($this->sppQuery($enrollment, 8)->exists());
    }

    public function test_migrasi_mempertahankan_periode_lama_dan_menyelesaikan_yang_sudah_lewat(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $migration = require database_path('migrations/2026_10_08_000001_create_enrollment_leaves_table.php');
        $migration->down();
        $this->student->update(['status' => 'Cuti', 'cuti_from' => '2026-06-01', 'cuti_until' => '2026-06-30']);
        $elapsed = $this->enrollment();
        $inactive = $this->enrollment();
        $inactive->update(['status' => Enrollment::STATUS_INACTIVE]);
        $currentStudent = Student::factory()->create([
            'status' => 'Cuti', 'cuti_from' => '2026-07-01', 'cuti_until' => '2026-07-31',
        ]);
        $current = Enrollment::factory()->create([
            'student_id' => $currentStudent->id, 'status' => Enrollment::STATUS_ON_LEAVE,
        ]);

        $migration->up();

        $oldLeave = $elapsed->leaves()->sole();
        $this->assertSame('2026-06-01', $oldLeave->cuti_from->toDateString());
        $this->assertSame('2026-06-30', $oldLeave->cuti_until->toDateString());
        $this->assertNotNull($oldLeave->completed_at);
        $this->assertSame(0, $inactive->leaves()->count());
        $this->assertSame('2026-07-01', $current->fresh()->currentLeave->cuti_from->toDateString());
        $this->assertNull($current->fresh()->currentLeave->completed_at);
        $this->assertSame('2026-06-01', $this->student->fresh()->cuti_from->toDateString());
    }

    private function enrollment(): Enrollment
    {
        return Enrollment::factory()->create(['student_id' => $this->student->id]);
    }

    private function applyLeave(Enrollment $enrollment, string $from, string $until): Student
    {
        return $this->lifecycle->ajukanCuti($this->student, [
            'enrollment_id' => $enrollment->id, 'cuti_from' => $from,
            'cuti_until' => $until, 'reason' => 'UAS sekolah',
        ]);
    }

    private function createSession(Enrollment $enrollment, string $date, string $status = ClassSession::STATUS_SCHEDULED): ClassSession
    {
        return ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id, 'student_id' => $this->student->id,
            'teacher_id' => $enrollment->teacher_id, 'session_date' => $date,
            'status' => $status, 'honor_amount' => 42500,
        ]);
    }

    private function sppQuery(Enrollment $enrollment, int $month)
    {
        return $this->student->invoices()->where('enrollment_id', $enrollment->id)
            ->where('year', 2026)->where('month', $month)
            ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'));
    }
}
