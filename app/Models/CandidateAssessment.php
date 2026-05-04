<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CandidateAssessment extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'candidate_enrollment_id',
        'assessment_agency',
        'assessor_name',
        'assessed_on',
        'theory_score',
        'practical_score',
        'viva_score',
        'total_score',
        'result',
        'status',
        'verified_at',
        'verified_by',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assessed_on' => 'date',
            'verified_at' => 'datetime',
            'theory_score' => 'decimal:2',
            'practical_score' => 'decimal:2',
            'viva_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CandidateEnrollment::class, 'candidate_enrollment_id');
    }
}
