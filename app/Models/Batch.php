<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Batch extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'vendor_center_id',
        'scheme_id',
        'job_role_id',
        'start_date',
        'end_date',
        'seats',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'seats' => 'integer',
        ];
    }

    public function vendorCenter(): BelongsTo
    {
        return $this->belongsTo(VendorCenter::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CandidateEnrollment::class);
    }

    public function trainerAssignments(): HasMany
    {
        return $this->hasMany(TrainerAssignment::class);
    }
}
