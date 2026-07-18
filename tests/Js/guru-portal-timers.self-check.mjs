import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { cleanupSessionHistory, createSessionActivationScheduler, startMinuteClock } from '../../resources/js/guru-portal-timers.js';

class FakeEventTarget {
    constructor() {
        this.hidden = false;
        this.listeners = new Map();
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? new Set();
        listeners.add(listener);
        this.listeners.set(type, listeners);
    }

    removeEventListener(type, listener) {
        this.listeners.get(type)?.delete(listener);
    }

    dispatch(type) {
        for (const listener of this.listeners.get(type) ?? []) listener();
    }

    count(type) {
        return this.listeners.get(type)?.size ?? 0;
    }
}

class FakeClock {
    constructor(now) {
        this.time = now;
        this.nextId = 1;
        this.timers = new Map();
    }

    now = () => this.time;

    setTimeout = (callback, delay) => {
        const id = this.nextId++;
        this.timers.set(id, { callback, at: this.time + delay });
        return id;
    };

    clearTimeout = (id) => this.timers.delete(id);

    advanceTo(time) {
        this.time = time;
        for (const [id, timer] of [...this.timers]) {
            if (timer.at <= time) {
                this.timers.delete(id);
                timer.callback();
            }
        }
    }

    get pending() {
        return this.timers.size;
    }
}

const documentTarget = new FakeEventTarget();
const windowTarget = new FakeEventTarget();
const clock = new FakeClock(1000);
const states = [];
const scheduler = createSessionActivationScheduler({
    startEpoch: 5000,
    now: clock.now,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
    documentTarget,
    windowTarget,
    onChange: (active) => states.push(active),
});

assert.deepEqual(states, [false]);
assert.equal(clock.pending, 1);
assert.equal(documentTarget.count('visibilitychange'), 1);
assert.equal(windowTarget.count('focus'), 1);

windowTarget.dispatch('focus');
windowTarget.dispatch('focus');
documentTarget.dispatch('visibilitychange');
assert.equal(clock.pending, 1);
assert.deepEqual(states, [false, false, false, false]);

documentTarget.hidden = true;
documentTarget.dispatch('visibilitychange');
assert.equal(clock.pending, 1);
assert.equal(states.length, 4);
documentTarget.hidden = false;

clock.advanceTo(5000);
assert.equal(states.at(-1), true);
assert.equal(clock.pending, 0);

windowTarget.dispatch('focus');
assert.equal(states.at(-1), true);
assert.equal(clock.pending, 0);

scheduler.destroy();
assert.equal(clock.pending, 0);
assert.equal(documentTarget.count('visibilitychange'), 0);
assert.equal(windowTarget.count('focus'), 0);

const afterClock = new FakeClock(6000);
const afterStates = [];
const afterScheduler = createSessionActivationScheduler({
    startEpoch: 5000,
    now: afterClock.now,
    setTimeout: afterClock.setTimeout,
    clearTimeout: afterClock.clearTimeout,
    documentTarget: new FakeEventTarget(),
    windowTarget: new FakeEventTarget(),
    onChange: (active) => afterStates.push(active),
});
assert.deepEqual(afterStates, [true]);
assert.equal(afterClock.pending, 0);
afterScheduler.destroy();

const minuteClock = new FakeClock(new Date(2026, 6, 18, 7, 4, 12, 345).getTime());
const rendered = [];
const stopClock = startMinuteClock({
    render: (value) => rendered.push(value),
    now: () => new Date(minuteClock.now()),
    setTimeout: minuteClock.setTimeout,
    clearTimeout: minuteClock.clearTimeout,
});
assert.deepEqual(rendered, ['07:04']);
assert.equal(minuteClock.pending, 1);
minuteClock.advanceTo(new Date(2026, 6, 18, 7, 5, 0, 0).getTime());
assert.deepEqual(rendered, ['07:04', '07:05']);
assert.equal(minuteClock.pending, 1);
stopClock();
assert.equal(minuteClock.pending, 0);

for (const closeDialog of [true, false]) {
    let aborted = 0;
    let closed = 0;
    const controller = { abort: () => aborted++ };
    const state = { open: true, historyController: controller };
    const dialog = { open: closeDialog, close: () => closed++ };

    cleanupSessionHistory(state, dialog, closeDialog);

    assert.equal(aborted, 1);
    assert.equal(state.historyController, null);
    assert.equal(state.open, false);
    assert.equal(closed, closeDialog ? 1 : 0);

    const contentBefore = 'tertutup';
    if (controller === state.historyController && state.open) state.contentHtml = 'respons terlambat';
    assert.equal(state.contentHtml ?? contentBefore, contentBefore);
}

const layout = await readFile(new URL('../../resources/views/layouts/guru.blade.php', import.meta.url), 'utf8');
const card = await readFile(new URL('../../resources/views/guru/_sesi-card.blade.php', import.meta.url), 'utf8');
const app = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
assert.doesNotMatch(layout + card + app, /setInterval|,\s*1000\)/);
assert.match(app, /startMinuteClock/);
assert.match(app, /Alpine\.data\('guruSessionActivation'/);
assert.match(card, /guruSessionActivation/);
assert.match(layout, /@cancel\.prevent="closeHistory\(\)"/);
assert.match(layout, /cleanupHistory\(closeDialog = false\)[\s\S]*cleanupSessionHistory\(this, this\.\$refs\.historyDialog, closeDialog\)/);
assert.match(layout, /@close="cleanupHistory\(\)"/);
assert.match(layout, /if \(controller !== this\.historyController \|\| !this\.open\) return;/);

console.log('OK: timer portal guru dan cleanup dialog terverifikasi');
