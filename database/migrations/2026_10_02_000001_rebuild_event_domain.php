<?php

use App\Models\HonorSlip;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLegacyDataCanBeRebuilt();
        $this->cleanLegacyEventInvoices();

        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('events');

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_number', 30)->unique();
            $table->string('name', 100);
            $table->enum('type', ['MINI_CONCERT', 'UJIAN_GRADE'])->default('MINI_CONCERT');
            $table->date('event_date');
            $table->date('payment_due_date');
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'event_date']);
        });

        Schema::create('event_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->enum('attendance_status', ['PENDING', 'HADIR', 'TIDAK_HADIR'])->default('PENDING');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('absence_reason')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'teacher_id']);
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->enum('status', ['ACTIVE', 'CANCELLED'])->default('ACTIVE');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('active_teacher_assignment_id')
                ->nullable()
                ->constrained('event_teacher_assignments')
                ->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->unsignedBigInteger('snapshot_package_id')->nullable();
            $table->string('snapshot_package_code', 50);
            $table->string('snapshot_instrument_name', 100);
            $table->string('snapshot_class_type', 30);
            $table->string('snapshot_grade', 30)->nullable();
            $table->unsignedSmallInteger('snapshot_duration_min');
            $table->unsignedInteger('snapshot_package_price');
            $table->unsignedInteger('snapshot_mc_fee')->default(0);
            $table->unsignedInteger('snapshot_honor_amount');
            $table->enum('attendance_status', ['PENDING', 'HADIR', 'TIDAK_HADIR'])->default('PENDING');
            $table->text('event_notes')->nullable();
            $table->timestamp('attendance_updated_at')->nullable();
            $table->foreignId('attendance_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('snapshot_package_id')->references('id')->on('packages')->nullOnDelete();
            $table->unique(['event_id', 'enrollment_id']);
            $table->index(['event_id', 'student_id', 'status']);
        });

        Schema::create('event_teacher_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->foreignId('from_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('to_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('event_honor_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->unsignedBigInteger('snapshot_package_id')->nullable();
            $table->string('snapshot_package_code', 50);
            $table->unsignedInteger('snapshot_package_price');
            $table->string('honor_formula', 255);
            $table->unsignedInteger('amount');
            $table->foreignId('honor_slip_id')->nullable()->constrained('teacher_honor_slips')->restrictOnDelete();
            $table->enum('status', ['POSTED', 'REVERSED'])->default('POSTED');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign('snapshot_package_id')->references('id')->on('packages')->nullOnDelete();
            $table->unique('event_participant_id');
            $table->index(['teacher_id', 'status']);
            $table->index('honor_slip_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('enrollment_id')->constrained('events')->restrictOnDelete();
            $table->index(['event_id', 'student_id', 'status'], 'invoices_event_student_status_index');
        });
    }

    /** Migrasi berhenti sebelum data lama diubah bila masih ada transaksi event aktif. */
    public function assertLegacyDataCanBeRebuilt(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('event_participants')) {
            return;
        }

        $invoiceIds = DB::table('event_participants')
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->unique()
            ->values();

        if ($invoiceIds->isNotEmpty() && Schema::hasTable('payments')) {
            $hasValidPayment = DB::table('payments')
                ->whereIn('invoice_id', $invoiceIds)
                ->whereNull('voided_at')
                ->exists();

            if ($hasValidPayment) {
                throw new RuntimeException('Migrasi event dihentikan: ditemukan payment event yang masih valid. Void pembayaran terlebih dahulu.');
            }
        }

        if ($invoiceIds->isNotEmpty() && Schema::hasColumn('invoices', 'status')) {
            $hasPaidInvoice = DB::table('invoices')
                ->whereIn('id', $invoiceIds)
                ->whereIn('status', [Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                ->exists();

            if ($hasPaidInvoice) {
                throw new RuntimeException('Migrasi event dihentikan: ditemukan invoice event dengan status pembayaran aktif.');
            }
        }

        $hasPaidEventHonor = DB::table('events')->exists()
            && Schema::hasTable('teacher_honor_slips')
            && Schema::hasColumn('teacher_honor_slips', 'event_honor')
            && DB::table('teacher_honor_slips')
                ->where('status', HonorSlip::STATUS_PAID)
                ->where('event_honor', '>', 0)
                ->exists();

        if ($hasPaidEventHonor) {
            throw new RuntimeException('Migrasi event dihentikan: ditemukan slip honor event yang sudah PAID.');
        }
    }

    private function cleanLegacyEventInvoices(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        $participants = DB::table('event_participants')->get(['invoice_id', 'invoice_item_id']);
        $invoiceItemIds = $participants->pluck('invoice_item_id')->filter()->unique()->values();
        $invoiceIds = $participants->pluck('invoice_id')->filter()->unique()->values();

        if ($invoiceItemIds->isNotEmpty()) {
            if (Schema::hasColumn('invoice_items', 'parent_item_id')) {
                DB::table('invoice_items')->whereIn('parent_item_id', $invoiceItemIds)->delete();
            }
            DB::table('invoice_items')->whereIn('id', $invoiceItemIds)->delete();
        }

        foreach ($invoiceIds as $invoiceId) {
            $remainingTotal = (int) DB::table('invoice_items')->where('invoice_id', $invoiceId)->sum('amount');

            if ($remainingTotal > 0) {
                DB::table('invoices')->where('id', $invoiceId)->update([
                    'total_amount' => $remainingTotal,
                    'paid_amount' => 0,
                    'status' => Invoice::STATUS_UNPAID,
                ]);
                continue;
            }

            $hasPaymentHistory = Schema::hasTable('payments')
                && DB::table('payments')->where('invoice_id', $invoiceId)->exists();

            if ($hasPaymentHistory) {
                $update = ['total_amount' => 0, 'paid_amount' => 0, 'status' => Invoice::STATUS_VOID];
                if (Schema::hasColumn('invoices', 'voided_at')) {
                    $update['voided_at'] = now();
                    $update['voided_reason'] = 'Migrasi domain event baru';
                }
                DB::table('invoices')->where('id', $invoiceId)->update($update);
            } else {
                DB::table('invoices')->where('id', $invoiceId)->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_event_student_status_index');
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });

        Schema::dropIfExists('event_honor_entries');
        Schema::dropIfExists('event_teacher_reassignments');
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('event_teacher_assignments');
        Schema::dropIfExists('events');

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_number', 30)->unique();
            $table->string('name', 100);
            $table->enum('type', ['MINI_CONCERT', 'UJIAN', 'MINI_CONCERT_UJIAN'])->default('MINI_CONCERT');
            $table->date('event_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['DRAFT', 'COMPLETED'])->default('DRAFT');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('accompanying_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('participation_type', ['UJIAN_TAMPIL', 'TAMPIL_SAJA'])->default('TAMPIL_SAJA');
            $table->integer('fee_amount')->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->enum('exam_result', ['LULUS', 'TIDAK_LULUS'])->nullable();
            $table->string('grade_before', 20)->nullable();
            $table->string('grade_after', 20)->nullable();
            $table->text('exam_notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'student_id']);
        });
    }
};
