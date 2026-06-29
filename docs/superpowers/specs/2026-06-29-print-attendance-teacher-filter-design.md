# Spesifikasi Desain: Filter Guru pada Cetak Absensi Harian

## 1. Pendahuluan
Dokumen ini mendefinisikan rancangan teknis untuk menambahkan fitur filter berdasarkan guru pada cetakan absensi harian studio Musik KITA. Fitur ini memungkinkan admin untuk mencetak lembar absensi khusus untuk satu guru tertentu pada tanggal yang dipilih, baik guru tersebut merupakan pengajar utama kelas maupun pengajar pengganti pada hari itu.

---

## 2. Arsitektur & Perubahan

### A. Controller (`app/Http/Controllers/AbsensiController.php`)
Perubahan dilakukan pada method `print(Request $request)`:
- Menangkap parameter `teacher_id` dari request query string: `$teacherId = $request->query('teacher_id');`
- Jika `teacher_id` diisi, lakukan kueri pencarian data guru (`Teacher::findOrFail($teacherId)`) untuk memvalidasi keberadaan guru dan menyimpan datanya pada variabel `$selectedTeacher`.
- Menambahkan filter pada kueri `ClassSession` untuk menyaring sesi yang diajar oleh guru terpilih. Logika filter:
  - Sesi di mana guru tersebut adalah guru utama (`teacher_id = $teacherId`) **DAN** tidak sedang digantikan (`substitute_teacher_id IS NULL`).
  - **ATAU** sesi di mana guru tersebut bertindak sebagai guru pengganti (`substitute_teacher_id = $teacherId`).
- Mengambil daftar semua guru yang aktif (`is_active = true`) diurutkan berdasarkan nama untuk dikirimkan ke dropdown filter halaman cetak.
- Mengirimkan variabel berikut ke view `absensi.print`:
  - `sessions` (koleksi sesi yang sudah terfilter)
  - `teachers` (koleksi seluruh guru aktif untuk dropdown)
  - `selectedTeacher` (objek guru terpilih, bernilai `null` jika mencetak semua guru)
  - `tanggal` (string format Y-MM-DD)
  - `tanggalObj` (Carbon instance)

### B. View Cetak (`resources/views/absensi/print.blade.php`)
1. **Header / Judul Cetak**:
   Mengubah kop cetak agar menampilkan informasi guru jika sedang difilter:
   - Jika `$selectedTeacher` tidak null:
     `Daftar Absensi Harian (Guru: {{ $selectedTeacher->name }}) - {{ $tanggalObj->locale('id')->translatedFormat('l, d F Y') }}`
   - Jika `$selectedTeacher` null:
     `Daftar Absensi Harian - {{ $tanggalObj->locale('id')->translatedFormat('l, d F Y') }}`
2. **Dropdown Filter Guru di Panel Kontrol**:
   Menambahkan sebuah select dropdown di dalam `.no-print-panel` agar Admin dapat mengganti filter guru langsung dari halaman pratinjau cetak:
   ```html
   <div>
       <label for="print-filter-guru" style="font-weight: 600; margin-right: 8px; font-size: 12px;">Filter Guru:</label>
       <select id="print-filter-guru" onchange="switchPrintTeacher(this.value)" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; background: white;">
           <option value="">Semua Guru</option>
           @foreach($teachers as $t)
               <option value="{{ $t->id }}" {{ request('teacher_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
           @endforeach
       </select>
   </div>
   ```
3. **Fungsi JavaScript Pengalih**:
   Menambahkan fungsi JavaScript di bagian bawah file untuk menangani pengalihan halaman saat dropdown diubah:
   ```javascript
   function switchPrintTeacher(teacherId) {
       const url = new URL(window.location.href);
       if (teacherId) {
           url.searchParams.set('teacher_id', teacherId);
       } else {
           url.searchParams.delete('teacher_id');
       }
       window.location.href = url.toString();
   }
   ```

### C. View Utama Absensi (`resources/views/absensi/index.blade.php`)
1. **Identifikasi Tombol Cetak**:
   Menambahkan atribut `id="btn-cetak-absensi"` pada tautan cetak absensi harian:
   ```html
   <a href="{{ route('absensi.print', ['date' => $tanggal]) }}" 
      id="btn-cetak-absensi"
      target="_blank" 
      class="..."
      title="Cetak Absensi Harian">
   ```
2. **Sinkronisasi Link via JavaScript**:
   Memodifikasi fungsi `applyFilter()` agar memperbarui URL pada tombol cetak secara dinamis sesuai dropdown guru terpilih:
   ```javascript
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
   ```
3. **Pemicu Awal**:
   Memastikan `applyFilter()` dipanggil saat halaman selesai dimuat agar link tombol cetak sinkron dengan data filter yang tersimpan atau terisi otomatis oleh browser pada saat pemuatan halaman.

---

## 3. Alur Logika Kueri (Eloquent)

Logika penyaringan sesi di database:
```php
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
```

---

## 4. Rencana Pengujian (Testing)

### A. Uji Coba Otomatis (Feature Test)
Membuat file test baru `tests/Feature/AbsensiPrintFilterTest.php` untuk memvalidasi fungsionalitas backend:
1. **Aksesibilitas**: Memastikan hanya user login dengan role `Owner`, `Admin`, atau `Auditor` yang dapat mengakses `/absensi/print`.
2. **Filter Tanpa Parameter**: Menguji bahwa semua sesi pada tanggal tersebut muncul di view jika parameter `teacher_id` kosong.
3. **Filter Guru Utama**: Menguji bahwa sesi di mana guru terpilih mengajar secara utama (tanpa substitute) masuk dalam hasil cetak.
4. **Filter Guru Pengganti**: Menguji bahwa sesi di mana guru terpilih bertindak sebagai guru pengganti (`substitute_teacher_id`) masuk dalam hasil cetak.
5. **Pengecualian Guru yang Digantikan**: Menguji bahwa sesi di mana guru terpilih seharusnya mengajar, namun digantikan oleh guru lain, **tidak** muncul di hasil cetak guru terpilih tersebut.

### B. Uji Coba Manual
1. Buka halaman absensi harian, pilih tanggal tertentu.
2. Ganti dropdown "Semua Guru" ke guru tertentu (misal: "THOMAS").
3. Verifikasi apakah tombol "Cetak Absensi Harian" memiliki parameter `&teacher_id=[ID_THOMAS]`.
4. Klik tombol cetak, tab baru akan terbuka dan dialog print browser muncul.
5. Batalkan dialog print, ubah filter dropdown di dalam panel cetak ke guru lain (misal: "ADI").
6. Pastikan halaman me-reload dengan data sesi milik ADI dan dialog print terbuka kembali secara otomatis.
