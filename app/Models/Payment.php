<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Payment extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'amount',
        'mode',
        'utr',
        'paid_on',
        'paid_by',
        'status',
        'bank_response_json',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'datetime',
            'bank_response_json' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
