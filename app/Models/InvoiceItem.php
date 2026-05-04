<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class InvoiceItem extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'candidate_enrollment_id',
        'scheme_payment_milestone_id',
        'amount',
        'is_eligible',
        'eligibility_reason',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_eligible' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CandidateEnrollment::class, 'candidate_enrollment_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(SchemePaymentMilestone::class, 'scheme_payment_milestone_id');
    }
}
