<?php

namespace App\Models\Concerns;

use App\Models\Verification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Adds the standardized verification workflow to a model.
 *
 * Requirements on the consuming model:
 *  - has a `status` column (string)
 *  - exposes a `statusTransitions(): array<string,string[]>` static method
 */
trait HasVerificationWorkflow
{
    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable');
    }

    /**
     * Transition the entity's status, recording an immutable verification row.
     *
     * @param  array<string,mixed>  $meta
     */
    public function transitionStatus(
        string $toStatus,
        ?int $verifierId = null,
        ?string $remarks = null,
        array $meta = [],
    ): Verification {
        $from = $this->status instanceof \BackedEnum
            ? (string) $this->status->value
            : (string) ($this->status ?? '');
        $allowed = static::statusTransitions()[$from] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Illegal transition for ".class_basename(static::class)." #{$this->getKey()}: {$from} → {$toStatus}"
            );
        }

        if (in_array($toStatus, ['rejected', 'suspended'], true) && empty($remarks)) {
            throw new InvalidArgumentException("Remarks are mandatory when transitioning to {$toStatus}.");
        }

        $verification = $this->verifications()->create([
            'from_status' => $from,
            'to_status' => $toStatus,
            'verifier_id' => $verifierId,
            'remarks' => $remarks,
            'meta' => $meta ?: null,
        ]);

        $this->status = $toStatus;
        if (in_array($toStatus, ['verified', 'active'], true) && property_exists($this, 'attributes')) {
            $this->verified_at = $this->verified_at ?? now();
            $this->verified_by = $verifierId;
        }
        $this->save();

        return $verification;
    }
}
