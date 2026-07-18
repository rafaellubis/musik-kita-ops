import './bootstrap';

import Alpine from 'alpinejs';
import { guruJadwalCalendar } from './guru-jadwal-calendar';
import { cleanupSessionHistory, guruSessionActivation, startMinuteClock } from './guru-portal-timers';

window.Alpine = Alpine;
window.cleanupSessionHistory = cleanupSessionHistory;

Alpine.data('guruJadwalCalendar', guruJadwalCalendar);
Alpine.data('guruSessionActivation', guruSessionActivation);

Alpine.start();

const topbarClock = document.getElementById('topbar-clock');
if (topbarClock) startMinuteClock({ render: (value) => topbarClock.textContent = value });
