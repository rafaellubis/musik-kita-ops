# Cetak Absensi dengan Filter Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan filter pencetakan absensi harian berdasarkan guru yang sebenarnya mengajar pada hari tersebut.

**Architecture:** Menggunakan Backend-based Filter (Pendekatan 1) di mana query parameter `teacher_id` dikirimkan ke `/absensi/print`. Sisi server memfilter sesi kelas menggunakan Eloquent (mencocokkan `teacher_id` atau `substitute_teacher_id`). Di frontend, Javascript memperbarui URL tombol cetak secara dinamis di halaman utama dan menambahkan pemilih dropdown instan pada halaman cetak.

**Tech Stack:** Laravel 11, PHP 8.3, Blade, Tailwind CSS 3, Vanilla JavaScript, SQLite (:memory:) untuk testing.

## Global Constraints

- Kode yang clean, mudah dibaca, dan well-commented dalam Bahasa Indonesia.
- Menggunakan Tailwind CSS, bukan Bootstrap.
- Validasi di Form Request (jika ada form submission baru).
- Database Testing menggunakan SQLite `:memory:` (mengikuti pengaturan `phpunit.xml` yang sudah ada).
- Melakukan commit secara atomic untuk setiap task yang diselesaikan dengan prefix modul `M04`.

---

### Task 1: Backend Filter dan View Cetak Absensi

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `resources/views/absensi/print.blade.php`
- Modify: `tests/Feature/Admin/AbsensiPrintTest.php`

**Interfaces:**
- Consumes: Query parameters `date` dan `teacher_id` dari Route `absensi.print`.
- Produces: Halaman absensi cetak yang terfilter, dropdown pilihan guru di panel cetak, sinkronisasi tombol cetak.

