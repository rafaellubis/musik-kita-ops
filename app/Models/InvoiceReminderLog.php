<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pengiriman reminder tagihan via WhatsApp.
 */
class InvoiceReminderLog extends Model
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_FAILED  = 'FAILED';

    protected $fillable = [
        'student_id',
        'sent_by',
        'phone',
        'message_body',
        'invoice_ids',
        'pdf_filenames',
        'wablas_message_ids',
        'documents_sent',
        'documents_total',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'invoice_ids'         => 'array',
        'pdf_filenames'       => 'array',
        'wablas_message_ids'    => 'array',
        'documents_sent'      => 'integer',
        'documents_total'     => 'integer',
        'sent_at'             => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
