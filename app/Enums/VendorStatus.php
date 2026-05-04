<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingVerification => 'Pending Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /** @return array<string, string[]> */
    public static function transitions(): array
    {
        return [
            self::Draft->value => [self::PendingVerification->value],
            self::PendingVerification->value => [self::Verified->value, self::Rejected->value],
            self::Verified->value => [self::Active->value, self::Suspended->value, self::Rejected->value],
            self::Active->value => [self::Suspended->value],
            self::Suspended->value => [self::Active->value],
            self::Rejected->value => [self::PendingVerification->value],
        ];
    }
}
