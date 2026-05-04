<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeEligibilityRule extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scheme_id',
        'rule_key',
        'operator',
        'value_json',
        'is_blocking',
        'display_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'is_blocking' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }
}
