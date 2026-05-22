<?php

namespace App\Http\Requests;

use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ============= IDENTITAS =============
            'full_name'           => 'required|string|max:100',
            'nickname'            => 'nullable|string|max:30',
            'gender'              => 'required|in:L,P',
            'birth_date'          => 'nullable|date|before_or_equal:today',

            // ============= KONTAK =============
            'phone'               => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'email'               => 'nullable|email|max:100',
            'address'             => 'nullable|string',
            'notes'               => 'nullable|string',

            // ============= PARENT =============
            'parent_name'         => 'nullable|string|max:100',
            'parent_phone'        => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'parent_email'        => 'nullable|email|max:100',
            'parent_relationship' => 'nullable|in:Ayah,Ibu,Wali',

            // ============= STATUS BELAJAR =============
            'status'              => 'required|in:Calon,Trial,Aktif',
            'package_id'          => 'nullable|exists:packages,id',
            'assigned_teacher_id' => 'nullable|exists:teachers,id',
            // assigned_room_id, preferred_day, preferred_time sudah dihapus dari schema students
            // Ruangan dikelola via schedules (tab Kelas di halaman Detail)
            'trial_date'          => 'nullable|date|after:now',

            // ============= HYBRID: ALASAN SKIP TRIAL =============
            // Wajib kalau status = Aktif (validasi di withValidator).
            'reason_code'         => 'nullable|in:walk_in,migrasi,reaktivasi,lulus_kids',
            'skip_trial_reason'   => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'           => 'Nama lengkap wajib diisi.',
            'full_name.max'                => 'Nama lengkap maksimal 100 karakter.',
            'gender.required'              => 'Jenis kelamin wajib dipilih.',
            'gender.in'                    => 'Jenis kelamin harus L atau P.',
            'birth_date.before_or_equal'   => 'Tanggal lahir tidak boleh di masa depan.',
            'email.email'                  => 'Format email tidak valid.',
            'phone.regex'                  => 'Nomor HP hanya boleh angka, +, -, spasi, dan kurung.',
            'parent_email.email'           => 'Format email orang tua tidak valid.',
            'parent_phone.regex'           => 'Nomor HP orang tua hanya boleh angka.',
            'status.required'              => 'Status wajib dipilih.',
            'status.in'                    => 'Status hanya boleh Calon, Trial, atau Aktif.',
            'package_id.exists'            => 'Paket yang dipilih tidak valid.',
            'assigned_teacher_id.exists'   => 'Guru yang dipilih tidak valid.',
            'trial_date.after'             => 'Jadwal trial harus setelah sekarang.',
            'skip_trial_reason.max'        => 'Alasan maksimal 500 karakter.',
            'reason_code.in'               => 'Kode alasan tidak valid.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            // ============= RULE 1: Trial wajib trial_date =============
            if ($this->status === 'Trial' && !$this->trial_date) {
                $validator->errors()->add(
                    'trial_date',
                    'Jadwal trial wajib diisi untuk status Trial.'
                );
            }

            // ============= RULE 2: Aktif wajib package =============
            if ($this->status === 'Aktif' && !$this->package_id) {
                $validator->errors()->add(
                    'package_id',
                    'Paket wajib diisi untuk status Aktif.'
                );
            }

            // ============= RULE 3: Aktif wajib teacher =============
            if ($this->status === 'Aktif' && !$this->assigned_teacher_id) {
                $validator->errors()->add(
                    'assigned_teacher_id',
                    'Guru wajib diisi untuk status Aktif.'
                );
            }

            // ============= RULE 4: Aktif wajib alasan (Hybrid) =============
            if ($this->status === 'Aktif' && !$this->skip_trial_reason) {
                $validator->errors()->add(
                    'skip_trial_reason',
                    'Alasan langsung Aktif wajib diisi (mis: walk-in confident, migrasi data, lulus Kids Class).'
                );
            }

            // ============= RULE 4b: Aktif wajib reason_code (Hybrid v1.1) =============
            if ($this->status === 'Aktif' && !$this->reason_code) {
                $validator->errors()->add(
                    'reason_code',
                    'Pilih kode alasan: walk_in / migrasi / reaktivasi / lulus_kids.'
                );
            }

            // ============= RULE 5-7: Validasi Kids Class =============
            if ($this->package_id) {
                $package = Package::find($this->package_id);

                // ⚠ KRITIS: Pakai enum value yang BENAR dari schema M01
                $isKidsClass = $package && in_array(
                    $package->class_type,
                    ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE']
                );

                // Rule 5: Kids Class wajib parent_name
                if ($isKidsClass && !$this->parent_name) {
                    $validator->errors()->add(
                        'parent_name',
                        'Nama orang tua wajib untuk Kids Class.'
                    );
                }

                // Rule 6: Kids Class wajib parent_phone
                if ($isKidsClass && !$this->parent_phone) {
                    $validator->errors()->add(
                        'parent_phone',
                        'No. HP orang tua wajib untuk Kids Class.'
                    );
                }

                // Rule 7: Kids Class umur 4-<5 tahun (BR-10.1 revisi)
                if ($isKidsClass && $this->birth_date) {
                    $age = \Carbon\Carbon::parse($this->birth_date)->age;
                    if ($age < 4 || $age >= 5) {
                        $validator->errors()->add(
                            'birth_date',
                            'Kids Class hanya untuk anak usia 4 sampai kurang dari 5 tahun.'
                        );
                    }
                }
            }
        });
    }
}