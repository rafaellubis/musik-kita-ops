/**
 * Kalender jadwal portal guru — strip tanggal slide + month picker + AJAX sesi.
 */

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function guruJadwalCalendar(initial) {
    return {
        selectedDate: initial.selectedDate,
        today: initial.today,
        dates: initial.dates,
        sessionCounts: { ...initial.sessionCounts },
        routes: initial.routes,
        lazyBatchDays: initial.lazyBatchDays ?? 30,
        dragThresholdPx: initial.tapDragThresholdPx ?? 8,
        dateBoundYears: initial.dateBoundYears ?? 2,

        loadingSessions: false,
        loadingDatesLeft: false,
        loadingDatesRight: false,
        monthPickerYear: parseInt(initial.selectedDate.slice(0, 4), 10),
        monthNames: MONTH_NAMES,
        dragStartX: null,
        dragStartScrollLeft: 0,
        dragPointerId: null,
        isMouseDragging: false,
        suppressClickAfterDrag: false,
        canScrollLeft: true,
        canScrollRight: true,
        sessionRequestId: 0,
        scrollDebounce: null,
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

        get monthLabel() {
            const d = new Date(`${this.selectedDate}T12:00:00`);
            return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        },

        init() {
            this.$nextTick(() => {
                this.scrollChipToCenter(this.selectedDate, false);
                this.updateScrollControls();
                this.bindSesiListActions();
            });

            // ponytail: dengarkan perubahan sesi dan load ulang list sesi tanpa reload halaman
            window.addEventListener('session-updated', (e) => {
                const targetDate = e.detail?.date;
                if (targetDate === this.selectedDate) {
                    this.fetchSessions(this.selectedDate);
                }
            });

            window.addEventListener('popstate', () => {
                const params = new URLSearchParams(window.location.search);
                const date = params.get('date') || this.today;
                this.selectedDate = date;
                this.scrollChipToCenter(date, !this.reducedMotion);
                this.fetchSessions(date);
            });

            this.$refs.monthDialog?.addEventListener('cancel', (e) => {
                e.preventDefault();
                this.closeMonthPicker();
            });
        },

        chipClasses(d) {
            if (d.date === this.selectedDate) {
                return 'bg-secondary text-on-secondary shadow-md border-transparent';
            }
            if (d.is_today || d.date === this.today) {
                return 'bg-secondary-container text-on-secondary-container border-secondary/30';
            }
            return 'bg-surface-container-lowest border-outline-variant/20 hover:bg-secondary/5 text-on-surface';
        },

        hasSessionDot(d) {
            return (this.sessionCounts[d.date] ?? 0) > 0;
        },

        monthCellIsCurrent(index) {
            const d = new Date(`${this.selectedDate}T12:00:00`);
            return d.getFullYear() === this.monthPickerYear && d.getMonth() === index;
        },

        monthCellClasses(index) {
            return this.monthCellIsCurrent(index)
                ? 'bg-secondary text-on-secondary'
                : 'bg-surface-container-low hover:bg-secondary/10 text-on-surface';
        },

        /**
         * Tekan biasa tetap menjadi klik. Mode drag baru aktif setelah mouse
         * bergerak melewati threshold agar pemilihan tanggal tidak terganggu.
         */
        onStripPointerDown(event) {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            const strip = this.$refs.strip;
            if (!strip) {
                return;
            }

            this.suppressClickAfterDrag = false;
            this.dragStartX = event.clientX;
            this.dragStartScrollLeft = strip.scrollLeft;
            this.dragPointerId = event.pointerId;
            this.isMouseDragging = false;
        },

        onStripPointerMove(event) {
            if (
                this.dragStartX === null
                || this.dragPointerId === null
                || event.pointerId !== this.dragPointerId
            ) {
                return;
            }

            if ((event.buttons & 1) !== 1) {
                this.onStripPointerEnd(event);
                return;
            }

            const strip = this.$refs.strip;
            if (!strip) {
                return;
            }

            const deltaX = event.clientX - this.dragStartX;
            if (!this.isMouseDragging && Math.abs(deltaX) <= this.dragThresholdPx) {
                return;
            }

            if (!this.isMouseDragging) {
                this.isMouseDragging = true;
                strip.classList.add('is-mouse-dragging');
                strip.setPointerCapture?.(event.pointerId);
            }

            event.preventDefault();
            strip.scrollLeft = this.dragStartScrollLeft - deltaX;
        },

        onStripPointerEnd(event) {
            if (
                this.dragPointerId === null
                || (event.pointerId !== undefined && event.pointerId !== this.dragPointerId)
            ) {
                return;
            }

            const strip = this.$refs.strip;
            const pointerId = this.dragPointerId;
            const wasDragging = this.isMouseDragging;

            this.dragStartX = null;
            this.dragPointerId = null;
            this.isMouseDragging = false;
            strip?.classList.remove('is-mouse-dragging');

            if (
                event.type !== 'lostpointercapture'
                && strip?.hasPointerCapture?.(pointerId)
            ) {
                strip.releasePointerCapture(pointerId);
            }

            if (wasDragging) {
                this.suppressClickAfterDrag = true;
            }

            this.updateScrollControls();
        },

        /** Batalkan hanya klik yang terbit tepat setelah gesture drag. */
        onStripClickCapture(event) {
            // detail === 0 adalah aktivasi keyboard dan harus tetap diizinkan.
            if (!this.suppressClickAfterDrag || event.detail === 0) {
                this.suppressClickAfterDrag = false;
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.suppressClickAfterDrag = false;
        },

        /**
         * Roda mouse vertikal menggeser strip secara horizontal. Scroll halaman
         * tetap diteruskan ketika strip sudah mencapai ujung arah tersebut.
         */
        onStripWheel(event) {
            if (event.ctrlKey || Math.abs(event.deltaX) >= Math.abs(event.deltaY)) {
                return;
            }

            const strip = this.$refs.strip;
            if (!strip || event.deltaY === 0) {
                return;
            }

            const multiplier = event.deltaMode === 1
                ? 24
                : event.deltaMode === 2
                    ? strip.clientWidth
                    : 1;
            const delta = event.deltaY * multiplier;
            const maxScrollLeft = Math.max(0, strip.scrollWidth - strip.clientWidth);
            const nextScrollLeft = Math.min(maxScrollLeft, Math.max(0, strip.scrollLeft + delta));

            if (nextScrollLeft === strip.scrollLeft) {
                return;
            }

            event.preventDefault();
            strip.scrollLeft = nextScrollLeft;
        },

        /** Geser tampilan sekitar tujuh tanggal tanpa mengubah tanggal terpilih. */
        scrollDateStrip(direction) {
            const strip = this.$refs.strip;
            const chips = strip?.querySelectorAll('[data-date]') ?? [];
            if (!strip || chips.length === 0) {
                return;
            }

            const chipStep = chips.length > 1
                ? chips[1].offsetLeft - chips[0].offsetLeft
                : chips[0].clientWidth;
            const distance = chipStep * 7;

            strip.scrollBy({
                left: direction * distance,
                behavior: this.reducedMotion ? 'instant' : 'smooth',
            });
        },

        /** Pilih tanggal sebelahnya saat pengguna menekan panah keyboard. */
        async moveDateSelection(direction) {
            const target = this.addDays(this.selectedDate, direction);
            if (!this.isWithinBounds(target)) {
                return;
            }

            await this.ensureDateLoaded(target);
            const selectionPromise = this.selectDate(target);
            await this.$nextTick();
            this.$refs.strip
                ?.querySelector(`[data-date="${target}"]`)
                ?.focus({ preventScroll: true });
            await selectionPromise;
        },

        async selectDate(date, { scroll = true } = {}) {
            if (!date || date === this.selectedDate) {
                if (scroll) {
                    this.scrollChipToCenter(date, !this.reducedMotion);
                }
                return;
            }

            this.selectedDate = date;
            this.syncUrl(date);

            if (scroll) {
                this.scrollChipToCenter(date, !this.reducedMotion);
            }

            await this.fetchSessions(date);
            this.pruneDates();
        },

        syncUrl(date) {
            const url = new URL(window.location.href);
            url.searchParams.set('date', date);
            window.history.replaceState({}, '', url.toString());
        },

        async fetchSessions(date) {
            const requestId = ++this.sessionRequestId;
            this.loadingSessions = true;

            const listEl = document.getElementById('jadwal-sesi-list');
            if (listEl && !this.reducedMotion) {
                listEl.classList.add('guru-sesi-fade-out');
            }

            try {
                const response = await fetch(`${this.routes.sessions}?date=${encodeURIComponent(date)}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat sesi');
                }

                const data = await response.json();

                if (requestId !== this.sessionRequestId) {
                    return;
                }

                if (listEl) {
                    if (!this.reducedMotion) {
                        await this.wait(150);
                    }
                    listEl.innerHTML = data.html;
                    listEl.classList.remove('guru-sesi-fade-out');
                    if (!this.reducedMotion) {
                        listEl.classList.add('guru-sesi-fade-in');
                        setTimeout(() => listEl.classList.remove('guru-sesi-fade-in'), 200);
                    }
                    this.bindSesiListActions();
                }
            } catch (error) {
                if (requestId === this.sessionRequestId && listEl) {
                    listEl.classList.remove('guru-sesi-fade-out');
                    listEl.innerHTML = `<div role="alert" class="rounded-xl border border-error/30 bg-error-container p-4 text-center text-sm text-on-error-container">
                        <p>Gagal memuat sesi untuk tanggal ini.</p>
                        <button type="button" data-action="retry-sessions" class="mt-3 rounded-full border border-error/40 px-4 py-2 font-semibold hover:bg-error/10">Coba lagi</button>
                    </div>`;
                    this.bindSesiListActions(date);
                }
            } finally {
                if (requestId === this.sessionRequestId) {
                    this.loadingSessions = false;
                }
            }
        },

        bindSesiListActions(retryDate = this.selectedDate) {
            const listEl = document.getElementById('jadwal-sesi-list');
            if (!listEl) {
                return;
            }
            listEl.querySelectorAll('[data-action="go-today"]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.goToday();
                });
            });
            listEl.querySelector('[data-action="retry-sessions"]')?.addEventListener('click', () => {
                this.fetchSessions(retryDate);
            });
        },

        scrollChipToCenter(date, smooth = true) {
            const strip = this.$refs.strip;
            const chip = strip?.querySelector(`[data-date="${date}"]`);
            if (!strip || !chip) {
                return;
            }

            const offset = chip.offsetLeft - (strip.clientWidth / 2) + (chip.clientWidth / 2);
            strip.scrollTo({
                left: Math.max(0, offset),
                behavior: smooth && !this.reducedMotion ? 'smooth' : 'instant',
            });
        },

        onStripScroll() {
            this.updateScrollControls();
            clearTimeout(this.scrollDebounce);
            this.scrollDebounce = setTimeout(() => this.checkStripEdges(), 300);
        },

        updateScrollControls() {
            const strip = this.$refs.strip;
            if (!strip || this.dates.length === 0) {
                this.canScrollLeft = false;
                this.canScrollRight = false;
                return;
            }

            const maxScrollLeft = Math.max(0, strip.scrollWidth - strip.clientWidth);
            const hasEarlierDates = this.isWithinBounds(this.addDays(this.dates[0].date, -1));
            const hasLaterDates = this.isWithinBounds(this.addDays(this.dates[this.dates.length - 1].date, 1));

            this.canScrollLeft = strip.scrollLeft > 1 || hasEarlierDates;
            this.canScrollRight = strip.scrollLeft < maxScrollLeft - 1 || hasLaterDates;
        },

        async checkStripEdges() {
            const strip = this.$refs.strip;
            if (!strip || this.dates.length === 0) {
                return;
            }

            const { scrollLeft, scrollWidth, clientWidth } = strip;

            if (scrollLeft < 120 && !this.loadingDatesLeft) {
                await this.loadDatesAtEdge('left');
            }

            if (scrollWidth - scrollLeft - clientWidth < 120 && !this.loadingDatesRight) {
                await this.loadDatesAtEdge('right');
            }
        },

        async loadDatesAtEdge(direction) {
            if (this.dates.length === 0) {
                return;
            }

            const flag = direction === 'left' ? 'loadingDatesLeft' : 'loadingDatesRight';
            this[flag] = true;

            try {
                const earliest = this.dates[0].date;
                const latest = this.dates[this.dates.length - 1].date;

                let from;
                let to;

                if (direction === 'left') {
                    to = this.addDays(earliest, -1);
                    from = this.addDays(to, -(this.lazyBatchDays - 1));
                } else {
                    from = this.addDays(latest, 1);
                    to = this.addDays(from, this.lazyBatchDays - 1);
                }

                if (!this.isWithinBounds(from) || !this.isWithinBounds(to)) {
                    return;
                }

                const strip = this.$refs.strip;
                const prevScrollWidth = strip?.scrollWidth ?? 0;

                const payload = await this.fetchDateRange(from, to);
                if (!payload.length) {
                    return;
                }

                payload.forEach((d) => {
                    if (d.has_sessions) {
                        this.sessionCounts[d.date] = 1;
                    }
                });

                const existing = new Set(this.dates.map((d) => d.date));
                const newItems = payload.filter((d) => !existing.has(d.date));

                if (direction === 'left') {
                    this.dates = [...newItems, ...this.dates];
                    this.$nextTick(() => {
                        if (strip) {
                            strip.scrollLeft += strip.scrollWidth - prevScrollWidth;
                        }
                        this.updateScrollControls();
                    });
                } else {
                    this.dates = [...this.dates, ...newItems];
                    this.$nextTick(() => this.updateScrollControls());
                }
            } finally {
                this[flag] = false;
            }
        },

        async fetchDateRange(from, to) {
            const response = await fetch(
                `${this.routes.dates}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            return data.dates ?? [];
        },

        async ensureDateLoaded(date) {
            if (this.dates.some((d) => d.date === date)) {
                return;
            }

            const from = this.addDays(date, -this.lazyBatchDays);
            const to = this.addDays(date, this.lazyBatchDays);
            const payload = await this.fetchDateRange(from, to);

            payload.forEach((d) => {
                if (d.has_sessions) {
                    this.sessionCounts[d.date] = 1;
                }
            });

            const existing = new Set(this.dates.map((d) => d.date));
            const merged = [...this.dates, ...payload.filter((d) => !existing.has(d.date))];
            merged.sort((a, b) => a.date.localeCompare(b.date));
            this.dates = merged;
        },

        pruneDates() {
            const anchor = this.selectedDate;
            const maxDistance = 120;
            this.dates = this.dates.filter((d) => Math.abs(this.dayDiff(anchor, d.date)) <= maxDistance);
        },

        openMonthPicker() {
            const d = new Date(`${this.selectedDate}T12:00:00`);
            this.monthPickerYear = d.getFullYear();
            this.$refs.monthDialog?.showModal();
        },

        closeMonthPicker() {
            this.$refs.monthDialog?.close();
        },

        shiftPickerYear(delta) {
            this.monthPickerYear += delta;
        },

        async pickMonth(monthIndex) {
            const now = new Date();
            const isCurrentMonth = this.monthPickerYear === now.getFullYear()
                && monthIndex === now.getMonth();

            const target = isCurrentMonth
                ? this.today
                : `${this.monthPickerYear}-${String(monthIndex + 1).padStart(2, '0')}-01`;

            this.closeMonthPicker();
            await this.ensureDateLoaded(target);
            await this.selectDate(target);
        },

        async goToday() {
            this.closeMonthPicker();
            await this.ensureDateLoaded(this.today);
            await this.selectDate(this.today);
        },

        addDays(dateStr, days) {
            const d = new Date(`${dateStr}T12:00:00`);
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        },

        dayDiff(from, to) {
            const a = new Date(`${from}T12:00:00`);
            const b = new Date(`${to}T12:00:00`);
            return Math.round((b - a) / (1000 * 60 * 60 * 24));
        },

        isWithinBounds(dateStr) {
            const d = new Date(`${dateStr}T12:00:00`);
            const min = new Date(`${this.today}T12:00:00`);
            min.setFullYear(min.getFullYear() - this.dateBoundYears);
            const max = new Date(`${this.today}T12:00:00`);
            max.setFullYear(max.getFullYear() + this.dateBoundYears);
            return d >= min && d <= max;
        },

        wait(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        },
    };
}
