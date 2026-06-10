<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Karyawan</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $employee->employee_code }} — {{ $employee->full_name }}</div>
            </div>
            <a href="{{ route('employees.index') }}" class="text-sm text-mk-muted hover:text-mk-text">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-3xl">
            <form action="{{ route('employees.update', $employee) }}" method="POST">
                @csrf
                @method('PUT')
                @include('employees._form', ['employee' => $employee, 'users' => $users])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('employees.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit" class="px-4 py-2 rounded text-sm font-bold btn-mk-primary">Simpan</button>
                </div>
            </form>

            @if(!$employee->payrollSlips()->exists())
            <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="mt-4"
                  onsubmit="return confirm('Hapus karyawan ini?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                    Hapus Karyawan
                </button>
            </form>
            @else
            <p class="text-xs text-mk-dim mt-4">Karyawan dengan historis slip tidak bisa dihapus — nonaktifkan saja.</p>
            @endif
        </div>
    </div>
</x-app-layout>
