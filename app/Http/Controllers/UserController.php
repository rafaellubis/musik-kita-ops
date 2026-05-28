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
        abort(501); // TODO: implementasi Task 5
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