- [ ] **Step 1: Tulis tes yang gagal (Failing Tests)**
  Tambahkan tes berikut ke akhir file [AbsensiPrintTest.php](file:///C:/laragon/www/musik-kita-ops/tests/Feature/Admin/AbsensiPrintTest.php):

  ```php
      public function test_print_page_filters_by_teacher_id(): void
      {
          $user = User::factory()->create();
          $user->assignRole('Admin');

          $thomas = \App\Models\Teacher::factory()->create(['name' => 'THOMAS', 'is_active' => true]);
          $adi = \App\Models\Teacher::factory()->create(['name' => 'ADI', 'is_active' => true]);

          $student = \App\Models\Student::factory()->create();
          $room = \App\Models\Room::factory()->create(['code' => 'R1']);
          $package = \App\Models\Package::factory()->create();

          $enrollmentThomas = \App\Models\Enrollment::factory()->create([
              'student_id' => $student->id,
              'package_id' => $package->id,
              'teacher_id' => $thomas->id,
              'status' => 'ACTIVE',
          ]);
          $enrollmentAdi = \App\Models\Enrollment::factory()->create([
              'student_id' => $student->id,
              'package_id' => $package->id,
              'teacher_id' => $adi->id,
              'status' => 'ACTIVE',
          ]);

          // Sesi 1: Utama Thomas, no substitute (Thomas mengajar)
          \App\Models\ClassSession::factory()->create([
              'enrollment_id' => $enrollmentThomas->id,
              'student_id' => $student->id,
              'teacher_id' => $thomas->id,
              'room_id' => $room->id,
              'session_date' => '2026-06-29',
              'start_time' => '10:00:00',
              'end_time' => '10:30:00',
              'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
              'notes' => 'Sesi Thomas Asli',
          ]);

          // Sesi 2: Utama Adi, no substitute (Adi mengajar)
          \App\Models\ClassSession::factory()->create([
              'enrollment_id' => $enrollmentAdi->id,
              'student_id' => $student->id,
              'teacher_id' => $adi->id,
              'room_id' => $room->id,
              'session_date' => '2026-06-29',
              'start_time' => '11:00:00',
              'end_time' => '11:30:00',
              'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
              'notes' => 'Sesi Adi Asli',
          ]);

          // Sesi 3: Utama Adi, substitute Thomas (Thomas mengajar)
          \App\Models\ClassSession::factory()->create([
              'enrollment_id' => $enrollmentAdi->id,
              'student_id' => $student->id,
              'teacher_id' => $adi->id,
              'substitute_teacher_id' => $thomas->id,
              'room_id' => $room->id,
              'session_date' => '2026-06-29',
              'start_time' => '12:00:00',
              'end_time' => '12:30:00',
              'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
              'notes' => 'Sesi Adi diganti Thomas',
          ]);

          // Sesi 4: Utama Thomas, substitute Adi (Adi mengajar)
          \App\Models\ClassSession::factory()->create([
              'enrollment_id' => $enrollmentThomas->id,
              'student_id' => $student->id,
              'teacher_id' => $thomas->id,
              'substitute_teacher_id' => $adi->id,
              'room_id' => $room->id,
              'session_date' => '2026-06-29',
              'start_time' => '13:00:00',
              'end_time' => '13:30:00',
              'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
              'notes' => 'Sesi Thomas diganti Adi',
          ]);

          // Test filter untuk Thomas (Harus ada Sesi 1 & 3, tidak ada Sesi 2 & 4)
          $responseThomas = $this->actingAs($user)->get(route('absensi.print', [
              'date' => '2026-06-29',
              'teacher_id' => $thomas->id,
          ]));
          $responseThomas->assertStatus(200);
          $responseThomas->assertSee('Sesi Thomas Asli');
          $responseThomas->assertSee('Sesi Adi diganti Thomas');
          $responseThomas->assertDontSee('Sesi Adi Asli');
          $responseThomas->assertDontSee('Sesi Thomas diganti Adi');

          // Test filter untuk Adi (Harus ada Sesi 2 & 4, tidak ada Sesi 1 & 3)
          $responseAdi = $this->actingAs($user)->get(route('absensi.print', [
              'date' => '2026-06-29',
              'teacher_id' => $adi->id,
          ]));
          $responseAdi->assertStatus(200);
          $responseAdi->assertSee('Sesi Adi Asli');
          $responseAdi->assertSee('Sesi Thomas diganti Adi');
          $responseAdi->assertDontSee('Sesi Thomas Asli');
          $responseAdi->assertDontSee('Sesi Adi diganti Thomas');
      }

      public function test_print_page_displays_selected_teacher_name_in_header(): void
      {
          $user = User::factory()->create();
          $user->assignRole('Admin');

          $thomas = \App\Models\Teacher::factory()->create(['name' => 'THOMAS_TEST', 'is_active' => true]);

          $response = $this->actingAs($user)->get(route('absensi.print', [
              'date' => '2026-06-29',
              'teacher_id' => $thomas->id,
          ]));

          $response->assertStatus(200);
          $response->assertSee('Daftar Absensi Harian (Guru: THOMAS_TEST)');
      }

      public function test_print_page_renders_teacher_dropdown(): void
      {
          $user = User::factory()->create();
          $user->assignRole('Admin');

          \App\Models\Teacher::factory()->create(['name' => 'THOMAS_DROPDOWN', 'is_active' => true]);
          \App\Models\Teacher::factory()->create(['name' => 'ADI_DROPDOWN', 'is_active' => true]);

          $response = $this->actingAs($user)->get(route('absensi.print', [
              'date' => '2026-06-29',
          ]));

          $response->assertStatus(200);
          $response->assertSee('id="print-filter-guru"', false);
          $response->assertSee('THOMAS_DROPDOWN');
          $response->assertSee('ADI_DROPDOWN');
      }
  ```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalannya**
  Jalankan perintah berikut:
  `php artisan test tests/Feature/Admin/AbsensiPrintTest.php`
  *Ekspektasi:* Pengujian gagal karena fungsionalitas filter `teacher_id` belum diimplementasikan di controller maupun view cetak.

- [ ] **Step 3: Implementasikan logika filter di Controller**
  Ubah method `print(Request $request)` pada [AbsensiController.php](file:///C:/laragon/www/musik-kita-ops/app/Http/Controllers/AbsensiController.php#L100-L120) menjadi:

  ```php
      public function print(Request $request): View
      {
          $tanggal = $request->date
              ? Carbon::parse($request->date)->toDateString()
              : today()->toDateString();

          $teacherId = $request->teacher_id;
          $selectedTeacher = null;

          $query = ClassSession::with([
                  'student', 'teacher', 'substituteTeacher', 'room',
                  'enrollment.package.instrument',
              ])
              ->where('session_date', $tanggal)
              ->orderBy('start_time')
              ->orderBy('room_id');

          if ($teacherId) {
              $selectedTeacher = Teacher::findOrFail($teacherId);
              $query->where(function($q) use ($teacherId) {
                  $q->where(function($sub) use ($teacherId) {
                      $sub->where('teacher_id', $teacherId)
                          ->whereNull('substitute_teacher_id');
                  })->orWhere('substitute_teacher_id', $teacherId);
              });
          }

          $sessions = $query->get();
          $teachers = Teacher::where('is_active', true)->orderBy('name')->get();

          return view('absensi.print', [
              'sessions'        => $sessions,
              'teachers'        => $teachers,
              'selectedTeacher' => $selectedTeacher,
              'tanggal'         => $tanggal,
              'tanggalObj'      => Carbon::parse($tanggal),
          ]);
      }
  ```

- [ ] **Step 4: Perbarui View Cetak (`resources/views/absensi/print.blade.php`)**
  Modifikasi file [print.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/absensi/print.blade.php):
  1. Pada `.no-print-panel` (sekitar baris 148-159), tambahkan dropdown pilihan guru di bagian tengah:
     ```html
         <!-- Panel Kontrol untuk Tampilan Layar (Disembunyikan saat Print) -->
         <div class="no-print-panel no-print">
             <div>
                 <a href="{{ route('absensi.index', ['date' => $tanggal]) }}" class="btn btn-secondary">
                     &larr; Kembali ke Absensi Harian
                 </a>
             </div>
             <!-- Dropdown Filter Guru -->
             <div>
                 <label for="print-filter-guru" style="font-weight: 600; margin-right: 8px; font-size: 12px;">Filter Guru:</label>
                 <select id="print-filter-guru" onchange="switchPrintTeacher(this.value)" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; background: white;">
                     <option value="">Semua Guru</option>
                     @foreach($teachers as $t)
                         <option value="{{ $t->id }}" {{ request('teacher_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                     @endforeach
                 </select>
             </div>
             <div>
                 <button onclick="window.print()" class="btn btn-primary">
                     Cetak Halaman (Print)
                 </button>
             </div>
         </div>
     ```
  2. Perbarui judul / kop cetakan (sekitar baris 161-165) agar menampilkan nama guru jika sedang difilter:
     ```html
         <!-- Kop Cetakan -->
         <div class="header">
             <h1>Musik KITA</h1>
             <p>
                 Daftar Absensi Harian
                 @if($selectedTeacher)
                     (Guru: {{ $selectedTeacher->name }})
                 @endif
                 - {{ $tanggalObj->locale('id')->translatedFormat('l, d F Y') }}
             </p>
         </div>
     ```
  3. Tambahkan fungsi JavaScript helper `switchPrintTeacher` di bagian bawah file sebelum tag penutup `</body>` (sekitar baris 244-249):
     ```html
         <script>
             function switchPrintTeacher(teacherId) {
                 const url = new URL(window.location.href);
                 if (teacherId) {
                     url.searchParams.set('teacher_id', teacherId);
                 } else {
                     url.searchParams.delete('teacher_id');
                 }
                 window.location.href = url.toString();
             }

             // Picu cetak browser otomatis saat halaman selesai dimuat
             window.onload = function() {
                 window.print();
             }
         </script>
     ```

- [ ] **Step 5: Jalankan tes untuk memverifikasi kelulusannya**
  Jalankan perintah berikut:
  `php artisan test tests/Feature/Admin/AbsensiPrintTest.php`
  *Ekspektasi:* PASS.

- [ ] **Step 6: Commit (Atomic Commit)**
  Jalankan perintah berikut:
  ```bash
  git add app/Http/Controllers/AbsensiController.php resources/views/absensi/print.blade.php tests/Feature/Admin/AbsensiPrintTest.php
  git commit -m "M04: Cetak absensi harian filter guru - update controller dan print view"
  ```

---

### Task 2: Integrasi Dinamis di Halaman Utama Absensi

**Files:**
- Modify: `resources/views/absensi/index.blade.php`
- Modify: `tests/Feature/Admin/AbsensiPrintTest.php`

**Interfaces:**
- Consumes: Perubahan dropdown `#filter-guru` di halaman index.
- Produces: Dynamic link update pada tombol cetak absensi.

- [ ] **Step 1: Tulis tes yang gagal (Failing Tests)**
  Tambahkan tes regression check berikut ke akhir file [AbsensiPrintTest.php](file:///C:/laragon/www/musik-kita-ops/tests/Feature/Admin/AbsensiPrintTest.php) untuk memverifikasi keberadaan ID element tombol cetak dan filter guru pada view utama absensi:

  ```php
      public function test_index_page_renders_print_button_and_teacher_dropdown(): void
      {
          $user = User::factory()->create();
          $user->assignRole('Admin');

          $response = $this->actingAs($user)->get(route('absensi.index'));

          $response->assertStatus(200);
          $response->assertSee('id="btn-cetak-absensi"', false);
          $response->assertSee('id="filter-guru"', false);
      }
  ```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalannya**
  Jalankan perintah:
  `php artisan test tests/Feature/Admin/AbsensiPrintTest.php`
  *Ekspektasi:* FAIL karena ID `btn-cetak-absensi` belum ditambahkan pada file view index.

- [ ] **Step 3: Tambahkan ID tombol cetak dan logika sinkronisasi JS**
  Modifikasi file [index.blade.php](file:///C:/laragon/www/musik-kita-ops/resources/views/absensi/index.blade.php):
  1. Tambahkan `id="btn-cetak-absensi"` pada tag link tombol cetak (sekitar baris 48-56):
     ```html
                     <!-- Tombol Cetak Absensi Harian -->
                     <a href="{{ route('absensi.print', ['date' => $tanggal]) }}" 
                        id="btn-cetak-absensi"
                        target="_blank" 
                        class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-800 border border-mk-border dark:border-gray-700 rounded-lg text-sm font-semibold text-mk-muted dark:text-gray-300 shadow-sm hover:bg-mk-surface dark:hover:bg-gray-700 transition ease-in-out duration-150"
                        title="Cetak Absensi Harian">
     ```
  2. Modifikasi script JavaScript filter utama (sekitar baris 140-171) untuk memperbarui URL tombol cetak secara dinamis dan jalankan fungsi filter secara langsung pada saat halaman selesai dimuat:
     ```html
         {{-- Client-side filter JS — mencakup tabel desktop DAN kartu mobile --}}
         <script>
             function applyFilter() {
                 const guruId  = document.getElementById('filter-guru').value;
                 const status  = document.getElementById('filter-status').value;
                 const murid   = document.getElementById('filter-murid').value.toLowerCase();

                 // Filter baris tabel desktop
                 document.querySelectorAll('#tabel-absensi tbody tr').forEach(row => {
                     const matchGuru   = !guruId || row.dataset.teacherId === guruId;
                     const rowStatus   = row.dataset.status;
                     const matchStatus = !status
                         || (status === 'inputted' ? rowStatus !== 'SCHEDULED' : rowStatus === status);
                     const matchMurid  = !murid || row.dataset.murid.toLowerCase().includes(murid);
                     row.style.display = (matchGuru && matchStatus && matchMurid) ? '' : 'none';
                 });

                 // Filter kartu mobile
                 document.querySelectorAll('#list-absensi-mobile .card-sesi-mobile').forEach(card => {
                     const matchGuru   = !guruId || card.dataset.teacherId === guruId;
                     const cardStatus  = card.dataset.status;
                     const matchStatus = !status
                         || (status === 'inputted' ? cardStatus !== 'SCHEDULED' : cardStatus === status);
                     const matchMurid  = !murid || card.dataset.murid.toLowerCase().includes(murid);
                     card.style.display = (matchGuru && matchStatus && matchMurid) ? '' : 'none';
                 });

                 // Perbarui URL tombol cetak absensi
                 const btnCetak = document.getElementById('btn-cetak-absensi');
                 if (btnCetak) {
                     const url = new URL(btnCetak.href);
                     if (guruId) {
                         url.searchParams.set('teacher_id', guruId);
                     } else {
                         url.searchParams.delete('teacher_id');
                     }
                     btnCetak.href = url.toString();
                 }
             }

             ['filter-guru', 'filter-status'].forEach(id =>
                 document.getElementById(id).addEventListener('change', applyFilter)
             );
             document.getElementById('filter-murid').addEventListener('input', applyFilter);

             // Pemicu awal agar link cetak tetap sinkron apabila input tersimpan otomatis oleh browser
             document.addEventListener('DOMContentLoaded', applyFilter);
         </script>
     ```

- [ ] **Step 4: Jalankan tes untuk memverifikasi kelulusannya**
  Jalankan perintah berikut:
  `php artisan test tests/Feature/Admin/AbsensiPrintTest.php`
  *Ekspektasi:* PASS.

- [ ] **Step 5: Commit (Atomic Commit)**
  Jalankan perintah berikut:
  ```bash
  git add resources/views/absensi/index.blade.php tests/Feature/Admin/AbsensiPrintTest.php
  git commit -m "M04: Cetak absensi harian filter guru - integrasi JS di index view"
  ```
