<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['roles', 'teacher'])->orderBy('name');

        // Filter: search nama atau email
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter: role
        if ($role = $request->get('role')) {
            $query->role($role); // scope dari Spatie Permission
        }

        // Filter: status aktif/nonaktif
        if ($request->get('status') === 'aktif') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'nonaktif') {
            $query->where('is_active', false);
        }

        // Ambil satu kali semua user_id yang punya audit log (hindari N+1)
        $userIdsWithLogs = AuditLog::select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->flip();

        $users = $query->get()->map(function ($user) use ($userIdsWithLogs) {
            // Tandai apakah user bisa dihapus (tidak ada audit log)
            $user->can_delete = ! isset($userIdsWithLogs[$user->id]);
            return $user;
        });

        // Semua teacher aktif beserta user_id-nya — untuk dropdown modal
        $allTeachers = Teacher::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'user_id']);

        $totalAktif    = User::where('is_active', true)->count();
        $totalNonaktif = User::where('is_active', false)->count();

        return view('users.index', compact('users', 'allTeachers', 'totalAktif', 'totalNonaktif'));
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$request->role]);

        // Hubungkan ke Teacher jika role Guru
        if ($request->role === 'Guru' && $request->teacher_id) {
            Teacher::where('id', $request->teacher_id)->update(['user_id' => $user->id]);
        }

        AuditLog::record(
            AuditLog::ACTION_CREATE,
            $user,
            $user->name,
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $request->role],
        );

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil dibuat.");
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $oldRole   = $user->getRoleNames()->first();
        $oldValues = ['name' => $user->name, 'email' => $user->email, 'role' => $oldRole];

        // Jika role berubah dari Guru → lepas link Teacher lama
        if ($oldRole === 'Guru' && $request->role !== 'Guru') {
            Teacher::where('user_id', $user->id)->update(['user_id' => null]);
        }

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        $user->syncRoles([$request->role]);

        // Perbarui link Teacher jika masih Guru
        if ($request->role === 'Guru' && $request->teacher_id) {
            // Lepas teacher lama jika beda
            Teacher::where('user_id', $user->id)
                   ->whereNot('id', $request->teacher_id)
                   ->update(['user_id' => null]);
            Teacher::where('id', $request->teacher_id)->update(['user_id' => $user->id]);
        }

        AuditLog::record(
            AuditLog::ACTION_UPDATE,
            $user,
            $user->name,
            $oldValues,
            ['name' => $user->name, 'email' => $user->email, 'role' => $request->role],
        );

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil diperbarui.");
    }

    public function resetPassword(ResetPasswordRequest $request, User $user)
    {
        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::record(
            AuditLog::ACTION_UPDATE,
            $user,
            $user->name,
            null,
            ['password_reset' => true],
            'Reset password oleh Owner',
        );

        return redirect()->route('users.index')
            ->with('success', "Password {$user->name} berhasil direset.");
    }

    public function toggleActive(User $user)
    {
        // Tidak boleh mengubah status akun sendiri
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Tidak dapat mengubah status akun Anda sendiri.');
        }

        $wasActive = $user->is_active;
        $user->update(['is_active' => !$wasActive]);

        AuditLog::record(
            AuditLog::ACTION_UPDATE,
            $user,
            $user->name,
            ['is_active' => $wasActive],
            ['is_active' => !$wasActive],
        );

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil {$status}.");
    }

    public function destroy(User $user)
    {
        // Tidak boleh hapus akun sendiri
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        // Hanya boleh hapus yang sudah nonaktif
        if ($user->is_active) {
            return redirect()->route('users.index')
                ->with('error', "User harus dinonaktifkan terlebih dahulu sebelum dihapus.");
        }

        // Cek audit log — user dengan riwayat tidak bisa dihapus
        if (AuditLog::where('user_id', $user->id)->exists()) {
            return redirect()->route('users.index')
                ->with('error', "User {$user->name} memiliki riwayat aktivitas dan tidak dapat dihapus.");
        }

        // Lepas link Teacher jika Guru
        Teacher::where('user_id', $user->id)->update(['user_id' => null]);

        $userName  = $user->name;
        $userEmail = $user->email;
        $userRole  = $user->getRoleNames()->first();

        $user->delete();

        AuditLog::record(
            AuditLog::ACTION_DELETE,
            null,
            $userName,
            ['name' => $userName, 'email' => $userEmail, 'role' => $userRole],
            null,
        );

        return redirect()->route('users.index')
            ->with('success', "User {$userName} berhasil dihapus dari sistem.");
    }
}
