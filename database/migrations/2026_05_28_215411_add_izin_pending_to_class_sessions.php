<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar lengkap status SETELAH tambah IZIN_PENDING.
     * Dipakai untuk SQLite (recreate table) dan MySQL (MODIFY COLUMN).
     */
    private const STATUS_WITH_IZIN_PENDING = [
        'SCHEDULED', 'HADIR', 'HADIR_TERLAMBAT',
        'IZIN_RESCHEDULE', 'IZIN_PENDING', 'IZIN_VIDEO', 'HANGUS',
        'LIBUR', 'DIGANTI', 'CANCELLED',
    ];

    private const STATUS_WITHOUT_IZIN_PENDING = [
        'SCHEDULED', 'HADIR', 'HADIR_TERLAMBAT',
        'IZIN_RESCHEDULE', 'IZIN_VIDEO', 'HANGUS',
        'LIBUR', 'DIGANTI', 'CANCELLED',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite tidak bisa MODIFY COLUMN — harus recreate table.
            // Aman di testing (in-memory) karena tidak ada data produksi.
            $this->recreateSqliteTable(self::STATUS_WITH_IZIN_PENDING);
            return;
        }

        // MySQL tidak bisa ALTER ENUM kolom langsung — harus re-define enum.
        // Tambah 'IZIN_PENDING' di antara IZIN_RESCHEDULE dan IZIN_VIDEO.
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED',
            'HADIR',
            'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_PENDING',
            'IZIN_VIDEO',
            'HANGUS',
            'LIBUR',
            'DIGANTI',
            'CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->recreateSqliteTable(self::STATUS_WITHOUT_IZIN_PENDING);
            return;
        }

        // Hapus IZIN_PENDING dari enum (hati-hati: jika ada row IZIN_PENDING, down() akan error)
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED',
            'HADIR',
            'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_VIDEO',
            'HANGUS',
            'LIBUR',
            'DIGANTI',
            'CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }

    /**
     * Recreate class_sessions di SQLite dengan CHECK constraint baru.
     * SQLite tidak support ALTER COLUMN, jadi kita rename → create baru → copy data → drop lama.
     *
     * @param string[] $statuses  Daftar nilai enum yang valid.
     */
    private function recreateSqliteTable(array $statuses): void
    {
        $inList = implode("', '", $statuses);

        // Nonaktifkan FK sementara agar rename tidak error
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('ALTER TABLE class_sessions RENAME TO class_sessions_old');

        DB::statement("CREATE TABLE class_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NULL REFERENCES schedules(id) ON DELETE SET NULL,
            enrollment_id INTEGER NULL REFERENCES enrollments(id) ON DELETE SET NULL,
            student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE RESTRICT,
            teacher_id INTEGER NOT NULL REFERENCES teachers(id) ON DELETE RESTRICT,
            substitute_teacher_id INTEGER NULL REFERENCES teachers(id) ON DELETE SET NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            room_id INTEGER NULL REFERENCES rooms(id) ON DELETE SET NULL,
            status VARCHAR(255) NOT NULL DEFAULT 'SCHEDULED'
                CHECK(status IN ('{$inList}')),
            late_minutes SMALLINT UNSIGNED NULL,
            notes TEXT NULL,
            honor_code VARCHAR(20) NULL,
            honor_amount INTEGER NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )");

        DB::statement('INSERT INTO class_sessions SELECT * FROM class_sessions_old');
        DB::statement('DROP TABLE class_sessions_old');

        // Recreate indeks (tidak ter-copy saat rename)
        DB::statement('CREATE INDEX class_sessions_session_date_index ON class_sessions (session_date)');
        DB::statement('CREATE INDEX class_sessions_teacher_id_session_date_index ON class_sessions (teacher_id, session_date)');
        DB::statement('CREATE INDEX class_sessions_student_id_session_date_index ON class_sessions (student_id, session_date)');
        DB::statement('CREATE INDEX class_sessions_schedule_id_session_date_index ON class_sessions (schedule_id, session_date)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
