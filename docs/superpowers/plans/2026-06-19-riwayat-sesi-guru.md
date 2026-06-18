# Portal Guru: Riwayat Sesi Murid Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur popup responsif (Bottom Sheet di Mobile, Centered Modal di Desktop) bagi guru untuk melihat riwayat absensi dan catatan sesi murid pada bulan berjalan secara dinamis menggunakan AJAX dan Alpine.js.

**Architecture:** 
- Membuat endpoint baru `/guru/sesi/{classSession}/history` yang divalidasi dengan otorisasi pengajar.
- Mengembalikan view Blade parsial `guru._sesi-history-timeline` berisi data sesi lampau murid untuk enrollment terkait di bulan berjalan.
- Menambahkan modal wrapper global pada layout utama `layouts/guru.blade.php` yang mendengarkan event global Alpine.js dan mem-fetch serta merender konten secara dinamis.
- Menambahkan tombol pemicu di form catatan sesi.

**Tech Stack:** Laravel 11, Livewire/Blade, Alpine.js, Tailwind CSS 3.x, SQLite (untuk Feature Tests).

## Global Constraints

- Validasi akses penuh: Hanya guru yang terhubung dengan sesi tersebut (utama/pengganti) yang boleh mengakses riwayat sesi.
- Keterangan materi (`material_learned`), tugas (`homework_notes`), catatan tambahan (`notes`), dan rating (`session_rating`) diambil dari relasi `teacherNote`.
- Sesi di-filter hanya untuk enrollment yang sama di bulan/tahun berjalan (`attribution_month` & `attribution_year`).
- Menulis kode yang clean, mudah dipahami pemula, dan well-commented dalam Bahasa Indonesia.
- Pengujian (PHPUnit Feature Tests) wajib menggunakan database SQLite `:memory:`.

---

### Task 1: Route, Controller, dan Spesifikasi Fitur Keamanan (Access Control)

**Files:**
- Create: `tests/Feature/GuruSessionHistoryTest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/GuruController.php`

**Interfaces:**
- Consumes: `App\Models\ClassSession`, `App\Models\Teacher`, `App\Models\User`
- Produces: Route GET `/guru/sesi/{classSession}/history` dan method `GuruController@sessionHistory`

- [ ] **Step 1: Tulis feature test awal yang gagal**
  Buat file `tests/Feature/GuruSessionHistoryTest.php` dengan test case awal untuk memvalidasi akses endpoint dan otorisasi guru.
  ```php
  <?php

  namespace Tests\Feature;

  use App\Models\ClassSession;
  use App\Models\Enrollment;
  use App\Models\Instrument;
  use App\Models\Package;
  use App\Models\Student;
  use App\Models\Teacher;
  use App\Models\User;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Spatie\Permission\Models\Role;
  use Tests\TestCase;

  class GuruSessionHistoryTest extends TestCase
  {
      use RefreshDatabase;

      private User $guruUser;
      private Teacher $teacher;
      private ClassSession $session;

      protected function setUp(): void
      {
          parent::setUp();
          Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

          $this->guruUser = User::factory()->create();
          $this->guruUser->assignRole('Guru');
          $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

          $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
          $package = Package::factory()->create([
              'class_type' => 'REGULER',
              'instrument_id' => $instr->id,
          ]);
          $student = Student::factory()->create();
          $enroll = Enrollment::factory()->create([
              'student_id' => $student->id,
              'teacher_id' => $this->teacher->id,
              'package_id' => $package->id,
              'status' => 'ACTIVE',
          ]);

          $this->session = ClassSession::factory()->create([
              'enrollment_id' => $enroll->id,
              'student_id' => $student->id,
              'teacher_id' => $this->teacher->id,
              'session_date' => today()->toDateString(),
              'status' => 'HADIR',
          ]);
      }

      public function test_guest_cannot_access_history_endpoint(): void
      {
          $response = $this->get(route('guru.sesi.history', $this->session));
          $response->assertRedirect(route('login'));
      }

      public function test_authenticated_guru_can_access_own_session_history(): void
      {
          $response = $this->actingAs($this->guruUser)
              ->get(route('guru.sesi.history', $this->session));

          $response->assertStatus(200);
      }

      public function test_guru_cannot_access_other_teacher_session_history(): void
      {
          $otherTeacherUser = User::factory()->create();
          $otherTeacherUser->assignRole('Guru');
          $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);

          $otherSession = ClassSession::factory()->create([
              'teacher_id' => $otherTeacher->id,
              'session_date' => today()->toDateString(),
          ]);

          $response = $this->actingAs($this->guruUser)
              ->get(route('guru.sesi.history', $otherSession));

          $response->assertStatus(403);
      }
  }
  ```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalannya**
  Jalankan: `php artisan test --filter=GuruSessionHistoryTest`
  Expected: FAIL (route `guru.sesi.history` tidak ditemukan).

