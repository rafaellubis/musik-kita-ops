export function invoiceReminderPanel(metadata = {}) {
    return {
        open: false, activeTab: 'new', config: {}, preview: null, messageBody: '', originalMessage: '',
        selectedIds: [], includeOthers: false, loading: false, submitting: false, errors: [], result: null,
        history: null, historyPage: 1, revision: 0, trigger: null, previousEditedMessage: '', metadata,
        expired: false, expiryTimer: null,
        destroy() { clearTimeout(this.expiryTimer); if (this.open) document.body.classList.remove('overflow-y-hidden'); },
        get dirty() { return this.messageBody !== this.originalMessage; },
        get canSend() {
            return !!(this.preview?.can_send && this.preview.preview_token && !this.loading && !this.submitting
                && !this.result && !this.expired && this.messageBody.trim() && [...this.messageBody].length <= 5000
                && Date.parse(this.preview.expires_at) > Date.now());
        },
        resizeMessage(element) {
            if (!element.getClientRects().length) return;
            element.style.height = 'auto';
            const style = getComputedStyle(element);
            element.style.height = `${element.scrollHeight + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth)}px`;
        },
        async show(config, tab = 'new') {
            if (this.submitting || (this.open && this.dirty && !window.confirm('Buang perubahan pesan yang belum dikirim?'))) return;
            this.trigger = document.activeElement;
            this.config = config; this.activeTab = tab; this.preview = null; this.history = null;
            this.messageBody = ''; this.originalMessage = ''; this.previousEditedMessage = '';
            this.errors = []; this.result = null; this.selectedIds = []; this.includeOthers = false;
            this.revision++; this.open = true;
            document.body.classList.add('overflow-y-hidden');
            this.$nextTick(() => this.$refs.close.focus());
            if (tab === 'history') await this.loadHistory(); else await this.loadPreview();
        },
        close() {
            if (this.submitting || (this.dirty && !this.result && !window.confirm('Buang perubahan pesan yang belum dikirim?'))) return;
            this.open = false; this.revision++; this.loading = false;
            clearTimeout(this.expiryTimer);
            document.body.classList.remove('overflow-y-hidden'); this.trigger?.focus();
        },
        escape() { if (this.open && !document.querySelector('dialog[open]')) this.close(); },
        trap(event) {
            const controls = [...this.$refs.panel.querySelectorAll('button:not(:disabled), input:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]')]
                .filter(el => el.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        },
        async setTab(tab) {
            if (this.submitting) return;
            this.activeTab = tab;
            if (tab === 'history') await this.loadHistory();
            else if (!this.preview) await this.loadPreview();
        },
        async request(url, body = null) {
            const response = await fetch(url, {
                method: body === null ? 'GET' : 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                ...(body === null ? {} : { body: JSON.stringify(body) }),
            });
            let data;
            try { data = await response.json(); } catch { throw new Error('Respons server tidak dapat dibaca. Periksa koneksi dan riwayat.'); }
            return { response, data };
        },
        async loadPreview(invoiceIds = null, resendLogId = null) {
            const revision = ++this.revision;
            this.loading = true; this.errors = [];
            if (this.preview) this.preview.preview_token = null;
            try {
                const body = resendLogId !== null ? { resend_log_id: resendLogId }
                    : invoiceIds === null ? {} : { invoice_ids: invoiceIds };
                const { response, data } = await this.request(this.config.preview_url, body);
                if (revision !== this.revision || !this.open) return;
                if (!response.ok) { this.errors = this.errorMessages(data); return; }
                this.preview = data; this.selectedIds = data.selected_invoices.map(i => i.id);
                this.includeOthers = this.selectedIds.length > 1;
                this.messageBody = data.message_body; this.originalMessage = data.message_body;
                this.result = null;
                clearTimeout(this.expiryTimer); this.expired = false;
                if (data.expires_at) this.expiryTimer = setTimeout(() => { this.expired = true; }, Math.max(0, Date.parse(data.expires_at) - Date.now()));
            } catch (error) {
                if (revision === this.revision) this.errors = [error.message || 'Preview gagal dimuat. Coba kembali.'];
            } finally { if (revision === this.revision) this.loading = false; }
        },
        async changeSelection(ids) {
            if (this.submitting || this.loading) return;
            if (this.dirty && !window.confirm('Mengubah pilihan tagihan akan membuat ulang pesan dan menghapus edit Anda. Lanjutkan?')) return;
            if (this.dirty) this.previousEditedMessage = this.messageBody;
            await this.loadPreview(ids);
        },
        async toggleOthers(checked) {
            if (!checked) await this.changeSelection([this.config.id]);
            else this.includeOthers = true;
        },
        async resetMessage() {
            if (this.submitting || this.loading) return;
            if (this.dirty && !window.confirm('Buat ulang pesan dari template dan hapus edit Anda?')) return;
            if (this.dirty) this.previousEditedMessage = this.messageBody;
            await this.loadPreview(this.selectedIds.length ? this.selectedIds : null);
        },
        async loadHistory(page = 1) {
            const revision = ++this.revision;
            this.loading = true; this.errors = [];
            try {
                const { response, data } = await this.request(this.config.history_url + '?page=' + page);
                if (revision !== this.revision || !this.open) return;
                if (!response.ok) { this.errors = this.errorMessages(data); return; }
                this.history = data; this.historyPage = data.current_page;
            } catch (error) {
                if (revision === this.revision) this.errors = [error.message || 'Riwayat gagal dimuat. Coba kembali.'];
            } finally { if (revision === this.revision) this.loading = false; }
        },
        async prepareResend(logId) {
            if (this.dirty && !window.confirm('Buat pesan baru dan buang edit yang belum dikirim?')) return;
            this.activeTab = 'new'; await this.loadPreview(null, logId);
        },
        async send() {
            if (!this.canSend) return;
            const confirmRepeat = !!this.preview.repeat_warnings.length;
            if (confirmRepeat && !window.confirm('Invoice sudah diingatkan hari ini. Kirim reminder lagi?')) return;
            this.submitting = true; this.errors = [];
            try {
                const { response, data } = await this.request(this.config.send_url, {
                    preview_token: this.preview.preview_token, message_body: this.messageBody, confirm_repeat: confirmRepeat,
                });
                if (!response.ok) {
                    this.errors = this.errorMessages(data);
                    if (data.code === 'repeat_confirmation_required') {
                        this.preview.repeat_warnings = [{ invoice_id: this.config.id }];
                    } else if (response.status === 409) {
                        this.previousEditedMessage = this.messageBody;
                        this.preview.preview_token = null;
                        if (data.selected_invoices) this.preview.selected_invoices = data.selected_invoices;
                    }
                    return;
                }
                this.result = data; Object.assign(this.metadata, data.metadata_by_invoice || {});
                this.preview.preview_token = null;
                this.history = null;
            } catch {
                this.result = { state: 'unknown', message: 'Hasil pengiriman belum dapat dipastikan; periksa riwayat dan WhatsApp sebelum mencoba lagi.' };
                this.preview.preview_token = null;
            } finally { this.submitting = false; }
        },
        errorMessages(data) { return Object.values(data.errors || { message: [data.message || 'Permintaan gagal. Coba kembali.'] }).flat(); },
        money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value); },
        date(value) { return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', ...(value.includes('T') ? { timeStyle: 'short' } : {}) }).format(new Date(value)) : '—'; },
        statusLabel(status) { return ({ SUCCESS: 'Dikirim ke provider', FAILED: 'Gagal', PARTIAL: 'Sebagian' })[status] || 'Belum dikirim'; },
    };
}
