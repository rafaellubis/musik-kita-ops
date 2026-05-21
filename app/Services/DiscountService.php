<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Logika bisnis diskon per item invoice (M05).
 *
 * Diskon disimpan sebagai InvoiceItem baru dengan item_code='DISKON',
 * amount negatif, dan parent_item_id menunjuk ke item yang didiskon.
 * Maks 1 diskon per item dijaga di service ini, bukan di database constraint.
 */
class DiscountService
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * Beri atau update diskon pada sebuah item invoice.
     * Idempotent: jika diskon sudah ada → diupdate. Jika belum → dibuat baru.
     *
     * @throws \InvalidArgumentException jika validasi bisnis gagal
     */
    public function applyDiscount(
        InvoiceItem $item,
        string $type,
        int $value,
        string $reason,
        User $by,
    ): InvoiceItem {
        $invoice = $item->invoice;

        // Tidak boleh menambah diskon pada invoice yang sudah lunas atau di-void
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID])) {
            throw new \InvalidArgumentException('Diskon tidak bisa ditambahkan pada invoice yang sudah PAID atau VOID.');
        }

        // Tidak bisa mendiskon item yang sudah merupakan item diskon
        if ($item->isDiscount()) {
            throw new \InvalidArgumentException('Tidak bisa memberi diskon pada item diskon.');
        }

        // Validasi nilai diskon berdasarkan tipe
        if ($type === InvoiceItem::DISCOUNT_TYPE_NOMINAL) {
            if ($value <= 0 || $value >= $item->amount) {
                throw new \InvalidArgumentException('Nilai diskon nominal harus lebih dari 0 dan kurang dari harga item.');
            }
        } elseif ($type === InvoiceItem::DISCOUNT_TYPE_PERCENT) {
            if ($value < 1 || $value > 100) {
                throw new \InvalidArgumentException('Persentase diskon harus antara 1 dan 100.');
            }
        } else {
            throw new \InvalidArgumentException('Tipe diskon tidak valid. Gunakan NOMINAL atau PERCENT.');
        }

        // Hitung amount diskon (selalu negatif karena mengurangi total)
        $calculatedAmount = $type === InvoiceItem::DISCOUNT_TYPE_NOMINAL
            ? -$value
            : -intdiv($item->amount * $value, 100);

        return DB::transaction(function () use ($item, $type, $value, $reason, $by, $calculatedAmount) {
            // Cek apakah sudah ada diskon untuk item ini
            $existing = $item->discountItem()->first();

            $data = [
                'invoice_id'      => $item->invoice_id,
                'parent_item_id'  => $item->id,
                'item_code'       => 'DISKON',
                'description'     => $reason,
                'amount'          => $calculatedAmount,
                'discount_type'   => $type,
                'discount_value'  => $value,
                'discount_reason' => $reason,
                'added_by'        => $by->id,
            ];

            $oldValues = null;

            if ($existing) {
                // Simpan snapshot lama untuk audit log
                $oldValues = [
                    'discount_type'   => $existing->discount_type,
                    'discount_value'  => $existing->discount_value,
                    'discount_reason' => $existing->discount_reason,
                    'amount'          => $existing->amount,
                ];
                $existing->update($data);
                $discountItem = $existing->fresh();
            } else {
                // Buat item diskon baru
                $discountItem = InvoiceItem::create($data);
            }

            // Recalculate total invoice dari sum semua items (termasuk diskon negatif)
            $this->invoiceService->recalcStatus($item->invoice()->first());

            // Catat ke audit log
            AuditLog::record(
                action: AuditLog::ACTION_UPDATE,
                entity: $item->invoice,
                entityLabel: "Invoice {$item->invoice->invoice_number}",
                oldValues: $oldValues,
                newValues: [
                    'item_id'           => $item->id,
                    'item_code'         => $item->item_code,
                    'discount_type'     => $type,
                    'discount_value'    => $value,
                    'discount_reason'   => $reason,
                    'calculated_amount' => $calculatedAmount,
                ],
                notes: $existing ? 'Edit diskon item invoice' : 'Beri diskon item invoice',
            );

            return $discountItem;
        });
    }

    /**
     * Hapus diskon dari item invoice.
     *
     * @throws \InvalidArgumentException jika validasi bisnis gagal
     */
    public function removeDiscount(InvoiceItem $discountItem, User $by): void
    {
        // Pastikan item yang dihapus benar-benar item diskon
        if (!$discountItem->isDiscount()) {
            throw new \InvalidArgumentException('Item yang dihapus bukan item diskon.');
        }

        $invoice = $discountItem->invoice;

        // Tidak bisa hapus diskon dari invoice yang sudah lunas atau di-void
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID])) {
            throw new \InvalidArgumentException('Diskon tidak bisa dihapus dari invoice yang sudah PAID atau VOID.');
        }

        DB::transaction(function () use ($discountItem) {
            // Snapshot data diskon untuk audit log
            $snapshot = [
                'item_id'         => $discountItem->parent_item_id,
                'discount_type'   => $discountItem->discount_type,
                'discount_value'  => $discountItem->discount_value,
                'discount_reason' => $discountItem->discount_reason,
                'amount'          => $discountItem->amount,
            ];

            $invoice = $discountItem->invoice;

            // Hapus item diskon
            $discountItem->delete();

            // Recalculate total invoice setelah diskon dihapus
            $this->invoiceService->recalcStatus($invoice);

            // Catat ke audit log
            AuditLog::record(
                action: AuditLog::ACTION_UPDATE,
                entity: $invoice,
                entityLabel: "Invoice {$invoice->invoice_number}",
                oldValues: $snapshot,
                newValues: ['removed_discount' => $snapshot],
                notes: 'Hapus diskon item invoice',
            );
        });
    }
}
