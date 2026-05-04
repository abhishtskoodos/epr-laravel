<?php

namespace App\Enums;

enum CandidateStatus: string
{
    case Registered = 'registered';
    case Training = 'training';
    case Assessed = 'assessed';
    case Certified = 'certified';
    case Placed = 'placed';
    case Dropped = 'dropped';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Linear progression. Cannot skip stages.
     *
     * @return array<string, string[]>
     */
    public static function transitions(): array
    {
        return [
            self::Registered->value => [self::Training->value, self::Dropped->value],
            self::Training->value => [self::Assessed->value, self::Dropped->value],
            self::Assessed->value => [self::Certified->value, self::Dropped->value],
            self::Certified->value => [self::Placed->value],
            self::Placed->value => [],
            self::Dropped->value => [],
        ];
    }
}
