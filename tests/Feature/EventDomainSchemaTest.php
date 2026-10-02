<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\EventTeacherReassignment;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EventDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_domain_has_required_columns_and_relationships(): void
    {
        $this->assertTrue(Schema::hasColumns('events', [
            'event_number', 'name', 'type', 'event_date', 'payment_due_date', 'status', 'notes',
            'created_by', 'published_at', 'published_by', 'completed_at', 'completed_by',
            'cancelled_at', 'cancelled_by', 'cancellation_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('event_participants', [
            'event_id', 'student_id', 'enrollment_id', 'status', 'cancelled_at', 'cancelled_by',
            'cancellation_reason', 'active_teacher_assignment_id', 'invoice_id', 'invoice_item_id',
            'snapshot_package_id', 'snapshot_package_code', 'snapshot_instrument_name',
            'snapshot_class_type', 'snapshot_grade', 'snapshot_duration_min',
            'snapshot_package_price', 'snapshot_mc_fee', 'snapshot_honor_amount',
            'attendance_status', 'event_notes', 'attendance_updated_at', 'attendance_updated_by',
        ]));
        $this->assertTrue(Schema::hasColumns('event_teacher_assignments', [
            'event_id', 'teacher_id', 'attendance_status', 'confirmed_at', 'confirmed_by',
            'absence_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('event_teacher_reassignments', [
            'event_id', 'event_participant_id', 'from_teacher_id', 'to_teacher_id',
            'reason', 'changed_by', 'changed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('event_honor_entries', [
            'event_id', 'event_participant_id', 'teacher_id', 'snapshot_package_id',
            'snapshot_package_code', 'snapshot_package_price', 'honor_formula', 'amount',
            'honor_slip_id', 'status', 'posted_at', 'posted_by', 'reversed_at',
            'reversed_by', 'reversal_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('invoices', ['event_id']));

        $event = Event::factory()->published()->make();
        $this->assertTrue($event->isPublished());
        $this->assertSame(Event::STATUS_COMPLETED, Event::factory()->completed()->make()->status);
        $this->assertSame(Event::STATUS_CANCELLED, Event::factory()->cancelled()->make()->status);
        $this->assertSame(EventParticipant::STATUS_CANCELLED, EventParticipant::factory()->cancelled()->make()->status);
        $this->assertSame(EventTeacherAssignment::ATTENDANCE_HADIR, EventTeacherAssignment::factory()->hadir()->make()->attendance_status);
        $this->assertSame(EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR, EventTeacherAssignment::factory()->tidakHadir()->make()->attendance_status);
        $this->assertSame(EventHonorEntry::STATUS_POSTED, EventHonorEntry::factory()->posted()->make()->status);
        $this->assertSame(EventHonorEntry::STATUS_REVERSED, EventHonorEntry::factory()->reversed()->make()->status);

        $this->assertSame('event_id', (new Event())->invoices()->getForeignKeyName());
        $this->assertSame('active_teacher_assignment_id', (new EventParticipant())->activeTeacherAssignment()->getForeignKeyName());
        $this->assertSame('invoice_id', (new EventParticipant())->invoice()->getForeignKeyName());
        $this->assertSame('invoice_item_id', (new EventParticipant())->invoiceItem()->getForeignKeyName());
        $this->assertSame('event_id', (new Invoice())->event()->getForeignKeyName());
    }

    public function test_same_enrollment_cannot_be_active_twice_in_one_event(): void
    {
        $event = Event::factory()->create();
        $student = Student::factory()->create();
        $firstEnrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $secondEnrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'enrollment_id' => $firstEnrollment->id,
        ]);
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'enrollment_id' => $secondEnrollment->id,
        ]);

        $this->assertSame(2, $event->participants()->count());

        $this->expectException(QueryException::class);
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'enrollment_id' => $firstEnrollment->id,
        ]);
    }

    public function test_legacy_event_preflight_rejects_valid_payments_or_paid_event_honor(): void
    {
        $this->assertLegacyPreflightRejected(
            'payment event yang masih valid',
            fn () => DB::table('payments')->insert([
                'invoice_id' => 1,
                'voided_at' => null,
            ]),
        );

        $this->assertLegacyPreflightRejected(
            'slip honor event yang sudah PAID',
            fn () => DB::table('teacher_honor_slips')->insert([
                'status' => 'PAID',
                'event_honor' => 250000,
            ]),
        );
    }

    private function assertLegacyPreflightRejected(string $expectedMessage, callable $seedUnsafeData): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'musik-kita-event-');
        $originalConnection = DB::getDefaultConnection();

        config(['database.connections.legacy_event_test' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('legacy_event_test');
        DB::setDefaultConnection('legacy_event_test');

        try {
            $this->createLegacyEventSchema();
            $seedUnsafeData();

            $migration = require database_path('migrations/2026_10_02_000001_rebuild_event_domain.php');

            try {
                $migration->up();
                $this->fail('Preflight seharusnya menghentikan migrasi sebelum schema lama diubah.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($expectedMessage, $exception->getMessage());
            }

            $this->assertTrue(Schema::hasColumn('events', 'legacy_marker'));
            $this->assertFalse(Schema::hasColumn('events', 'payment_due_date'));
        } finally {
            DB::disconnect('legacy_event_test');
            DB::setDefaultConnection($originalConnection);
            DB::purge('legacy_event_test');
            @unlink($databasePath);
        }
    }

    private function createLegacyEventSchema(): void
    {
        Schema::create('events', function ($table) {
            $table->id();
            $table->date('event_date');
            $table->string('legacy_marker')->nullable();
        });
        Schema::create('invoices', function ($table) {
            $table->id();
        });
        Schema::create('invoice_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->integer('amount')->default(0);
        });
        Schema::create('event_participants', function ($table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('invoice_item_id')->nullable();
        });
        Schema::create('payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->dateTime('voided_at')->nullable();
        });
        Schema::create('teacher_honor_slips', function ($table) {
            $table->id();
            $table->string('status');
            $table->unsignedInteger('event_honor')->default(0);
        });

        DB::table('events')->insert(['id' => 1, 'event_date' => '2026-06-15']);
        DB::table('invoices')->insert(['id' => 1]);
        DB::table('invoice_items')->insert(['id' => 1, 'invoice_id' => 1, 'amount' => 295000]);
        DB::table('event_participants')->insert([
            'event_id' => 1,
            'invoice_id' => 1,
            'invoice_item_id' => 1,
        ]);
    }
}
