# Rencana Implementasi: Filter Riwayat Sesi Guru (Portal Guru)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan menu filter bulan (3 bulan terakhir: bulan berjalan + 2 bulan sebelumnya) menggunakan tab kontrol horizontal yang interaktif di modal riwayat sesi portal guru secara dinamis dan efisien.

**Architecture:** Memanfaatkan filter query string parameters (`month` dan `year`) pada endpoint riwayat sesi yang sudah ada, menangkapnya di Controller melalui request helper, dan memperbarui visualisasi drawer menggunakan event dynamic Alpine.js di layout portal guru.

**Tech Stack:** Laravel 11, PHP 8.3, Blade, Alpine.js, Tailwind CSS 3.x, PHPUnit.

## Global Constraints
- Bahasa komentar kode: **Bahasa Indonesia**.
- Bahasa pesan validasi & UI: **Bahasa Indonesia**.
- Gunakan style Tailwind CSS, **JANGAN** menggunakan Bootstrap.
- Batasi modifikasi controller seminimal mungkin (pendekatan YAGNI/ponytail).
- Database Testing menggunakan database in-memory (RefreshDatabase).

---

### Task 1: Pembuatan Failing Test & Implementasi Backend (Controller)

**Files:**
- Modify: `tests/Feature/GuruSessionHistoryTest.php`
- Modify: `app/Http/Controllers/GuruController.php:922-948`

**Interfaces:**
- Consumes: Parameter opsional `month` dan `year` dari query string URL.
- Produces: Data history sesi ter-filter dalam view data variable `$history`.

- [ ] **Step 1: Tulis unit test untuk memverifikasi query parameter filter di controller**

