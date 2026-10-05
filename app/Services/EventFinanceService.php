<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\Invoice;

class EventFinanceService
{
    /** @return array{target:int,billed:int,collected:int,outstanding:int,estimated_honor:int,final_honor:int,cash_margin:int,invoice_counts:array<string,int>} */
    public function summary(Event $event): array
    {
        $participantTotals = $event->participants()
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->selectRaw('COALESCE(SUM(snapshot_mc_fee), 0) as target, COALESCE(SUM(snapshot_honor_amount), 0) as estimated_honor')
            ->first();
        $invoices = $event->invoices()
            ->notVoid()
            ->withSum('validPayments', 'amount')
            ->get();
        $billed = (int) $invoices->sum('total_amount');
        $collected = (int) $invoices->sum('valid_payments_sum_amount');
        $finalHonor = (int) $event->honorEntries()
            ->where('status', EventHonorEntry::STATUS_POSTED)
            ->sum('amount');
        $invoiceCounts = [
            Invoice::STATUS_UNPAID => 0,
            Invoice::STATUS_PARTIAL => 0,
            Invoice::STATUS_PAID => 0,
        ];

        foreach ($invoices->countBy('status') as $status => $count) {
            $invoiceCounts[$status] = (int) $count;
        }

        return [
            'target' => (int) $participantTotals->target,
            'billed' => $billed,
            'collected' => $collected,
            'outstanding' => $billed - $collected,
            'estimated_honor' => (int) $participantTotals->estimated_honor,
            'final_honor' => $finalHonor,
            'cash_margin' => $collected - $finalHonor,
            'invoice_counts' => $invoiceCounts,
        ];
    }
}
