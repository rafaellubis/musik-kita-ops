import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { guruJadwalCalendar } from './guru-jadwal-calendar';
import { eventTeacherDetail } from './event-teacher-detail';
import { enrollmentPromotionPanel } from './enrollment-promotion-panel';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

Alpine.data('guruJadwalCalendar', guruJadwalCalendar);
Alpine.data('eventTeacherDetail', eventTeacherDetail);
Alpine.data('enrollmentPromotionPanel', enrollmentPromotionPanel);

Alpine.start();
