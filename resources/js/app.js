import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { guruJadwalCalendar } from './guru-jadwal-calendar';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

Alpine.data('guruJadwalCalendar', guruJadwalCalendar);

Alpine.start();
