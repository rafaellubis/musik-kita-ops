<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceItemNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Invoice $invoice;
    private InvoiceItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV/2026/11/0001',
            'student_id' => Student::factory()->create()->id,
            'year' => 2026, 'month' => 11,
            'total_amount' => 370000, 'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-11-10', 'issued_at' => '2026-11-01',
        ]);
        $this->item = $this->invoice->items()->create([
            'item_code' => 'SPP', 'description' => 'SPP Piano', 'amount' => 370000,
            'metadata' => ['package_id' => 123],
        ]);
    }

    public function test_admin_dapat_mencatat_item_lunas_tanpa_mengubah_keuangan(): void
    {
        $this->invoice->update(['status' => Invoice::STATUS_PAID, 'paid_amount' => 370000]);
        $before = $this->invoice->fresh()->getAttributes();
        $notes = "Termasuk buku latihan\nDiberikan saat pertemuan pertama";

        $this->actingAs($this->admin)->patch(route('invoice-items.notes.update', $this->item), ['notes' => $notes])
            ->assertRedirect(route('invoices.show', $this->invoice))
            ->assertSessionHas('success');

        $this->assertSame(['package_id' => 123, 'notes' => $notes], $this->item->fresh()->metadata);
        $this->assertSame($before, $this->invoice->fresh()->getAttributes());
        $this->assertSame(370000, $this->item->fresh()->amount);
        $audit = AuditLog::where('entity_type', 'InvoiceItem')->where('entity_id', $this->item->id)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(['notes' => null], $audit->old_values);
        $this->assertSame(['notes' => $notes], $audit->new_values);
    }

    public function test_owner_dapat_mengosongkan_catatan_tanpa_menghapus_metadata_lain(): void
    {
        $this->item->update(['metadata' => ['package_id' => 123, 'notes' => 'Buku menyusul']]);
        $owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($owner)->patch(route('invoice-items.notes.update', $this->item), ['notes' => ''])
            ->assertSessionHas('success');
        $this->assertSame(['package_id' => 123], $this->item->fresh()->metadata);
    }

    public function test_invoice_void_menolak_perubahan_catatan(): void
    {
        $this->invoice->update(['status' => Invoice::STATUS_VOID, 'voided_at' => now()]);
        $this->actingAs($this->admin)->patch(route('invoice-items.notes.update', $this->item), ['notes' => 'Tidak boleh'])
            ->assertForbidden();
        $this->assertSame(['package_id' => 123], $this->item->fresh()->metadata);
    }

    public function test_auditor_hanya_bisa_membaca_catatan(): void
    {
        $this->item->update(['metadata' => ['notes' => 'Buku sudah diserahkan']]);
        $auditor = User::factory()->create()->assignRole('Auditor');
        $this->actingAs($auditor)->patch(route('invoice-items.notes.update', $this->item), ['notes' => 'Diubah'])
            ->assertForbidden();
        $this->get(route('invoices.show', $this->invoice))->assertOk()->assertSeeText('Buku sudah diserahkan');
        $this->assertSame('Buku sudah diserahkan', $this->item->fresh()->metadata['notes']);
    }

    public function test_catatan_lebih_dari_500_karakter_ditolak(): void
    {
        $this->actingAs($this->admin)->patch(route('invoice-items.notes.update', $this->item), ['notes' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['notes'], null, 'itemNote');
        $this->assertSame(['package_id' => 123], $this->item->fresh()->metadata);
    }

    public function test_catatan_dicetak_sebagai_teks_bukan_html_aktif(): void
    {
        $this->item->update(['metadata' => ['notes' => '<script>alert("uji")</script> Buku menyusul']]);
        $this->actingAs($this->admin)->get(route('invoices.print', $this->invoice))
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("uji")</script>', false);
    }

    public function test_update_denda_harian_mempertahankan_catatan(): void
    {
        $fine = $this->invoice->items()->create([
            'item_code' => 'DENDA', 'description' => 'Denda 1 hari', 'amount' => 5000,
            'metadata' => ['days_late' => 1, 'notes' => 'Sudah dikonfirmasi wali'],
        ]);
        app(InvoiceService::class)->applyLateFinesForMonth(2026, 11, Carbon::parse('2026-11-12'));
        $this->assertSame(10000, $fine->fresh()->amount);
        $this->assertSame('Sudah dikonfirmasi wali', $fine->fresh()->metadata['notes'] ?? null);
        $this->assertSame(2, $fine->fresh()->metadata['days_late']);
    }
}
