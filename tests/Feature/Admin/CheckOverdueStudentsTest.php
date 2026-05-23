<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test untuk command students:check-overdue (M05 — Auto-Mundur / Overdue Detection).
 * Verifikasi bahwa notifikasi dikirim ke Admin + Owner untuk murid Aktif
 * dengan tunggakan >1 bulan, dan tidak dikirim untuk kasus yang tidak relevan.
 */
class CheckOverdueStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang dibutuhkan — pola sama dengan test lain di project ini
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    /** Murid Aktif + invoice UNPAID bulan lalu → notif dikirim ke Admin dan Owner. */
    public function test_murid_aktif_dengan_tunggakan_mendapat_notifikasi(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);

        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertSentTo($this->admin, MuridOverdueNotification::class);
        Notification::assertSentTo($this->owner, MuridOverdueNotification::class);
    }

    /** Murid Aktif tanpa tunggakan → tidak ada notif. */
    public function test_murid_aktif_tanpa_tunggakan_tidak_dapat_notifikasi(): void
    {
        Notification::fake();

        Student::factory()->create(['status' => 'Aktif']);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Murid bukan Aktif + invoice UNPAID → tidak ada notif. */
    public function test_murid_tidak_aktif_diabaikan(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Mengundurkan Diri']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Invoice bulan ini yang belum lunas → tidak trigger. */
    public function test_tunggakan_bulan_ini_tidak_trigger(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => now()->year,
            'month'      => now()->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * Jalankan command dua kali → notif tidak duplikat (idempotent).
     * Test ini TIDAK pakai Notification::fake() agar notif benar-benar
     * tersimpan ke tabel notifications sehingga idempotency guard bisa membacanya.
     */
    public function test_idempotent_tidak_kirim_notif_duplikat(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        // Run pertama: harus kirim 1 notif ke admin
        $this->artisan('students:check-overdue')->assertSuccessful();
        $this->assertEquals(1, $this->admin->notifications()->count());

        // Run kedua: idempotency guard harus mencegah duplikat
        $this->artisan('students:check-overdue')->assertSuccessful();
        $this->assertEquals(1, $this->admin->notifications()->count());
    }

    /** Invoice PARTIAL juga trigger notif. */
    public function test_invoice_partial_juga_trigger(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'PARTIAL',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertSentTo($this->admin, MuridOverdueNotification::class);
    }
}
