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

    public function test_legacy_cleanup_preserves_unrelated_items_when_remaining_total_is_zero(): void
    {
        $this->withLegacyEventDatabase(function (): void {
            $this->createLegacyEventSchema();

            DB::table('invoice_items')->insert([
                ['id' => 2, 'invoice_id' => 1, 'parent_item_id' => null, 'amount' => 100000],
                ['id' => 3, 'invoice_id' => 1, 'parent_item_id' => 2, 'amount' => -100000],
            ]);

            $migration = require database_path('migrations/2026_10_02_000001_rebuild_event_domain.php');
            $migration->up();

            $this->assertDatabaseHas('invoices', [
                'id' => 1,
                'total_amount' => 0,
                'status' => Invoice::STATUS_UNPAID,
            ]);
            $this->assertSame([2, 3], DB::table('invoice_items')->orderBy('id')->pluck('id')->all());
        });
    }

    public function test_legacy_cleanup_preserves_void_invoice_state_and_metadata(): void
    {
        $this->withLegacyEventDatabase(function (): void {
            $this->createLegacyEventSchema();

            DB::table('invoices')->insert([
                'id' => 2,
                'student_id' => 1,
                'total_amount' => 415000,
                'paid_amount' => 0,
                'status' => Invoice::STATUS_VOID,
                'voided_at' => '2026-06-20 10:00:00',
                'voided_by' => 1,
                'voided_reason' => 'Dibatalkan sebelum migrasi',
            ]);
            DB::table('invoice_items')->insert([
                ['id' => 4, 'invoice_id' => 2, 'amount' => 295000],
                ['id' => 5, 'invoice_id' => 2, 'amount' => 120000],
            ]);
            DB::table('event_participants')->insert([
                'event_id' => 1,
                'invoice_id' => 2,
                'invoice_item_id' => 4,
            ]);

            $migration = require database_path('migrations/2026_10_02_000001_rebuild_event_domain.php');
            $migration->up();

            $this->assertDatabaseHas('invoices', [
                'id' => 2,
                'total_amount' => 120000,
                'status' => Invoice::STATUS_VOID,
                'voided_at' => '2026-06-20 10:00:00',
                'voided_by' => 1,
                'voided_reason' => 'Dibatalkan sebelum migrasi',
            ]);
        });
    }

    public function test_factories_create_ownership_consistent_graphs(): void
    {
        $participant = EventParticipant::factory()->create();
        $honorEntry = EventHonorEntry::factory()->create();

        $this->assertSame($participant->enrollment->student_id, $participant->student_id);
        $this->assertSame($honorEntry->participant->event_id, $honorEntry->event_id);
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
        foreach (['users', 'teachers', 'students', 'enrollments', 'packages'] as $tableName) {
            Schema::create($tableName, function ($table) {
                $table->id();
            });
            DB::table($tableName)->insert(['id' => 1]);
        }

        Schema::create('events', function ($table) {
            $table->id();
            $table->date('event_date');
            $table->string('legacy_marker')->nullable();
        });
        Schema::create('invoices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->integer('total_amount')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->string('status')->default(Invoice::STATUS_UNPAID);
            $table->dateTime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('voided_reason')->nullable();
        });
        Schema::create('invoice_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->integer('amount')->default(0);

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
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
        DB::table('invoices')->insert(['id' => 1, 'student_id' => 1, 'total_amount' => 295000]);
        DB::table('invoice_items')->insert(['id' => 1, 'invoice_id' => 1, 'amount' => 295000]);
        DB::table('event_participants')->insert([
            'event_id' => 1,
            'invoice_id' => 1,
            'invoice_item_id' => 1,
        ]);
    }

    private function withLegacyEventDatabase(callable $callback): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'musik-kita-event-cleanup-');
        $originalConnection = DB::getDefaultConnection();

        config(['database.connections.legacy_event_cleanup_test' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('legacy_event_cleanup_test');
        DB::setDefaultConnection('legacy_event_cleanup_test');

        try {
            $callback();
        } finally {
            DB::disconnect('legacy_event_cleanup_test');
            DB::setDefaultConnection($originalConnection);
            DB::purge('legacy_event_cleanup_test');
            @unlink($databasePath);
        }
    }
}
