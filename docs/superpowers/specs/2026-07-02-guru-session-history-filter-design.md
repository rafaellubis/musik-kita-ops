# Spesifikasi Desain: Filter Riwayat Sesi Guru (Portal Guru)
**Tanggal:** 2026-07-02  
**Status:** DRAFT (Menunggu Review)  
**Modul Asal:** M10 - Portal Guru  

---

## 1. Latar Belakang & Kebutuhan
Saat ini, guru dapat melihat riwayat sesi seorang murid melalui popup modal asinkronus (Drawer) saat mengajar di kelas. Namun, riwayat ini terbatas hanya menampilkan sesi pada **bulan berjalan** (bulan atribusi dari sesi aktif tersebut). 

Guru membutuhkan kemampuan untuk melihat riwayat sesi murid pada bulan-bulan sebelumnya untuk memantau kemajuan belajar murid secara lebih komprehensif.

Sesuai hasil diskusi, filter riwayat akan dibatasi pada **3 bulan terakhir** (bulan sesi aktif + 2 bulan sebelumnya) demi menjaga performa query database dan kesederhanaan navigasi antarmuka guru.

---

## 2. Prinsip Implementasi

### 🎨 UI/UX Premium (`impeccable`)
1. **Antarmuka Tab Horizontal**: Dibanding menggunakan dropdown `<select>`, kita menggunakan tab horizontal modern di dalam drawer riwayat sesi. Tab ini ramah sentuhan (*touch-friendly*) di perangkat mobile.
2. **Kontras & Spacing**: Warna teks tab aktif menggunakan warna gelap ber-kontras tinggi (Ink/Primary) dan tab tidak aktif menggunakan warna sekunder yang redup namun tetap memenuhi rasio kontras WCAG ≥ 4.5:1.
3. **Pemuatan Asinkronus dengan Skeleton**: Setiap perpindahan tab akan memicu *loading state* (skeleton animation yang sudah ada di layout) untuk mencegah *layout shift* yang mengganggu mata.
4. **Batasan Radius & Shadow**: Menggunakan standard style Tailwind CSS `rounded-xl` (12px) untuk elemen tab container dan card histori, menghindari over-rounding.

### ⚡ Kode Minimalis & YAGNI (`ponytail`)
1. **Reuse Endpoint**: Memanfaatkan endpoint route `/guru/sesi/{classSession}/history` yang sudah ada dengan menambahkan query parameter opsional (`?month=MM&year=YYYY`).
2. **Conditional Query**: Menggunakan method query builder Laravel `when()` untuk menyederhanakan kondisional filter bulan/tahun di Controller tanpa merusak logika default.
3. **Alpine.js Built-in**: State manajemen tab dan data fetching ditangani langsung menggunakan Alpine.js yang sudah terintegrasi pada file layout portal guru.

---

## 3. Rencana Perubahan Detail

