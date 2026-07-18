import assert from 'node:assert/strict';
import { guruJadwalCalendar } from '../../resources/js/guru-jadwal-calendar.js';

globalThis.window = {
    matchMedia: () => ({ matches: true }),
    location: { href: 'http://localhost/guru/jadwal' },
    history: { replaceState() {} },
};

const listeners = new Map();
const listEl = {
    classList: { add() {}, remove() {} },
    innerHTML: '<p>Sesi lama</p>',
    querySelectorAll: () => [],
    querySelector(selector) {
        return selector === '[data-action="retry-sessions"]'
            ? { addEventListener: (event, callback) => listeners.set(event, callback) }
            : null;
    },
};

globalThis.document = { getElementById: () => listEl };
globalThis.fetch = async () => ({ ok: false });

const calendar = guruJadwalCalendar({
    selectedDate: '2026-07-18',
    today: '2026-07-18',
    dates: [],
    sessionCounts: {},
    routes: { sessions: '/guru/jadwal/sessions' },
});

await calendar.fetchSessions('2026-07-21');
assert.match(listEl.innerHTML, /role="alert"/);
assert.doesNotMatch(listEl.innerHTML, /Sesi lama/);

calendar.selectedDate = '2026-07-22';
let retriedDate = null;
calendar.fetchSessions = async (date) => { retriedDate = date; };
listeners.get('click')();
assert.equal(retriedDate, '2026-07-21');

calendar.monthPickerYear = 2026;
calendar.selectedDate = '2026-07-18';
assert.equal(calendar.monthCellIsCurrent(6), true);
assert.equal(calendar.monthCellIsCurrent(5), false);

console.log('OK: retry dan state bulan aktif terverifikasi');