- [ ] **Step 3: Implementasi route dan controller method minimal**
  Tambahkan route baru di `routes/web.php` (di dalam grup `role:Guru` pada baris ~628):
  ```php
  Route::get('/sesi/{classSession}/history', [GuruController::class, 'sessionHistory'])
      ->name('sesi.history');
  ```
  Tambahkan method `sessionHistory` pada `app/Http/Controllers/GuruController.php` (di bagian bawah file):
  ```php
  public function sessionHistory(ClassSession $classSession)
  {
      $teacher = auth()->user()->teacher;
      abort_if(!$teacher, 403);

      // Verifikasi hak akses (hanya guru utama atau guru pengganti yang boleh akses)
      abort_if(
          $classSession->teacher_id !== $teacher->id && 
          $classSession->substitute_teacher_id !== $teacher->id, 
          403, 
          'Bukan sesi Anda.'
      );

      // Sesi bulan berjalan (Juni 2026) dari enrollment yang sama, kecuali sesi aktif
      $history = ClassSession::where('enrollment_id', $classSession->enrollment_id)
          ->where('id', '!=', $classSession->id)
          ->where('attribution_month', $classSession->attribution_month)
          ->where('attribution_year', $classSession->attribution_year)
          ->whereIn('status', ['HADIR', 'HADIR_TERLAMBAT'])
          ->with('teacherNote')
          ->orderByDesc('session_date')
          ->orderByDesc('start_time')
          ->get();

      // Sementara kembalikan view kosong atau mock response
      return view('guru._sesi-history-timeline', compact('history', 'classSession'));
  }
  ```
  Untuk menghindari error view not found saat test dijalankan, buatlah file kosong sementara di `resources/views/guru/_sesi-history-timeline.blade.php`:
  ```html
  <!-- temporary view -->
  ```

- [ ] **Step 4: Jalankan test kembali untuk memverifikasi kelulusannya**
  Jalankan: `php artisan test --filter=GuruSessionHistoryTest`
  Expected: PASS.

- [ ] **Step 5: Commit perubahan**
  ```bash
  git add tests/Feature/GuruSessionHistoryTest.php routes/web.php app/Http/Controllers/GuruController.php resources/views/guru/_sesi-history-timeline.blade.php
  git commit -m "[M10] Tambah route, basic controller method, dan test otorisasi riwayat sesi"
  ```

---

### Task 2: Implementasi View Timeline Parsial (`_sesi-history-timeline.blade.php`)

**Files:**
- Modify: `tests/Feature/GuruSessionHistoryTest.php`
- Modify: `resources/views/guru/_sesi-history-timeline.blade.php`

**Interfaces:**
- Consumes: Koleksi `$history` dari controller
- Produces: Rendered HTML timeline vertikal (materi, tugas, rating, catatan tambahan)

