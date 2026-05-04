<?php

namespace App\Models;

use App\Models\Concerns\HasVerificationWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class VendorCenter extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;
    use HasVerificationWorkflow;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending_inspection',
    ];

    /** @var list<string> */
    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'capacity',
        'infrastructure_score',
        'status',
        'inspected_at',
        'inspected_by',
        'inspection_report_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'capacity' => 'integer',
            'infrastructure_score' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return array<string, string[]> */
    public static function statusTransitions(): array
    {
        return [
            'pending_inspection' => ['inspected', 'rejected'],
            'inspected' => ['approved', 'rejected'],
            'approved' => ['active', 'suspended'],
            'active' => ['suspended'],
            'suspended' => ['active'],
            'rejected' => ['pending_inspection'],
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
