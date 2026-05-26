<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Holiday;
use App\Models\HonorSlip;
use App\Models\PayrollConfig;

class EventHonorService
{
    private const DEFAULT_PENDAMPING_HONOR = 250000;

    /**
     * Proses honor guru pendamping saat event di-mark COMPLETED.
     *
     * Melakukan dua hal:
     * 1. Cek keberadaan Holiday Internal di tanggal event (warning jika tidak ada)
     * 2. Inject honor H_PENDAMPING ke slip bulan event untuk setiap guru pendamping unik
     *
     * @return array{slips_updated: int, slips_skipped: int, holiday_warning: bool}
     */
    public function processEventCompletion(Event $event, int $createdBy): array
    {
        $result = [
            'slips_updated'   => 0,
            'slips_skipped'   => 0,
            'holiday_warning' => false,
        ];

        // Cek apakah ada Holiday Internal di tanggal event
        $hasHoliday = Holiday::where('date', $event->event_date)
            ->where('type', 'Internal')
            ->exists();

        if (!$hasHoliday) {
            $result['holiday_warning'] = true;
        }

        // Baca nominal honor dari PayrollConfig
        $config      = PayrollConfig::where('scenario_code', 'H_PENDAMPING')
            ->where('is_active', true)
            ->first();
        $honorAmount = (int) ($config?->value_or_formula ?? self::DEFAULT_PENDAMPING_HONOR);

        // Ambil semua guru pendamping unik dari peserta event
        $teacherIds = $event->participants()
            ->whereNotNull('accompanying_teacher_id')
            ->pluck('accompanying_teacher_id')
            ->unique();

        if ($teacherIds->isEmpty()) {
            return $result;
        }

        $month = $event->event_date->month;
        $year  = $event->event_date->year;

        foreach ($teacherIds as $teacherId) {
            $slip = HonorSlip::where('teacher_id', $teacherId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (!$slip) {
                $slip = new HonorSlip([
                    'slip_number'     => $this->generateSlipNumber($year, $month),
                    'teacher_id'      => $teacherId,
                    'month'           => $month,
                    'year'            => $year,
                    'base_honor'      => 0,
                    'event_honor'     => 0,
                    'transport_honor' => 0,
                    'other_honor'     => 0,
                    'status'          => HonorSlip::STATUS_DRAFT,
                    'created_by'      => $createdBy,
                ]);
            }

            if ($slip->isLocked()) {
                $result['slips_skipped']++;
                continue;
            }

            $slip->event_honor = ($slip->event_honor ?? 0) + $honorAmount;
            $slip->event_honor_note = trim(
                ($slip->event_honor_note ? $slip->event_honor_note . ' | ' : '')
                . "Pendamping {$event->name}"
            );
            $slip->recalcTotal();
            $slip->save();

            $result['slips_updated']++;
        }

        return $result;
    }

    /**
     * Generate nomor slip format SLIP/YYYY/MM/NNNN (reset per bulan).
     * Duplikasi dari HonorCalculationService — disengaja agar dua service tidak saling bergantung.
     */
    private function generateSlipNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = HonorSlip::where('slip_number', 'like', "SLIP/{$year}/{$monthStr}/%")
            ->orderBy('slip_number', 'desc')
            ->value('slip_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('SLIP/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }
}