- [ ] **Step 1: Tulis test case baru untuk validasi data timeline dan empty state**
  Tambahkan method pengujian ke `tests/Feature/GuruSessionHistoryTest.php`:
  ```php
  public function test_renders_history_data_correctly(): void
  {
      // Buat sesi historis di bulan berjalan
      $pastSession = ClassSession::factory()->create([
          'enrollment_id' => $this->session->enrollment_id,
          'student_id' => $this->session->student_id,
          'teacher_id' => $this->teacher->id,
          'session_date' => today()->subDays(7)->toDateString(),
          'attribution_month' => $this->session->attribution_month,
          'attribution_year' => $this->session->attribution_year,
          'status' => 'HADIR',
      ]);

      // Buat catatan sesi untuk sesi historis tersebut
      \App\Models\SessionTeacherNote::create([
          'class_session_id' => $pastSession->id,
          'teacher_id' => $this->teacher->id,
          'material_learned' => 'Tangga nada C mayor',
          'homework_notes' => 'Latihan ketukan metronom 80',
          'notes' => 'Ahmad sangat fokus hari ini',
          'session_rating' => 5,
      ]);

      $response = $this->actingAs($this->guruUser)
          ->get(route('guru.sesi.history', $this->session));

      $response->assertStatus(200);
      $response->assertSee('Tangga nada C mayor');
      $response->assertSee('Latihan ketukan metronom 80');
      $response->assertSee('Ahmad sangat fokus hari ini');
      $response->assertSee('★★★★★');
  }

  public function test_renders_empty_state_when_no_history(): void
  {
      $response = $this->actingAs($this->guruUser)
          ->get(route('guru.sesi.history', $this->session));

      $response->assertStatus(200);
      $response->assertSee('Belum ada riwayat sesi di bulan ini');
  }
  ```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalannya**
  Jalankan: `php artisan test --filter=GuruSessionHistoryTest`
  Expected: FAIL (tidak menemukan teks 'Tangga nada C mayor' dll).

- [ ] **Step 3: Implementasi Blade View Parsial**
  Tulis markup timeline ke `resources/views/guru/_sesi-history-timeline.blade.php`:
  ```html
  @if($history->isEmpty())
      <div class="flex flex-col items-center justify-center py-12 text-center">
          <div class="w-16 h-16 bg-surface rounded-full flex items-center justify-center border border-border-light text-2xl mb-3">
              📅
          </div>
          <h4 class="font-semibold text-primary text-sm">Belum ada riwayat sesi</h4>
          <p class="text-xs text-on-surface-variant/70 mt-1 max-w-[240px]">
              Belum ada riwayat sesi yang tercatat hadir pada bulan berjalan ini.
          </p>
      </div>
  @else
      <div class="relative pl-6 border-l-2 border-outline-variant/30 ml-2.5 mt-2">
          @foreach($history as $idx => $sesi)
              @php
                  $note = $sesi->teacherNote;
                  $dateObj = \Carbon\Carbon::parse($sesi->session_date);
              @endphp
              <div class="relative mb-6 last:mb-2">
                  <!-- Bullet marker -->
                  <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full bg-accent border-2 border-white ring-2 ring-accent/30"></div>
                  
                  <!-- Sesi meta header -->
                  <div class="flex items-center justify-between gap-2 mb-1.5">
                      <span class="text-xs font-bold text-primary">
                          {{ $dateObj->locale('id')->isoFormat('dddd, D MMM Y') }}
                      </span>
                      <span class="text-[9px] bg-secondary-container/40 text-secondary font-semibold px-2 py-0.5 rounded-full border border-secondary/20 uppercase tracking-wide">
                          {{ $sesi->status }}
                      </span>
                  </div>

                  <!-- Details Card -->
                  <div class="bg-surface/50 border border-outline-variant/20 rounded-xl p-3 shadow-sm">
                      <!-- Rating Star -->
                      @if($note && $note->session_rating)
                          <div class="text-amber-500 text-xs mb-1.5 flex gap-0.5" title="Rating: {{ $note->session_rating }}/5">
                              @for($i = 1; $i <= 5; $i++)
                                  <span>{{ $i <= $note->session_rating ? '★' : '☆' }}</span>
                              @endfor
                          </div>
                      @endif

                      <!-- Content Fields -->
                      <div class="space-y-2">
                          <div>
                              <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Materi</span>
                              <p class="text-xs text-on-surface mt-0.5 leading-relaxed">
                                  {{ $note && $note->material_learned ? $note->material_learned : '—' }}
                              </p>
                          </div>
                          <div>
                              <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Tugas &amp; Latihan</span>
                              <p class="text-xs text-on-surface mt-0.5 leading-relaxed">
                                  {{ $note && $note->homework_notes ? $note->homework_notes : '—' }}
                              </p>
                          </div>
                          @if($note && $note->notes)
                              <div>
                                  <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Catatan Tambahan</span>
                                  <p class="text-xs text-on-surface-variant mt-0.5 leading-relaxed italic">
                                      "{{ $note->notes }}"
                                  </p>
                              </div>
                          @endif
                      </div>
                  </div>
              </div>
          @endforeach
      </div>
  @endif
  ```

