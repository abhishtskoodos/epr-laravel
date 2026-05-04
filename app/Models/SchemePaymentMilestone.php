<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemePaymentMilestone extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scheme_id',
        'key',
        'label',
        'percent',
        'amount',
        'requires_status',
        'display_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
            'display_order' => 'integer',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }
}
