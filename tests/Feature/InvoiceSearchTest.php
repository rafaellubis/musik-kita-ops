<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_partial_nickname_without_bypassing_month_and_status_filters(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('Admin'));

        $student = Student::factory()->create([
            'full_name' => 'Aditya Pratama',
            'nickname' => 'Kiki',
        ]);
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 10,
            'status' => 'UNPAID',
        ]);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 9,
        ]);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 10,
            'status' => 'PAID',
        ]);
        Invoice::factory()->create(['year' => 2026, 'month' => 10]);

        $this->get(route('invoices.index', [
            'year' => 2026,
            'month' => 10,
            'status' => 'UNPAID',
            'search' => 'Kik',
        ]))->assertOk()->assertViewHas('invoices', function ($invoices) use ($invoice) {
            return $invoices->getCollection()->modelKeys() === [$invoice->id];
        });
    }
}