- [ ] **Step 4: Jalankan test kembali untuk memverifikasi kelulusannya**
  Jalankan: `php artisan test --filter=GuruSessionHistoryTest`
  Expected: PASS.

- [ ] **Step 5: Commit perubahan**
  ```bash
  git add resources/views/guru/_sesi-history-timeline.blade.php tests/Feature/GuruSessionHistoryTest.php
  git commit -m "[M10] Implementasi Blade view timeline riwayat sesi dan tes kontennya"
  ```

---

### Task 3: Global Drawer/Modal Layout di `layouts/guru.blade.php`

**Files:**
- Modify: `resources/views/layouts/guru.blade.php`

**Interfaces:**
- Consumes: Event global Alpine.js `open-session-history`
- Produces: Drawer/Modal terpadu yang mem-fetch dan merender riwayat sesi secara dinamis.

- [ ] **Step 1: Edit layout utama untuk menyisipkan bottom sheet/centered-modal**
  Buka file `resources/views/layouts/guru.blade.php` dan cari area penutup sebelum `</body>`. Sisipkan wrapper modal Alpine.js berikut:
  ```html
  <!-- Global Session History Drawer/Modal -->
  <div x-data="{
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
                  
                  fetch(`/guru/sesi/${this.sessionId}/history`)
                      .then(res => {
                          if (!res.ok) throw new Error();
                          return res.text();
                      })
                      .then(html => {
                          this.contentHtml = html;
                          this.loading = false;
                      })
                      .catch(() => {
                          this.contentHtml = '<div class=\"py-8 text-center text-error text-xs\">Gagal memuat riwayat. Silakan coba lagi.</div>';
                          this.loading = false;
                      });
              });
          }
       }"
       x-cloak>
       
      <!-- Dark Backdrop -->
      <div class="fixed inset-0 bg-neutral-text/40 z-50 transition-opacity duration-300"
           x-show="open"
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0"
           x-transition:enter-end="opacity-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           @click="open = false"></div>

      <!-- Drawer/Modal Container -->
      <div class="fixed z-50 transition-all duration-300 flex flex-col bg-white shadow-2xl overflow-hidden
                  /* Mobile: Slide-up Bottom Sheet */
                  bottom-0 left-0 right-0 w-full rounded-t-2xl max-h-[85%]
                  /* Desktop: Centered Modal */
                  sm:bottom-auto sm:top-1/2 sm:left-1/2 sm:w-[480px] sm:max-w-lg sm:rounded-2xl sm:max-h-[75%]"
           x-show="open"
           x-transition:enter="ease-out duration-300"
           /* Responsive entrance: slide up on mobile, fade+scale on desktop */
           x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0 sm:-translate-x-1/2 sm:-translate-y-[45%]"
           x-transition:enter-end="translate-y-0 sm:translate-y-0 sm:scale-100 sm:opacity-100 sm:-translate-x-1/2 sm:-translate-y-1/2"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="translate-y-0 sm:translate-y-0 sm:scale-100 sm:opacity-100 sm:-translate-x-1/2 sm:-translate-y-1/2"
           x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0 sm:-translate-x-1/2 sm:-translate-y-[45%]"
           /* Center-alignment trick for desktop modal */
           :style="window.innerWidth >= 640 && open ? 'transform: translate(-50%, -50%)' : ''">

          <!-- Drag Handle for Mobile -->
          <div class="w-10 h-1 bg-outline-variant/30 rounded-full mx-auto my-3 shrink-0 sm:hidden"></div>

          <!-- Header -->
          <div class="px-5 py-3.5 border-b border-outline-variant/15 flex items-center justify-between shrink-0 bg-surface/30">
              <div>
                  <h3 class="font-bold text-primary text-sm tracking-tight sm:text-base font-serif">Riwayat Sesi</h3>
                  <p class="text-[10px] text-on-surface-variant/60 font-semibold tracking-wide uppercase mt-0.5" x-text="studentName"></p>
              </div>
              <button type="button" 
                      class="text-on-surface-variant/60 hover:text-primary p-1 rounded-full hover:bg-surface transition-colors cursor-pointer text-base leading-none"
                      @click="open = false">
                  &times;
              </button>
          </div>

          <!-- Body Content Area -->
          <div class="p-5 overflow-y-auto flex-1">
              <!-- Loading Skeleton -->
              <div x-show="loading" class="space-y-4 py-2">
                  <div class="animate-pulse flex gap-3">
                      <div class="w-3 h-3 bg-outline-variant/40 rounded-full mt-1.5"></div>
                      <div class="flex-1 space-y-2">
                          <div class="h-3 bg-outline-variant/40 rounded w-1/3"></div>
                          <div class="h-20 bg-outline-variant/30 rounded-xl"></div>
                      </div>
                  </div>
                  <div class="animate-pulse flex gap-3">
                      <div class="w-3 h-3 bg-outline-variant/40 rounded-full mt-1.5"></div>
                      <div class="flex-1 space-y-2">
                          <div class="h-3 bg-outline-variant/40 rounded w-1/4"></div>
                          <div class="h-16 bg-outline-variant/30 rounded-xl"></div>
                      </div>
                  </div>
              </div>

              <!-- Real Timeline content loaded via AJAX -->
              <div x-show="!loading" x-html="contentHtml"></div>
          </div>
      </div>
  </div>
  ```

