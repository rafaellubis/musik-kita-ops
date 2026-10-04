<x-app-layout>
    @php
        $canMutate = auth()->user()->hasAnyRole(['Owner', 'Admin']);
        $statusMeta = [
            'DRAFT' => ['Draft', 'bg-amber-50 text-amber-900 border-amber-200', 'bg-amber-500'],
            'PUBLISHED' => ['Dipublikasikan', 'bg-emerald-50 text-emerald-900 border-emerald-200', 'bg-emerald-600'],
            'COMPLETED' => ['Selesai', 'bg-mk-accentDim text-mk-text border-mk-border', 'bg-mk-accent'],
            'CANCELLED' => ['Dibatalkan', 'bg-red-50 text-red-900 border-red-200', 'bg-red-600'],
        ][$event->status] ?? [$event->status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
        $initialTab = in_array(old('event_ui_tab'), ['summary', 'participants', 'teacher-attendance', 'finance'], true)
            ? old('event_ui_tab')
            : 'summary';
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold text-mk-text">{{ $event->name }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusMeta[1] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusMeta[2] }}" aria-hidden="true"></span>
                        {{ $statusMeta[0] }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-mk-muted">
                    <span class="font-mono">{{ $event->event_number }}</span>
                    <span aria-hidden="true"> · </span>{{ $event->type_label }}
                    <span aria-hidden="true"> · </span>{{ $event->event_date->translatedFormat('d F Y') }}
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="text-sm font-semibold text-mk-muted hover:text-mk-text">Kembali</a>
        </div>
    </x-slot>

    <div class="px-4 py-6 lg:px-8" x-data="{ tab: '{{ $initialTab }}' }">
        <div class="overflow-x-auto border-b border-mk-border">
            <div class="flex min-w-max gap-1" role="tablist" aria-label="Detail event">
                @foreach([
                    'summary' => 'Ringkasan',
                    'participants' => 'Peserta',
                    'teacher-attendance' => 'Kehadiran Guru',
                    'finance' => 'Keuangan',
                ] as $tab => $label)
                    <button type="button" id="event-tab-{{ $tab }}" role="tab"
                            aria-selected="{{ $initialTab === $tab ? 'true' : 'false' }}"
                            :aria-selected="(tab === '{{ $tab }}').toString()"
                            aria-controls="event-panel-{{ $tab }}"
                            @click="tab = '{{ $tab }}'"
                            @keydown.right.prevent="const tabs = [...$el.parentElement.querySelectorAll('[role=tab]')]; tabs[(tabs.indexOf($el) + 1) % tabs.length].focus()"
                            @keydown.left.prevent="const tabs = [...$el.parentElement.querySelectorAll('[role=tab]')]; tabs[(tabs.indexOf($el) - 1 + tabs.length) % tabs.length].focus()"
                            class="rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-inset focus:ring-mk-accent"
                            :class="tab === '{{ $tab }}' ? 'border-mk-accent text-mk-text bg-mk-card' : 'border-transparent text-mk-muted hover:bg-mk-surface hover:text-mk-text'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <section id="event-panel-summary" role="tabpanel" aria-labelledby="event-tab-summary"
                 x-show="tab === 'summary'" class="pt-5">
            @include('events.partials.summary')
        </section>
        <section id="event-panel-participants" role="tabpanel" aria-labelledby="event-tab-participants"
                 x-show="tab === 'participants'" x-cloak class="pt-5">
            @include('events.partials.participants')
        </section>
        <section id="event-panel-teacher-attendance" role="tabpanel" aria-labelledby="event-tab-teacher-attendance"
                 x-show="tab === 'teacher-attendance'" x-cloak class="pt-5">
            @include('events.partials.teacher-attendance')
        </section>
        <section id="event-panel-finance" role="tabpanel" aria-labelledby="event-tab-finance"
                 x-show="tab === 'finance'" x-cloak class="pt-5">
            @include('events.partials.finance')
        </section>
    </div>
</x-app-layout>
