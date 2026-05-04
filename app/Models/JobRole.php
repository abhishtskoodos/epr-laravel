<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobRole extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'qp_code',
        'name',
        'nsqf_level',
        'sector',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nsqf_level' => 'integer',
        ];
    }

    public function schemes(): BelongsToMany
    {
        return $this->belongsToMany(Scheme::class, 'scheme_job_role')
            ->withPivot('payable_per_candidate')
            ->withTimestamps();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
