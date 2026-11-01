export function enrollmentPromotionPanel(options) {
    return {
        open: false, config: {}, form: {}, preview: null, errors: [], loading: false, submitting: false,
        revision: 0, trigger: null, options,
        init() {
            if (options.restore) {
                const saved = sessionStorage.getItem('mk-promotion-form');
                if (saved) {
                    try { this.show(JSON.parse(saved)); } catch { sessionStorage.removeItem('mk-promotion-form'); }
                }
                this.errors = options.errors || [];
            } else {
                sessionStorage.removeItem('mk-promotion-form');
            }
        },
        show(config) {
            this.trigger = document.activeElement;
            this.config = config;
            this.form = { package_id: config.package_id, effective_date: config.effective_date,
                teacher_id: config.teacher_id || '', room_id: config.room_id || '', day_of_week: config.day_of_week ?? '',
                start_time: config.start_time || '', evaluation_note: config.evaluation_note || '' };
            this.preview = null;
            this.errors = [];
            this.open = true;
            this.submitting = false;
            this.revision++;
            document.body.classList.add('overflow-y-hidden');
            this.$nextTick(() => this.$refs.close.focus());
        },
        close() {
            if (this.submitting) return;
            this.open = false;
            this.revision++;
            this.loading = false;
            document.body.classList.remove('overflow-y-hidden');
            this.trigger?.focus();
        },
        escape() {
            if (!document.querySelector('dialog[open]')) this.close();
        },
        trap(event) {
            const controls = [...this.$refs.panel.querySelectorAll('button:not([disabled]), input:not([type="hidden"]), select, textarea, a[href]')]
                .filter(el => el.getClientRects().length > 0);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        },
        changed() { this.preview = null; this.revision++; this.loading = false; },
        get teachers() { return options.teachers.filter(t => t.instruments.includes(this.config.instrument_id)); },
        get rooms() { return options.rooms.filter(r => (r.supported_instruments || []).includes(this.config.instrument_name)); },
        money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value); },
        async inspect() {
            if (!this.$refs.form.reportValidity()) return;
            const revision = ++this.revision;
            this.loading = true;
            this.errors = [];
            this.preview = null;
            try {
                const response = await fetch(this.config.preview_url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.$refs.form.querySelector('[name="_token"]').value },
                    body: JSON.stringify(this.form),
                });
                const result = await response.json();
                if (revision !== this.revision || !this.open) return;
                if (!response.ok) {
                    this.errors = Object.values(result.errors || { error: [result.message || 'Pratinjau gagal. Coba kembali.'] }).flat();
                    return;
                }
                this.preview = result;
            } catch {
                if (revision === this.revision) this.errors = ['Pratinjau gagal dimuat. Periksa koneksi lalu coba kembali.'];
            } finally {
                if (revision === this.revision) this.loading = false;
            }
        },
        submit(event) {
            if (!this.preview || this.submitting) { event.preventDefault(); return; }
            sessionStorage.setItem('mk-promotion-form', JSON.stringify({ ...this.config, ...this.form }));
            this.submitting = true;
        },
    };
}
