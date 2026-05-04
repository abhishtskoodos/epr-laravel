<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Trainer = 'trainer';
    case Candidate = 'candidate';
    case Inspector = 'inspector';
    case Finance = 'finance';

    /** @return string[] */
    public static function all(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
