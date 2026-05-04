<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class VendorKyc extends Model implements Auditable
{
    use AuditableTrait;
    use HasFactory;

    protected $table = 'vendor_kyc';

    /** @var list<string> */
    protected $fillable = [
        'vendor_id',
        'bank_name',
        'account_holder_name',
        'account_number_encrypted',
        'account_number_last4',
        'ifsc',
        'pan_image_document_id',
        'cancelled_cheque_document_id',
        'agreement_document_id',
        'status',
        'verified_at',
        'verified_by',
        'remarks',
    ];

    /** @var list<string> */
    protected $hidden = ['account_number_encrypted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Virtual attribute: write -> encrypt; read -> never expose plaintext outside controlled flows.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->account_number_encrypted
                ? decrypt($this->account_number_encrypted)
                : null,
            set: function (?string $value) {
                if ($value === null) {
                    return [
                        'account_number_encrypted' => null,
                        'account_number_last4' => null,
                    ];
                }

                return [
                    'account_number_encrypted' => encrypt($value),
                    'account_number_last4' => substr($value, -4),
                ];
            },
        );
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
