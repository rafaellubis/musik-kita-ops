{{--
    M-Users: Halaman manajemen user (Owner only)
    Fitur: List tabel + 5 modal Alpine.js (Tambah, Edit, Reset PW, Nonaktifkan, Hapus)
--}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Manajemen User</h2>
            <div class="text-xs text-mk-muted mt-0.5">Kelola akun login Owner, Admin, Auditor, dan Guru</div>
        </div>
    </x-slot>

    {{-- Alpine: state semua modal di-scope ke div utama --}}
    <div class="py-6 px-4 lg:px-8"
         x-data="{
            modal: null,
            editUser: {},
            resetUser: {},
            deleteUser: {},
            deactivateUser: {},
            selectedRole: '',
            allTeachers: {{ Js::from($allTeachers) }},

            openCreate() {
                this.editUser = {};
                this.selectedRole = '';
                this.modal = 'create';
            },
            openEdit(user) {
                this.editUser = user;
                this.selectedRole = user.role;
                this.modal = 'edit';
            },
            openReset(user) {
                this.resetUser = user;
                this.modal = 'reset';
            },
            openDeactivate(user) {
                this.deactivateUser = user;
                this.modal = 'deactivate';
            },
            openDelete(user) {
                this.deleteUser = user;
                this.modal = 'delete';
            },
            closeModal() {
                this.modal = null;
            },

            get availableTeachers() {
                return this.allTeachers.filter(t => t.user_id === null);
            },
            availableTeachersForEdit(userId) {
                return this.allTeachers.filter(t => t.user_id === null || t.user_id === userId);
            },
         }">

        {{-- Tombol tambah — harus di dalam x-data agar openCreate() tersedia --}}
        <div class="flex justify-end mb-4">
            <button type="button" @click="openCreate()"
                    class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                + Tambah User
            </button>
        </div>

        {{-- Flash messages --}}
        {{-- Filter bar --}}
        <form method="GET" action="{{ route('users.index') }}"
              class="mb-5 flex flex-wrap gap-3 items-center">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Cari nama, username, atau email..."
                   class="bg-white border border-gray-200 text-gray-900 text-sm rounded-lg px-3 py-2 w-56">

            <select name="role" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Role</option>
                @foreach(['Owner','Admin','Auditor','Guru'] as $r)
                    <option value="{{ $r }}" @selected(request('role') === $r)>{{ $r }}</option>
                @endforeach
            </select>

            <select name="status" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Status</option>
                <option value="aktif"    @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>

            <button type="submit"
                    class="px-3 py-2 text-sm rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                Filter
            </button>
            @if(request()->hasAny(['search','role','status']))
            <a href="{{ route('users.index') }}"
               class="text-xs text-mk-muted hover:text-mk-text">× Reset</a>
            @endif
        </form>

        {{-- Tabel --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Username</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Info</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    @php
                        $role    = $user->getRoleNames()->first() ?? '—';
                        $isSelf  = $user->id === auth()->id();
                        $isActive = $user->is_active;
                        $userData = [
                            'id'         => $user->id,
                            'name'       => $user->name,
                            'username'   => $user->username,
                            'email'      => $user->email,
                            'role'       => $role,
                            'teacher_id' => $user->teacher?->id,
                            'teacher'    => $user->teacher ? ['id' => $user->teacher->id, 'name' => $user->teacher->name] : null,
                            'is_active'  => $isActive,
                        ];
                        $roleBadge = match($role) {
                            'Owner'   => 'background:rgba(123,94,167,0.18);color:#B09AD8',
                            'Admin'   => 'background:rgba(58,97,134,0.18);color:#7AAAC8',
                            'Auditor' => 'background:rgba(181,101,29,0.18);color:#D4853A',
                            'Guru'    => 'background:rgba(58,125,68,0.18);color:#6BC07A',
                            default   => 'background:rgba(100,100,100,0.18);color:#888',
                        };
                    @endphp
                    <tr class="{{ !$isActive ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold shrink-0"
                                     style="{{ $roleBadge }}">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                    @if($isSelf)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded"
                                          style="background:rgba(212,168,83,0.15);color:#D4A853">Anda</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $user->username ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="{{ $roleBadge }}">{{ $role }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            @if($user->teacher)
                                👨‍🏫 {{ $user->teacher->name }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($isActive)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background:rgba(58,125,68,0.14);color:#16a34a">Aktif</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background:rgba(176,58,46,0.14);color:#dc2626">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($isSelf)
                                <span class="text-xs text-gray-400 italic">Akun Anda sendiri</span>
                            @elseif($isActive)
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="openEdit({{ Js::from($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(212,168,83,0.12);color:#92400e;border-color:rgba(212,168,83,0.3)">
                                        ✏️ Edit
                                    </button>
                                    <button @click="openReset({{ Js::from($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(58,97,134,0.12);color:#1d4ed8;border-color:rgba(58,97,134,0.3)">
                                        🔑 Reset PW
                                    </button>
                                    <button @click="openDeactivate({{ Js::from($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(176,58,46,0.12);color:#dc2626;border-color:rgba(176,58,46,0.3)">
                                        ⛔ Nonaktif
                                    </button>
                                </div>
                            @else
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                                style="background:rgba(58,125,68,0.12);color:#16a34a;border-color:rgba(58,125,68,0.3)">
                                            ✅ Aktifkan
                                        </button>
                                    </form>
                                    @if($user->can_delete)
                                    <button @click="openDelete({{ Js::from($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(176,58,46,0.12);color:#dc2626;border-color:rgba(176,58,46,0.3)">
                                        🗑️ Hapus
                                    </button>
                                    @else
                                    <span class="text-xs text-gray-400" title="User ini memiliki riwayat aktivitas">
                                        🔒 Tidak bisa dihapus
                                    </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">
                            Tidak ada user yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-gray-100 flex gap-4 text-xs text-gray-500">
                <span>Total: <strong class="text-gray-800">{{ $users->count() }}</strong></span>
                <span>Aktif: <strong style="color:#16a34a">{{ $totalAktif }}</strong></span>
                <span>Nonaktif: <strong style="color:#dc2626">{{ $totalNonaktif }}</strong></span>
            </div>
        </div>

        {{-- ===== MODAL 1: TAMBAH USER ===== --}}
        <div x-show="modal === 'create'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Tambah User Baru</h3>
                <p class="text-sm text-gray-500 mb-5">Isi semua field yang diperlukan</p>

                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    @include('users._form_fields', ['mode' => 'create'])
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold rounded-lg btn-mk-primary">
                            Simpan User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 2: EDIT USER ===== --}}
        <div x-show="modal === 'edit'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Edit User</h3>
                <p class="text-sm text-gray-500 mb-5" x-text="'Mengubah akun: ' + editUser.name"></p>

                <form method="POST" x-bind:action="`{{ url('/users') }}/${editUser.id}`">
                    @csrf
                    @method('PUT')
                    @include('users._form_fields', ['mode' => 'edit'])
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold rounded-lg btn-mk-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 3: RESET PASSWORD ===== --}}
        <div x-show="modal === 'reset'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Reset Password</h3>
                <p class="text-sm text-gray-500 mb-5">
                    untuk: <strong x-text="resetUser.name"></strong>
                </p>
                <form method="POST" x-bind:action="`{{ url('/users') }}/${resetUser.id}/reset-password`">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Password Baru <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required minlength="8"
                               placeholder="Min. 8 karakter"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password_confirmation" required
                               placeholder="Ulangi password baru"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#1d4ed8">
                            🔑 Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 4: KONFIRMASI NONAKTIFKAN ===== --}}
        <div x-show="modal === 'deactivate'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">⛔ Nonaktifkan User</h3>
                <p class="text-sm text-gray-600 mb-2">User ini tidak akan bisa login setelah dinonaktifkan:</p>
                <div class="bg-gray-50 rounded-lg p-3 mb-5">
                    <div class="font-medium text-gray-800 text-sm" x-text="deactivateUser.name"></div>
                    <div class="text-xs text-gray-500" x-text="deactivateUser.email + ' · ' + deactivateUser.role"></div>
                </div>
                <form method="POST" x-bind:action="`{{ url('/users') }}/${deactivateUser.id}/toggle-active`">
                    @csrf
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#dc2626">
                            Nonaktifkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 5: KONFIRMASI HAPUS ===== --}}
        <div x-show="modal === 'delete'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6"
                 style="border:1px solid rgba(176,58,46,0.3)">
                <h3 class="text-lg font-semibold mb-3" style="color:#dc2626">⚠️ Hapus User Permanen</h3>
                <p class="text-sm text-gray-600 mb-2">Anda akan menghapus akun berikut:</p>
                <div class="rounded-lg p-3 mb-3" style="background:rgba(176,58,46,0.06)">
                    <div class="font-medium text-gray-800 text-sm" x-text="deleteUser.name"></div>
                    <div class="text-xs text-gray-500" x-text="deleteUser.email + ' · ' + deleteUser.role"></div>
                </div>
                <div class="rounded-lg p-3 mb-5 text-xs text-gray-600"
                     style="background:rgba(212,168,83,0.08)">
                    ✅ User ini tidak memiliki audit log — aman untuk dihapus permanen.
                </div>
                <form method="POST" x-bind:action="`{{ url('/users') }}/${deleteUser.id}`">
                    @csrf
                    @method('DELETE')
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#dc2626">
                            🗑️ Hapus Permanen
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>{{-- end x-data --}}
</x-app-layout>
