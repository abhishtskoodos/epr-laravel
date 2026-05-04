<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasVerificationWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use HasVerificationWorkflow;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'invoice_no',
        'vendor_id',
        'scheme_id',
        'period_from',
        'period_to',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'paid_at',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** @return array<string, string[]> */
    public static function statusTransitions(): array
    {
        return InvoiceStatus::transitions();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = (float) $this->items()->sum('amount');
        $this->tax_amount = round($this->subtotal * 0.18, 2); // 18% GST default; configurable per scheme
        $this->total = $this->subtotal + $this->tax_amount;
        $this->save();
    }
}
