<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CandidatePlacement extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'candidate_enrollment_id',
        'employer_name',
        'employer_pan',
        'designation',
        'monthly_ctc',
        'placement_type',
        'placed_on',
        'verified_on',
        'proof_document_id',
        'status',
        'verified_by',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'placed_on' => 'date',
            'verified_on' => 'date',
            'monthly_ctc' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CandidateEnrollment::class, 'candidate_enrollment_id');
    }

    public function proofDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'proof_document_id');
    }
}
