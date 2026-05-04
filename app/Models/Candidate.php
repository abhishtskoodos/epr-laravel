<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Models\Concerns\HasVerificationWorkflow;
use App\Support\Aadhaar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Candidate extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use HasVerificationWorkflow;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'registered',
        'registered_via' => 'self',
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'aadhaar',
        'aadhaar_token',
        'aadhaar_last4',
        'full_name',
        'gender',
        'dob',
        'category',
        'phone',
        'email',
        'address_line1',
        'city',
        'state',
        'pincode',
        'education_level',
        'status',
        'registered_via',
        'registered_by_vendor_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'status' => CandidateStatus::class,
        ];
    }

    /** @return array<string, string[]> */
    public static function statusTransitions(): array
    {
        return CandidateStatus::transitions();
    }

    /**
     * Write-only virtual: when set, tokenizes Aadhaar and stores last4.
     * The raw value is NEVER persisted.
     */
    public function setAadhaarAttribute(?string $value): void
    {
        if ($value === null) {
            return;
        }
        if (! Aadhaar::isValid($value)) {
            throw new InvalidArgumentException('Invalid Aadhaar number.');
        }
        $this->attributes['aadhaar_token'] = Aadhaar::tokenize($value);
        $this->attributes['aadhaar_last4'] = Aadhaar::last4($value);
    }

    public function maskedAadhaar(): string
    {
        return Aadhaar::mask($this->aadhaar_last4);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registeredByVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'registered_by_vendor_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CandidateEnrollment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
