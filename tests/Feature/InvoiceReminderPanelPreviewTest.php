<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsInvoiceReminderPanel;
use Tests\TestCase;

class InvoiceReminderPanelPreviewTest extends TestCase
{
    use RefreshDatabase, BuildsInvoiceReminderPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
    }

    public function test_default_preview_does_not_include_sibling_invoice(): void
    {
        $data = $this->panelPreview();
        $this->assertSame([$this->source->id], array_column($data['selected_invoices'], 'id'));
        $this->assertSame(340000, $data['total_balance']);
        $this->assertStringNotContainsString($this->sibling->invoice_number, $data['message_body']);
        $this->assertSame('6281234567890', $data['recipient']['phone']);
        $this->assertNotNull($data['preview_token']);
        $this->assertSame(now()->addMinutes(30)->toIso8601String(), $data['expires_at']);
        Http::assertNothingSent();
    }

    public function test_explicit_selection_is_ordered_and_sums_balances(): void
    {
        $data = $this->panelPreview(['invoice_ids' => [$this->sibling->id, $this->source->id]]);
        $this->assertSame([$this->source->id, $this->sibling->id], array_column($data['selected_invoices'], 'id'));
        $this->assertSame(730000, $data['total_balance']);
        $this->assertStringContainsString($this->sibling->invoice_number, $data['message_body']);
    }

    public function test_untrusted_selection_cannot_include_other_students_or_ineligible_invoices(): void
    {
        $other = $this->panelInvoice(Student::factory()->create(), 450000);
        foreach ([[$this->source->id, $other->id], [$this->source->id, $this->source->id], [$this->sibling->id], []] as $ids) {
            $this->actingAs($this->owner)->postJson(route('invoices.reminder.preview', $this->source), ['invoice_ids' => $ids])->assertUnprocessable();
        }
        $this->sibling->update(['status' => 'PAID', 'paid_amount' => 390000]);
        $this->actingAs($this->owner)->postJson(route('invoices.reminder.preview', $this->source), ['invoice_ids' => [$this->source->id, $this->sibling->id]])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_student_fallback_and_blockers_prevent_unsafe_send(): void
    {
        $this->student->update(['parent_phone' => 'invalid', 'phone' => '081111222333']);
        $data = $this->panelPreview();
        $this->assertSame('student', $data['recipient']['kind']);
        $this->assertStringStartsWith('Halo '.$this->student->full_name, $data['message_body']);
        $this->student->update(['phone' => null]);
        $data = $this->panelPreview();
        $this->assertFalse($data['can_send']);
        $this->assertNull($data['preview_token']);
        $this->student->update(['parent_phone' => '081234567890']);
        WhatsappMessageTemplate::query()->update(['is_active' => false]);
        $this->assertFalse($this->panelPreview()['can_send']);
        WhatsappMessageTemplate::query()->update(['is_active' => true, 'body' => str_repeat('x', 5001)]);
        $this->assertFalse($this->panelPreview()['can_send']);
        Http::assertNothingSent();
    }

    public function test_paid_void_and_zero_balance_source_can_only_be_reviewed(): void
    {
        foreach (['PAID', 'VOID', 'UNPAID'] as $status) {
            $this->source->update(['status' => $status, 'paid_amount' => 340000]);
            $data = $this->panelPreview();
            $this->assertFalse($data['can_send']);
            $this->assertNull($data['preview_token']);
        }
    }

    public function test_repeat_warning_uses_success_even_when_latest_attempt_failed(): void
    {
        $this->panelLog([$this->source->id]);
        $this->panelLog([$this->source->id], 'FAILED');
        $this->assertSame([$this->source->id], array_column($this->panelPreview()['repeat_warnings'], 'invoice_id'));
    }

    public function test_only_owner_and_admin_can_preview(): void
    {
        foreach (['Admin' => 200, 'Auditor' => 403, 'Guru' => 403] as $role => $status) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->postJson(route('invoices.reminder.preview', $this->source), [])->assertStatus($status);
        }
    }
}
