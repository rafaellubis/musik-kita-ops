{{--
    Sidebar navigasi vertikal — dark navy.
    Di-include oleh layouts/app.blade.php di dalam <aside>.
--}}

{{-- ===== LOGO ===== --}}
<div class="px-4 py-3 border-b border-white/[0.06] shrink-0">
    <img src="{{ asset('images/logo-musikkita-dark-mode.PNG') }}"
         alt="Musik KITA"
         class="h-10 w-full object-contain object-left"
         style="max-width:160px">
</div>

{{-- ===== NAV ITEMS ===== --}}
<nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5 text-[13px]">

    {{-- Grup: UTAMA --}}
    <div class="px-2 pt-1 pb-1.5 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Utama</div>

    <x-sidebar-item route="dashboard" icon="🏠" label="Dashboard"
        :active="request()->routeIs('dashboard')" />
    <x-sidebar-item route="students.index" icon="🎓" label="Murid"
        :active="request()->routeIs('students.*')" />

    <x-sidebar-item route="sessions.index" icon="🎵" label="Sesi"
        :active="request()->routeIs('sessions.*')" />
    <x-sidebar-item route="kalender.index" icon="📅" label="Kalender"
        :active="request()->routeIs('kalender.*')" />
    @hasanyrole('Owner|Admin')
    <x-sidebar-item route="absensi.index" icon="✅" label="Absensi"
        :active="request()->routeIs('absensi.index') || request()->routeIs('absensi.update')" />
    <x-sidebar-item route="absensi.open-slots" icon="📋" label="Sesi Pending"
        :active="request()->routeIs('absensi.open-slots*')" />
    @endhasanyrole

    {{-- Grup: KEUANGAN --}}
    <div class="px-2 pt-3 pb-1 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Keuangan</div>

    <x-sidebar-item route="invoices.index" icon="💳" label="Tagihan"
        :active="request()->routeIs('invoices.*')" />
    @hasanyrole('Owner|Admin')
    {{-- Nonaktif untuk semua role: tampil abu-abu, tidak bisa diklik --}}
    <x-sidebar-item route="invoice-reminders.index" icon="💬" label="Reminder WA"
        :active="request()->routeIs('invoice-reminders.*')" 
        :disabled="true"
        title="Sementara dinonaktifkan"/>
    @endhasanyrole
    <x-sidebar-item route="honors.index" icon="💰" label="Slip Honor"
        :active="request()->routeIs('honors.*')" />
    <x-sidebar-item route="staff-payrolls.index" icon="💵" label="Gaji Staff"
        :active="request()->routeIs('staff-payrolls.*')" />
    <x-sidebar-item route="expenses.index" icon="📊" label="Pengeluaran"
        :active="request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*')" />
    <x-sidebar-item route="petty-cash.index" icon="💵" label="Petty Cash"
        :active="request()->routeIs('petty-cash.*')" />
    <x-sidebar-item route="events.index" icon="🎤" label="Event"
        :active="request()->routeIs('events.*') || request()->routeIs('event-participants.*')"
        :disabled="true"
        title="Sementara dinonaktifkan"/>

    {{-- Grup: LAPORAN --}}
    <div class="px-2 pt-3 pb-1 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Laporan</div>

    <x-sidebar-item route="progress-reports.index" icon="📝" label="Laporan Progres"
        :active="request()->routeIs('progress-reports.*')" />
    <x-sidebar-item route="session-report-wa-logs.index" icon="📲" label="Log Laporan Sesi WA"
        :active="request()->routeIs('session-report-wa-logs.*')" />

    @if(auth()->user()->hasRole('Owner'))
        <x-sidebar-item route="reports.finance" icon="📈" label="Laporan"
            :active="request()->routeIs('reports.*')" />
        <x-sidebar-item route="audit-logs.index" icon="🔍" label="Audit Log"
            :active="request()->routeIs('audit-logs.*')" />
    @else
        <x-sidebar-item route="reports.students" icon="📈" label="Laporan"
            :active="request()->routeIs('reports.*')" />
    @endif

    {{-- Grup: MASTER DATA --}}
    <div class="px-2 pt-3 pb-1 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Master Data</div>

    <x-sidebar-item route="teachers.index" icon="👨‍🏫" label="Guru"
        :active="request()->routeIs('teachers.*')" />
    <x-sidebar-item route="employees.index" icon="👥" label="Karyawan"
        :active="request()->routeIs('employees.*')" />
    <x-sidebar-item route="instruments.index" icon="🎸" label="Instrumen"
        :active="request()->routeIs('instruments.*')" />
    @role('Owner|Auditor')
    <x-sidebar-item route="packages.index" icon="📦" label="Packages Class"
        :active="request()->routeIs('packages.*')" />
        @endrole
    <x-sidebar-item route="rooms.index" icon="🚪" label="Rooms"
        :active="request()->routeIs('rooms.*')" />
    <x-sidebar-item route="holidays.index" icon="📅" label="Kalender Akademik"
        :active="request()->routeIs('holidays.*')" />
    @role('Owner')
    <x-sidebar-item route="report-templates.index" icon="📋" label="Template Laporan"
        :active="request()->routeIs('report-templates.*')"
        :disabled="true"
        title="Sementara dinonaktifkan"/>
    @endrole
    <x-sidebar-item route="whatsapp-templates.index" icon="💬" label="Template WA"
        :active="request()->routeIs('whatsapp-templates.*')" />
    @role('Owner|Auditor')
    <x-sidebar-item route="invoice-components.index" icon="🧾" label="Komponen Invoice"
        :active="request()->routeIs('invoice-components.*')" />
    <x-sidebar-item route="expense-categories.index" icon="📦" label="Kategori Pengeluaran"
        :active="request()->routeIs('expense-categories.*')" />
    <x-sidebar-item route="payroll-configs.index" icon="⚙️" label="Config Honor"
        :active="request()->routeIs('payroll-configs.*')" />
    @endrole
    @role('Owner')
    <x-sidebar-item route="users.index" icon="👤" label="Manage User"
        :active="request()->routeIs('users.*')" />
    @endrole

</nav>

{{-- ===== INFO USER (bawah sidebar) ===== --}}
<div class="shrink-0 px-4 py-3 border-t border-white/[0.06]">
    <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-mk-accentDim flex items-center justify-center text-sm font-bold text-mk-accent shrink-0">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold text-white/90 truncate">{{ auth()->user()->name }}</div>
            <div class="text-[10px] text-white/40 truncate">
                @foreach(auth()->user()->getRoleNames() as $role){{ $role }}@endforeach
            </div>
        </div>
        <a href="{{ route('profile.edit') }}"
           class="text-white/40 hover:text-white/60 transition-colors p-1 rounded"
           title="Edit profil">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </a>
    </div>
</div>