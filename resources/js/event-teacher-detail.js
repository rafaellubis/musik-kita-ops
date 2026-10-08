/**
 * Slide-over "Detail guru pendamping" pada tab Kehadiran Guru (halaman detail event).
 * ID guru yang sedang dibuka disimpan di sessionStorage supaya panel tetap terbuka
 * setelah submit absensi murid (submit = full page reload).
 *
 * Panel memakai x-show + x-transition (bukan <dialog>) agar animasi slide-nya sama
 * dengan panel detail di halaman Kalender.
 */

const STORAGE_PREFIX = 'mk-event-teacher-detail:';

export function eventTeacherDetail({ eventId, initial = null, ids = [], names = {} }) {
    const kunci = STORAGE_PREFIX + eventId;

    return {
        // Diisi setelah init(), jadi null sampai panel benar-benar dibuka.
        detail: null,
        // Penanda buka/tutup panel. Namanya beda dari method open() supaya tidak bentrok.
        panelOpen: false,
        // Peta { id guru: { nama, instrumen } } untuk judul di header panel.
        names,

        get namaGuru() {
            return this.detail ? this.names[this.detail]?.nama ?? '' : '';
        },

        get instrumenGuru() {
            return this.detail ? this.names[this.detail]?.instrumen ?? '' : '';
        },

        init() {
            // Prioritas: buka ulang saat validasi gagal (initial), lalu sesi terakhir (sessionStorage).
            const kandidat = Number(initial) || Number(sessionStorage.getItem(kunci)) || null;
            if (kandidat && ids.includes(kandidat)) {
                this.$nextTick(() => this.open(kandidat));
            }
        },

        open(id) {
            if (!ids.includes(Number(id))) return;
            this.detail = Number(id);
            this.panelOpen = true;
            document.body.classList.add('overflow-y-hidden');
            sessionStorage.setItem(kunci, String(id));
        },

        close() {
            if (!this.panelOpen) return;
            // `detail` sengaja TIDAK dikosongkan: isi panel harus tetap terlihat
            // selama animasi keluar berjalan (±300ms).
            this.panelOpen = false;
            document.body.classList.remove('overflow-y-hidden');
            sessionStorage.removeItem(kunci);
        },

        /**
         * Esc lewat keydown window. Konsisten dengan panel Kalender yang juga bisa
         * ditutup dari mana saja. Kalau ada <dialog> lain yang sedang terbuka
         * (mis. "Ganti guru pendamping"), Esc itu milik dialog tersebut.
         */
        closeFromEscape() {
            // ponytail: focus trap native <dialog> hilang bersama <dialog>-nya. Upgrade:
            // pakai plugin focus Alpine (x-trap) kalau tab-order keluar panel jadi masalah.
            if (document.querySelector('dialog[open]')) return;
            this.close();
        },
    };
}
