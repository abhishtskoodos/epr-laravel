<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially Paid',
        };
    }

    /** @return array<string, string[]> */
    public static function transitions(): array
    {
        return [
            self::Draft->value => [self::Submitted->value],
            self::Submitted->value => [self::UnderReview->value, self::Rejected->value],
            self::UnderReview->value => [self::Approved->value, self::Rejected->value],
            self::Approved->value => [self::Paid->value, self::PartiallyPaid->value],
            self::PartiallyPaid->value => [self::Paid->value],
            self::Rejected->value => [self::Submitted->value],
            self::Paid->value => [],
        ];
    }
}
