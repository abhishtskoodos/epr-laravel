<?php

namespace App\Models;

use App\Enums\TrainerStatus;
use App\Models\Concerns\HasVerificationWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Trainer extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use HasVerificationWorkflow;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'full_name',
        'pan',
        'aadhaar_token',
        'aadhaar_last4',
        'qualification',
        'tot_certificate_no',
        'experience_years',
        'phone',
        'email',
        'status',
        'verified_at',
        'verified_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TrainerStatus::class,
            'verified_at' => 'datetime',
            'experience_years' => 'decimal:1',
        ];
    }

    /** @return array<string, string[]> */
    public static function statusTransitions(): array
    {
        return [
            'draft' => ['pending_verification'],
            'pending_verification' => ['verified', 'rejected'],
            'verified' => ['active', 'suspended', 'rejected'],
            'active' => ['suspended'],
            'suspended' => ['active'],
            'rejected' => ['pending_verification'],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainerAssignment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
