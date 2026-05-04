<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CandidateEnrollment extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'candidate_id',
        'batch_id',
        'enrolled_at',
        'dropped_at',
        'drop_reason',
        'attendance_percent',
        'ojt_completed',
        'ojt_hours',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'dropped_at' => 'datetime',
            'attendance_percent' => 'decimal:2',
            'ojt_completed' => 'boolean',
            'ojt_hours' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(CandidateAssessment::class);
    }

    public function certification(): HasOne
    {
        return $this->hasOne(CandidateCertification::class);
    }

    public function placement(): HasOne
    {
        return $this->hasOne(CandidatePlacement::class);
    }
}
