<?php

namespace App\Models;

use App\Enums\VendorStatus;
use App\Models\Concerns\HasVerificationWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Vendor extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use HasVerificationWorkflow;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'country' => 'IN',
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'legal_name',
        'trade_name',
        'pan',
        'gst',
        'cin',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'entity_type',
        'status',
        'verified_at',
        'verified_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /** @return array<string, string[]> */
    public static function statusTransitions(): array
    {
        return VendorStatus::transitions();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function centers(): HasMany
    {
        return $this->hasMany(VendorCenter::class);
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(VendorKyc::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
