<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use App\Services\UserUsernameService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Command: guru:create-accounts
 *
 * Membuat akun User untuk semua guru aktif yang belum punya akun login.
 * Dijalankan satu kali saat pertama kali fitur login guru diaktifkan,
 * atau kapan pun ada guru baru yang perlu dibuatkan akun.
 *
 * Cara pakai:
 *   php artisan guru:create-accounts
 *
 * Output: tabel berisi nama, email login, dan password awal masing-masing guru.
 * PENTING: Simpan dan bagikan daftar tersebut ke guru setelah command selesai.
 */
class GuruCreateAccounts extends Command
{
    protected $signature   = 'guru:create-accounts';
    protected $description = 'Buat akun User untuk semua guru aktif yang belum punya akun login';

    public function handle(): int
    {
        // Ambil guru aktif yang belum punya user_id
        $teachers = Teacher::where('is_active', true)->whereNull('user_id')->get();

        if ($teachers->isEmpty()) {
            $this->info('Semua guru aktif sudah punya akun login.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($teachers as $teacher) {
            // Format nama untuk slug: lowercase, hapus spasi
            // Contoh: "T. HADI" → "t.hadi", "THOMAS" → "thomas"
            $namaSlug = Str::lower(str_replace(' ', '', $teacher->name));

            // Gunakan email guru jika ada, generate dummy jika tidak
            $email = $teacher->email ?? "{$namaSlug}@musikkita.local";

            // Hindari duplikat email dummy dengan tambahkan ID guru
            if (User::where('email', $email)->exists()) {
                $email = "{$namaSlug}.{$teacher->id}@musikkita.local";
            }

            // Password awal = nama guru lowercase tanpa spasi
            $password = $namaSlug;

            // Username unik — sama format dengan password awal, bisa diedit Owner nanti
            $username = UserUsernameService::generateUnique($namaSlug, $teacher->name);

            $user = User::create([
                'name'              => $teacher->name,
                'username'          => $username,
                'email'             => $email,
                'password'          => Hash::make($password),
                'email_verified_at' => now(), // wajib agar bisa login (middleware verified)
            ]);

            // Assign role Guru via Spatie Permission
            $user->assignRole('Guru');

            // Tautkan user ke teacher
            $teacher->update(['user_id' => $user->id]);

            $rows[] = [$teacher->name, $username, $email, $password];
        }

        $this->table(['Nama Guru', 'Username', 'Email Login', 'Password Awal'], $rows);
        $this->info(count($rows) . ' akun berhasil dibuat.');
        $this->warn('PENTING: Simpan daftar di atas dan bagikan ke masing-masing guru.');

        return self::SUCCESS;
    }
}