Buka file [tests/Feature/GuruSessionHistoryTest.php](file:///C:/laragon/www/musik-kita-ops/tests/Feature/GuruSessionHistoryTest.php) dan tambahkan test method baru di bagian akhir class:

```php
    public function test_can_filter_history_by_month_and_year_parameters(): void
    {
        $year = $this->session->attribution_year;
        $month = $this->session->attribution_month;

        // Tentukan bulan & tahun target filter (1 bulan sebelumnya)
        $targetMonth = $month == 1 ? 12 : $month - 1;
        $targetYear = $month == 1 ? $year - 1 : $year;

        // Buat sesi historis di bulan target (Mei)
        $pastSession = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-15', $targetYear, $targetMonth),
            'attribution_month' => $targetMonth,
            'attribution_year' => $targetYear,
            'status' => 'HADIR',
        ]);

        // Panggil endpoint dengan parameter target
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', [
                'classSession' => $this->session->id,
                'month' => $targetMonth,
                'year' => $targetYear
            ]));

        $response->assertStatus(200);
        $history = $response->viewData('history');

        // Pastikan hanya $pastSession yang dikembalikan
        $this->assertCount(1, $history);
        $this->assertEquals($pastSession->id, $history->first()->id);
    }
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan**

Jalankan perintah pengujian:
```powershell
php artisan test --filter=test_can_filter_history_by_month_and_year_parameters
```
**Expected Output:** FAIL (assertCount 1 failed, actual 0) karena controller saat ini masih mengabaikan parameter request dan selalu memfilter berdasarkan bulan sesi berjalan.

- [ ] **Step 3: Implementasikan filter parameter request di GuruController**

Buka file [app/Http/Controllers/GuruController.php](file:///C:/laragon/www/musik-kita-ops/app/Http/Controllers/GuruController.php) dan ubah method `sessionHistory` mulai baris 922:

```php
    public function sessionHistory(ClassSession $classSession)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        // Verifikasi hak akses (hanya guru utama atau guru pengganti yang boleh akses)
        abort_if(
            (int) $classSession->teacher_id !== (int) $teacher->id && 
            (int) $classSession->substitute_teacher_id !== (int) $teacher->id, 
            403, 
            'Bukan sesi Anda.'
        );

        // ponytail: fallback ke bulan/tahun atribusi sesi jika parameter request tidak tersedia
        $month = request('month', $classSession->attribution_month);
        $year = request('year', $classSession->attribution_year);

        // Sesi sesuai filter bulan & tahun dari enrollment yang sama, kecuali sesi aktif
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

- [ ] **Step 4: Jalankan test untuk memverifikasi keberhasilan**

Jalankan kembali perintah pengujian:
```powershell
php artisan test tests/Feature/GuruSessionHistoryTest.php
```
**Expected Output:** PASS (Semua tes lulus termasuk pengujian filter parameter request).

- [ ] **Step 5: Commit Perubahan Backend**

```bash
git add tests/Feature/GuruSessionHistoryTest.php app/Http/Controllers/GuruController.php
git commit -m "M10: Tambah logika filter request month dan year pada endpoint riwayat sesi guru"
```

---

### Task 2: Modifikasi Dispatcher Event Riwayat Sesi (Frontend)

**Files:**
- Modify: `resources/views/guru/_sesi-absensi-actions.blade.php`

**Interfaces:**
- Consumes: Properti `$sesi` di loop absensi.
- Produces: Dispatch event `open-session-history` dengan properti tambahan `sessionDate`.

- [ ] **Step 1: Kirimkan session_date pada trigger event riwayat**

Buka file [resources/views/guru/_sesi-absensi-actions.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/guru/_sesi-absensi-actions.blade.php) pada baris 44-46:

```html
<!-- target targetContent yang diganti -->
                        @click.stop.prevent="$dispatch('open-session-history', { sessionId: {{ $sesi->id }}, studentName: '{{ addslashes($sesi->student->full_name) }}' })"
```

Ganti menjadi:

```html
<!-- replacementContent -->
                        @click.stop.prevent="$dispatch('open-session-history', { 
                            sessionId: {{ $sesi->id }}, 
                            studentName: '{{ addslashes($sesi->student->full_name) }}',
                            sessionDate: '{{ $sesi->session_date }}'
                        })"
```

- [ ] **Step 2: Commit Perubahan Event Trigger**

```bash
git add resources/views/guru/_sesi-absensi-actions.blade.php
git commit -m "M10: Kirim sessionDate pada dispatch event open-session-history"
```

---

### Task 3: Pembuatan Antarmuka Filter Tab di Layout Modal (Frontend UI)

**Files:**
- Modify: `resources/views/layouts/guru.blade.php`

**Interfaces:**
- Consumes: Parameter event `open-session-history` (sessionId, studentName, sessionDate).
- Produces: Fetch asinkronus dengan format query string parameter dinamis berdasarkan tab bulan yang dipilih.

- [ ] **Step 1: Perluas state data Alpine.js modal di layouts/guru.blade.php**

Buka file [resources/views/layouts/guru.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/layouts/guru.blade.php) pada baris 229-258.

Ganti blok `x-data` lama:

```javascript
    x-data="{
        open: false,
        loading: false,
        studentName: '',
        sessionId: null,
        contentHtml: '',
        init() {
            window.addEventListener('open-session-history', (e) => {
                this.sessionId = e.detail.sessionId;
                this.studentName = e.detail.studentName;
                this.open = true;
                this.loading = true;
                this.contentHtml = '';
                
                fetch('{{ url('/guru/sesi') }}/' + this.sessionId + '/history')
                    .then(res => {
                        if (!res.ok) throw new Error();
                        return res.text();
                    })
                    .then(html => {
                        this.contentHtml = html;
                        this.loading = false;
                    })
                    .catch(() => {
                        this.contentHtml = '<div class=\'py-8 text-center text-red-500 text-xs font-semibold\'>Gagal memuat riwayat. Silakan coba lagi.</div>';
                        this.loading = false;
                    });
            });
        }
     }"
```

Menjadi blok `x-data` baru:

```javascript
    x-data="{
        open: false,
        loading: false,
        studentName: '',
        sessionId: null,
        contentHtml: '',
        monthsList: [],
        activeTab: '',
        
        init() {
            window.addEventListener('open-session-history', (e) => {
                this.sessionId = e.detail.sessionId;
                this.studentName = e.detail.studentName;
                
                // ponytail: kalkulasi daftar 3 bulan terakhir di sisi client dari sessionDate
                const sessionDate = new Date(e.detail.sessionDate);
                this.monthsList = this.calculateMonthsList(sessionDate);
                
                const baseMonth = sessionDate.getMonth() + 1;
                const baseYear = sessionDate.getFullYear();
                this.activeTab = `${baseMonth}-${baseYear}`;
                
                this.open = true;
                this.fetchHistory(baseMonth, baseYear);
            });
        },

        calculateMonthsList(baseDate) {
            const list = [];
            const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            for (let i = 0; i < 3; i++) {
                const tempDate = new Date(baseDate.getFullYear(), baseDate.getMonth() - i, 1);
                const m = tempDate.getMonth() + 1;
                const y = tempDate.getFullYear();
                list.push({
                    label: `${monthNames[tempDate.getMonth()]} ${y}`,
                    shortLabel: `${monthNames[tempDate.getMonth()].substring(0, 3)} ${y.toString().substring(2)}`,
                    month: m,
                    year: y,
                    key: `${m}-${y}`
                });
            }
            return list;
        },

        fetchHistory(month, year) {
            this.loading = true;
            this.contentHtml = '';
            
            fetch('{{ url('/guru/sesi') }}/' + this.sessionId + '/history?month=' + month + '&year=' + year)
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.text();
                })
                .then(html => {
                    this.contentHtml = html;
                    this.loading = false;
                })
                .catch(() => {
                    this.contentHtml = '<div class=\'py-8 text-center text-red-500 text-xs font-semibold\'>Gagal memuat riwayat. Silakan coba lagi.</div>';
                    this.loading = false;
                });
        }
     }"
```

- [ ] **Step 2: Tambahkan markup filter tab horizontal ke dalam layout drawer**

Temukan header drawer di file [resources/views/layouts/guru.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/layouts/guru.blade.php) baris 291-301. Tepat di bawah header drawer (setelah penutup `</div>` header), tambahkan layout tab dengan styling Tailwind:

```html
        <!-- Aksen Filter Tab (impeccable: modern style, high contrast) -->
        <div x-show="open && monthsList.length > 0" class="px-5 py-2.5 bg-slate-50 border-b border-outline-variant/15 shrink-0">
            <div class="flex gap-1.5 p-1 bg-slate-200/60 rounded-xl">
                <template x-for="item in monthsList" :key="item.key">
                    <button type="button"
                            @click="activeTab = item.key; fetchHistory(item.month, item.year)"
                            :class="activeTab === item.key ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold transition-all duration-200 focus:outline-none cursor-pointer">
                        <span class="block sm:hidden" x-text="item.shortLabel"></span>
                        <span class="hidden sm:block" x-text="item.label"></span>
                    </button>
                </template>
            </div>
        </div>
```

- [ ] **Step 3: Verifikasi build frontend**

Gunakan command build npm untuk memverifikasi tidak ada error sintaks pada asset Vite:
```powershell
npm run build
```
**Expected Output:** Build successfully (tanpa error).

- [ ] **Step 4: Commit Perubahan Layout Modal**

```bash
git add resources/views/layouts/guru.blade.php
git commit -m "M10: Tambah UI filter tab horizontal 3 bulan terakhir pada drawer riwayat sesi"
```
