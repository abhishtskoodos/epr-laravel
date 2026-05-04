<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Default dev channel — writes the OTP to the application log so developers can copy it during local testing.
 * Configure a real channel (Msg91OtpChannel, TwilioOtpChannel) for production.
 */
class LogOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, string $purpose): void
    {
        Log::info('[OTP] sending code', [
            'phone' => $phone,
            'purpose' => $purpose,
            'code' => $code, // only logged in non-production
        ]);
    }
}
