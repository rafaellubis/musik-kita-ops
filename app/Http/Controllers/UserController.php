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

        $users = $query->get()->map(function ($user) {
            // Tandai apakah user bisa dihapus (tidak ada audit log)
            $user->can_delete = !AuditLog::where('user_id', $user->id)->exists();
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
        abort(501); // TODO: implementasi Task 6
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }

    public function resetPassword(ResetPasswordRequest $request, User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }

    public function toggleActive(User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }

    public function destroy(User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }
}
