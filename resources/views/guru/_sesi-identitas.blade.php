{{-- Identitas sesi (Sesi ke-N · Bulan) — $sesi: ClassSession --}}
@php $identitasSesi = $sesi->getGuruSessionIdentity(); @endphp
@if($identitasSesi !== '—')
    <div class="text-[11px] text-mk-accent font-medium mt-0.5">{{ $identitasSesi }}</div>
@endif
