<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Scheme extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'funding_agency',
        'scheme_type',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
        'min_attendance_percent',
        'requires_assessment',
        'requires_placement',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'requires_assessment' => 'boolean',
            'requires_placement' => 'boolean',
            'min_attendance_percent' => 'decimal:2',
        ];
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(SchemeEligibilityRule::class);
    }

    public function paymentMilestones(): HasMany
    {
        return $this->hasMany(SchemePaymentMilestone::class);
    }

    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'scheme_job_role')
            ->withPivot('payable_per_candidate')
            ->withTimestamps();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
