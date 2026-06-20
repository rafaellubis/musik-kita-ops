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
        tapDragThresholdPx: initial.tapDragThresholdPx ?? 8,
        dateBoundYears: initial.dateBoundYears ?? 2,

        loadingSessions: false,
        loadingDatesLeft: false,
        loadingDatesRight: false,
        monthPickerYear: parseInt(initial.selectedDate.slice(0, 4), 10),
        monthNames: MONTH_NAMES,
        pointerStartX: null,
        didDrag: false,
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
                this.bindSesiListActions();
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

        monthCellClasses(index) {
            const d = new Date(`${this.selectedDate}T12:00:00`);
            const isActive = d.getFullYear() === this.monthPickerYear && d.getMonth() === index;
            return isActive
                ? 'bg-secondary text-on-secondary'
                : 'bg-surface-container-low hover:bg-secondary/10 text-on-surface';
        },

        onChipPointerDown(event) {
            this.pointerStartX = event.clientX ?? event.touches?.[0]?.clientX ?? null;
            this.didDrag = false;
        },

        onChipPointerMove(event) {
            if (this.pointerStartX === null) {
                return;
            }
            const x = event.clientX ?? event.touches?.[0]?.clientX ?? this.pointerStartX;
            if (Math.abs(x - this.pointerStartX) > this.tapDragThresholdPx) {
                this.didDrag = true;
            }
        },

        onChipTap(date) {
            if (this.didDrag) {
                return;
            }
            this.selectDate(date);
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
                console.error(error);
            } finally {
                if (requestId === this.sessionRequestId) {
                    this.loadingSessions = false;
                }
            }
        },

        bindSesiListActions() {
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
            clearTimeout(this.scrollDebounce);
            this.scrollDebounce = setTimeout(() => this.checkStripEdges(), 300);
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
                    });
                } else {
                    this.dates = [...this.dates, ...newItems];
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
