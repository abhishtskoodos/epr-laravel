<?php

namespace App\Services\Otp;

interface OtpChannel
{
    /**
     * Send `$code` to `$phone`. Implementations must NOT throw on transient failures unless
     * the failure should block login completely (in which case caller will surface to the user).
     */
    public function send(string $phone, string $code, string $purpose): void;
}