- [ ] **Step 2: Komit perubahan layout**
  ```bash
  git add resources/views/layouts/guru.blade.php
  git commit -m "[M10] Tambah global bottom-sheet / modal layout dengan Alpine.js dan fetch skeleton"
  ```

---

### Task 4: Tambahkan Tombol Pemicu di Form Absensi Actions

**Files:**
- Modify: `resources/views/guru/_sesi-absensi-actions.blade.php`

**Interfaces:**
- Consumes: Sesi yang aktif (`$sesi`)
- Produces: Trigger tombol Riwayat Sesi yang melempar Alpine.js event global

- [ ] **Step 1: Edit `_sesi-absensi-actions.blade.php` untuk memasukkan tombol**
  Buka file `resources/views/guru/_sesi-absensi-actions.blade.php`.
  Cari baris `Catatan Sesi` di baris ~34-39:
  ```html
  <span class="text-sm font-semibold text-primary">
      Catatan Sesi
      @if(!$teacherNote)
          <span class="text-[#2c694e] font-normal text-xs ml-1.5">— tap untuk mengisi laporan</span>
      @endif
  </span>
  ```
  Modifikasi elemen tersebut untuk meletakkan tombol di samping kanan tulisan secara responsif (gunakan flexbox alignment):
  ```html
  <div class="flex items-center justify-between w-full pr-6">
      <span class="text-sm font-semibold text-primary flex items-center gap-1.5">
          Catatan Sesi
          @if(!$teacherNote)
              <span class="text-[#2c694e] font-normal text-xs ml-1.5 hidden sm:inline">— tap untuk mengisi laporan</span>
          @endif
      </span>
      
      <!-- Tombol Riwayat Sesi -->
      <button type="button"
              class="history-btn shrink-0"
              @click.stop="$dispatch('open-session-history', { sessionId: {{ $sesi->id }}, studentName: '{{ addslashes($sesi->student->full_name) }}' })">
          ⏳ Riwayat Sesi
      </button>
  </div>
  ```

- [ ] **Step 2: Verifikasi pengujian lokal**
  Jalankan seluruh test suite portal guru untuk menjamin tidak ada yang rusak:
  `php artisan test --filter=Guru`
  Expected: Semua tests (termasuk yang baru kita buat) berhasil lolos (PASS).

- [ ] **Step 3: Commit perubahan akhir**
  ```bash
  git add resources/views/guru/_sesi-absensi-actions.blade.php
  git commit -m "[M10] Tambahkan tombol pemicu Riwayat Sesi pada form absensi actions"
  ```
