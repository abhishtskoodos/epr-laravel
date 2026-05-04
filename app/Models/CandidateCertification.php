<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CandidateCertification extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'candidate_enrollment_id',
        'certificate_no',
        'issued_on',
        'valid_until',
        'certificate_document_id',
        'status',
        'verified_at',
        'verified_by',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CandidateEnrollment::class, 'candidate_enrollment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'certificate_document_id');
    }
}
