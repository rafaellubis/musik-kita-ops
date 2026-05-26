<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-mk-card shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-mk-card shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-mk-card shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

            @role('Owner')
            <div class="p-4 sm:p-8 bg-mk-card shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-mk-text">
                            Import Data Murid
                        </h2>
                        <p class="mt-1 text-sm text-mk-muted">
                            Import data murid dari file Excel (.xlsx). Fitur ini digunakan sekali saat migrasi data awal.
                            Hanya Owner yang dapat mengakses halaman ini.
                        </p>
                    </header>
                    <div class="mt-4">
                        <a href="{{ route('import.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition">
                            📥 Buka Halaman Import Excel
                        </a>
                    </div>
                </div>
            </div>
            @endrole
        </div>
    </div>
</x-app-layout>
