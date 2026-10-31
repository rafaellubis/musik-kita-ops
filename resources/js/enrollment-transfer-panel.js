import { enrollmentPromotionPanel } from './enrollment-promotion-panel';

export function enrollmentTransferPanel(options) {
    // Gunakan pola panel yang sama untuk fokus, pratinjau, dan perlindungan respons basi.
    const panel = enrollmentPromotionPanel(options);
    return {
        ...panel,
        init() {
            const saved = sessionStorage.getItem('mk-transfer-form');
            if (options.restore && saved) {
                try { this.show(JSON.parse(saved)); } catch { sessionStorage.removeItem('mk-transfer-form'); }
                this.errors = options.errors || [];
            } else {
                sessionStorage.removeItem('mk-transfer-form');
            }
        },
        show(config) {
            panel.show.call(this, config);
            this.form = {
                teacher_id: config.target_teacher_id || '', room_id: config.room_id || '',
                day_of_week: config.day_of_week ?? '', start_time: config.start_time || '',
                effective_date: config.effective_date, reason: config.reason || '',
            };
        },
        get teachers() {
            return options.teachers.filter(t => t.id !== this.config.source_teacher_id && t.instruments.includes(this.config.instrument_id));
        },
        get rooms() {
            return options.rooms.filter(r => (r.supported_instruments || []).includes(this.config.instrument_name));
        },
        get endTime() {
            if (!this.form.start_time) return '—';
            const [hours, minutes] = this.form.start_time.split(':').map(Number);
            const end = hours * 60 + minutes + Number(this.config.duration_min);
            return end < 1440 ? `${String(Math.floor(end / 60)).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}` : 'Melewati tengah malam';
        },
        describe(assignment) {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const teacher = options.teachers.find(t => t.id === assignment.teacher_id)?.name || this.config.source_teacher_name;
            const room = options.rooms.find(r => r.id === assignment.room_id)?.name || 'Tanpa ruangan';
            return `${teacher} · ${days[assignment.day_of_week]} ${assignment.start_time.slice(0, 5)}–${assignment.end_time.slice(0, 5)} · ${room}`;
        },
        submit(event) {
            if (!this.preview || this.submitting) { event.preventDefault(); return; }
            sessionStorage.setItem('mk-transfer-form', JSON.stringify({ ...this.config, ...this.form, target_teacher_id: this.form.teacher_id }));
            this.submitting = true;
        },
    };
}
