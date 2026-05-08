<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder utama. Dipanggil oleh `php artisan db:seed` atau
     * `php artisan migrate:fresh --seed`.
     *
     * Urutan WAJIB:
     *   1. RoleSeeder      -> bikin role Owner/Admin/Auditor dulu
     *   2. Master Data     -> Instrument, Room, Holiday, dst (independen)
     *   3. Package         -> butuh Instrument
     *   4. Teacher         -> butuh Instrument (matriks)
     *   5. Student         -> butuh Package + Teacher
     *   6. User default    -> assign role Owner
     *
     * Pakai firstOrCreate di setiap seeder, jadi aman dijalankan ulang
     * tanpa duplikat data.
     */
    public function run(): void
    {
        $this->call([
            // 1. Role harus ada sebelum user di-assign role
            RoleSeeder::class,

            // 2. Master data tanpa dependensi
            InstrumentSeeder::class,
            RoomSeeder::class,
            HolidaySeeder::class,
            InvoiceComponentSeeder::class,
            PayrollConfigSeeder::class,
            ExpenseCategorySeeder::class, // M07: kategori pengeluaran

            // 3. Master data dengan dependensi
            PackageSeeder::class,   // butuh instruments
            TeacherSeeder::class,   // butuh instruments

            // 4. Data operasional
            StudentSeeder::class,   // butuh packages + teachers
        ]);

        // ============= User Owner Default =============
        // Akun login pertama untuk solo developer/owner studio.
        // Pakai firstOrCreate biar safe dijalankan ulang.
        $owner = User::firstOrCreate(
            ['email' => 'owner@musikkita.local'],
            [
                'name' => 'Owner Studio',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign role Owner kalau belum ada (idempotent)
        if (!$owner->hasRole('Owner')) {
            $owner->assignRole('Owner');
        }

        // ============= User Admin & Auditor Demo =============
        // Akun untuk testing role-based access. Hapus / ganti password
        // sebelum deploy production.
        $admin = User::firstOrCreate(
            ['email' => 'admin@musikkita.local'],
            [
                'name' => 'Admin Studio',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        if (!$admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }

        $auditor = User::firstOrCreate(
            ['email' => 'auditor@musikkita.local'],
            [
                'name' => 'Auditor Studio',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        if (!$auditor->hasRole('Auditor')) {
            $auditor->assignRole('Auditor');
        }
    }
}
