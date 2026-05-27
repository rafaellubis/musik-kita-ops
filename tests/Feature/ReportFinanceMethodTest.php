<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test untuk memastikan laporan keuangan memisahkan 4 metode pembayaran
 * (CASH, TRANSFER, QRIS, DEBIT) sebagai baris terpisah — bukan digabung.
 *
 * Test ini ditulis SEBELUM implementasi (TDD red phase).
 * Controller belum mengirimkan $revenueByMethod — semua test akan FAIL dulu.
 */
class ReportFinanceMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie Permission butuh role tersedia di DB sebelum assignRole()
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    /** @test */
    public function laporan_menampilkan_empat_metode_bayar_terpisah(): void
    {
        $year  = 2026;
        $month = 5;
        $date  = '2026-05-15';

        // Buat 4 payment dengan metode dan nominal berbeda, bukan void
        Payment::factory()->create(['method' => 'CASH',     'amount' => 500000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['method' => 'TRANSFER', 'amount' => 400000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['method' => 'QRIS',     'amount' => 300000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['method' => 'DEBIT',    'amount' => 200000, 'payment_date' => $date, 'voided_at' => null]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => $year, 'month' => $month]));

        $response->assertStatus(200);
        $response->assertViewHas('revenueByMethod');

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(500000, $byMethod['CASH']     ?? 0, 'CASH salah');
        $this->assertEquals(400000, $byMethod['TRANSFER'] ?? 0, 'TRANSFER salah');
        $this->assertEquals(300000, $byMethod['QRIS']     ?? 0, 'QRIS salah');
        $this->assertEquals(200000, $byMethod['DEBIT']    ?? 0, 'DEBIT salah');
    }

    /** @test */
    public function method_tanpa_transaksi_tidak_error_di_view(): void
    {
        // Bulan kosong — semua metode harus bernilai 0, bukan crash
        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 1]));

        $response->assertStatus(200);
        $response->assertViewHas('revenueByMethod');

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(0, $byMethod['CASH']     ?? 0, 'CASH harus 0 jika tidak ada transaksi');
        $this->assertEquals(0, $byMethod['TRANSFER'] ?? 0, 'TRANSFER harus 0 jika tidak ada transaksi');
        $this->assertEquals(0, $byMethod['QRIS']     ?? 0, 'QRIS harus 0 jika tidak ada transaksi');
        $this->assertEquals(0, $byMethod['DEBIT']    ?? 0, 'DEBIT harus 0 jika tidak ada transaksi');
    }

    /** @test */
    public function payment_void_tidak_dihitung_sebagai_pendapatan(): void
    {
        // Payment yang sudah di-void (voided_at terisi) tidak boleh dihitung sebagai revenue
        Payment::factory()->create([
            'method'       => 'QRIS',
            'amount'       => 100000,
            'payment_date' => '2026-05-10',
            'voided_at'    => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 5]));

        $response->assertStatus(200);
        $response->assertViewHas('revenueByMethod');

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(0, $byMethod['QRIS'] ?? 0, 'Payment void tidak boleh dihitung');
    }
}