### A. Backend: Controller & Route
* **File Terkait**: [GuruController.php](file:///C:/laragon/www/musik-kita-ops/app/Http/Controllers/GuruController.php)
* **Perubahan**:
  - Ambil parameter `month` dan `year` dari request.
  - Jika parameter tidak disediakan, gunakan `attribution_month` dan `attribution_year` dari `$classSession` (menjaga kompatibilitas pemanggilan awal).
  - Gunakan `when()` untuk menyaring data `ClassSession`.
  - Tandai simplifikasi query ini dengan komentar `// ponytail:`.

```php
// app/Http/Controllers/GuruController.php

public function sessionHistory(ClassSession $classSession)
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403);

    // Verifikasi hak akses (hanya guru utama atau guru pengganti)
    abort_if(
        (int) $classSession->teacher_id !== (int) $teacher->id && 
        (int) $classSession->substitute_teacher_id !== (int) $teacher->id, 
        403, 
        'Bukan sesi Anda.'
    );

    // ponytail: reuse request parameters dengan default fallback
    $month = request('month', $classSession->attribution_month);
    $year = request('year', $classSession->attribution_year);

    $history = ClassSession::where('enrollment_id', $classSession->enrollment_id)
        ->where('id', '!=', $classSession->id)
        ->where('attribution_month', $month)
        ->where('attribution_year', $year)
        ->whereIn('status', ['HADIR', 'HADIR_TERLAMBAT'])
        ->with('teacherNote')
        ->orderByDesc('session_date')
        ->orderByDesc('start_time')
        ->get();

    return view('guru._sesi-history-timeline', compact('history', 'classSession'));
}
```

### B. Frontend: View Layout Guru
* **File Terkait**: [layouts/guru.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/layouts/guru.blade.php)
* **Perubahan**:
  - Di dalam HTML modal (`Global Session History Drawer/Modal`), tambahkan navigasi tab horizontal di atas area konten.
  - Opsi 3 bulan di-render secara dinamis di sisi server menggunakan Carbon saat pertama kali modal dirender (menggunakan data dari event `open-session-history`).
  - Untuk memfasilitasi tab dinamis berdasarkan tanggal sesi, event `open-session-history` yang di-dispatch oleh tombol "Riwayat" di [_sesi-absensi-actions.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/guru/_sesi-absensi-actions.blade.php) perlu diperluas dengan mengirimkan informasi `sessionDate` atau `month` & `year` awal.
  - Alpine.js akan menangani fetch ulang data riwayat berdasarkan tab bulan yang aktif.

```javascript
// Alpine.js data state di layouts/guru.blade.php
{
    open: false,
    loading: false,
    studentName: '',
    sessionId: null,
    contentHtml: '',
    monthsList: [], // Menyimpan 3 opsi bulan [{name: 'Juni 2026', month: 6, year: 2026}]
    activeTab: null, // format: "MM-YYYY"
    
    init() {
        window.addEventListener('open-session-history', (e) => {
            this.sessionId = e.detail.sessionId;
            this.studentName = e.detail.studentName;
            
            // Hitung 3 bulan terakhir dari tanggal sesi aktif yang dikirim
            const sessionDate = new Date(e.detail.sessionDate);
            this.monthsList = this.calculateMonthsList(sessionDate);
            
            // Default tab aktif adalah bulan sesi tersebut
            const activeMonth = sessionDate.getMonth() + 1;
            const activeYear = sessionDate.getFullYear();
            this.activeTab = `${activeMonth}-${activeYear}`;
            
            this.open = true;
            this.fetchHistory(activeMonth, activeYear);
        });
    },

    calculateMonthsList(baseDate) {
        const list = [];
        for (let i = 0; i < 3; i++) {
            const d = new Date(baseDate.getFullYear(), baseDate.getMonth() - i, 1);
            const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            list.push({
                label: `${monthNames[d.getMonth()]} ${d.getFullYear()}`,
                shortLabel: `${monthNames[d.getMonth()].substring(0, 3)} ${d.getFullYear().toString().substring(2)}`,
                month: d.getMonth() + 1,
                year: d.getFullYear(),
                key: `${d.getMonth() + 1}-${d.getFullYear()}`
            });
        }
        return list;
    },

    fetchHistory(month, year) {
        this.loading = true;
        this.contentHtml = '';
        
        fetch(`{{ url('/guru/sesi') }}/${this.sessionId}/history?month=${month}&year=${year}`)
            .then(res => {
                if (!res.ok) throw new Error();
                return res.text();
            })
            .then(html => {
                this.contentHtml = html;
                this.loading = false;
            })
            .catch(() => {
                this.contentHtml = '<div class="py-8 text-center text-red-500 text-xs font-semibold">Gagal memuat riwayat. Silakan coba lagi.</div>';
                this.loading = false;
            });
    }
}
```

```html
<!-- Penambahan Markup Tab di layouts/guru.blade.php -->
<div x-show="open && monthsList.length > 0" class="px-5 py-2.5 bg-surface-variant/10 border-b border-outline-variant/15">
    <div class="flex gap-2 p-1 bg-outline-variant/10 rounded-xl">
        <template x-for="item in monthsList" :key="item.key">
            <button type="button"
                    @click="activeTab = item.key; fetchHistory(item.month, item.year)"
                    :class="activeTab === item.key ? 'bg-white text-primary shadow-sm' : 'text-on-surface-variant/60 hover:text-primary'"
                    class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold transition-all duration-200 focus:outline-none">
                <span class="block sm:hidden" x-text="item.shortLabel"></span>
                <span class="hidden sm:block" x-text="item.label"></span>
            </button>
        </template>
    </div>
</div>
```

### C. Frontend: Pengirim Event
* **File Terkait**: [resources/views/guru/_sesi-absensi-actions.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/guru/_sesi-absensi-actions.blade.php)
* **Perubahan**:
  - Kirim parameter `sessionDate` saat men-dispatch event `open-session-history`.

```html
<!-- resources/views/guru/_sesi-absensi-actions.blade.php -->
@click.stop.prevent="$dispatch('open-session-history', { 
    sessionId: {{ $sesi->id }}, 
    studentName: '{{ addslashes($sesi->student->full_name) }}',
    sessionDate: '{{ $sesi->session_date }}'
})"
```

---

## 4. Rencana Pengujian (Testing Plan)
1. **Unit/Feature Test**: 
   - Memodifikasi [tests/Feature/GuruSessionHistoryTest.php](file:///C:/laragon/www/musik-kita-ops/tests/Feature/GuruSessionHistoryTest.php) untuk memverifikasi bahwa query parameter `month` dan `year` memfilter sesi yang dikembalikan dengan benar.
2. **Manual Test (UI)**:
   - Login sebagai guru.
   - Buka dashboard kelas, klik tombol Riwayat Sesi.
   - Pastikan tab 3 bulan terakhir muncul dengan benar.
   - Pastikan perpindahan tab memicu loading skeleton dan memperbarui konten timeline.
