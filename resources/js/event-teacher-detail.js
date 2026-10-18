/**
 * Slide-over "Detail guru pendamping" pada tab Kehadiran Guru (halaman detail event).
 * ID guru yang sedang dibuka disimpan di sessionStorage supaya panel tetap terbuka
 * setelah submit absensi murid (submit = full page reload).
 */

const STORAGE_PREFIX = 'mk-event-teacher-detail:';

export function eventTeacherDetail({ eventId, initial = null, ids = [] }) {
    const kunci = STORAGE_PREFIX + eventId;

    return {
        // Diisi setelah init(), jadi null sampai panel benar-benar dibuka.
        detail: null,

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
            this.$refs.drawer.showModal();
            document.body.classList.add('overflow-y-hidden');
            sessionStorage.setItem(kunci, String(id));
        },

        close() {
            this.$refs.drawer.close();
        },

        /** Dipanggil dari @close <dialog> — tombol Tutup maupun tombol Esc. */
        onClose() {
            this.detail = null;
            document.body.classList.remove('overflow-y-hidden');
            sessionStorage.removeItem(kunci);
        },

        /** Klik backdrop = target klik tepat di elemen <dialog>. */
        closeOnBackdrop(event) {
            if (event.target === this.$refs.drawer) this.close();
        },
    };
}
