<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stub for MSG91 SMS OTP integration. Plug in your authkey + flow id via config/services.php.
 * Left as a TODO interface so the user can wire credentials when ready.
 */
class Msg91OtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, string $purpose): void
    {
        $authKey = (string) config('services.msg91.auth_key');
        $templateId = (string) config('services.msg91.template_id');

        if ($authKey === '' || $templateId === '') {
            Log::warning('[OTP] MSG91 not configured — falling back to log channel.', compact('phone', 'purpose'));
            (new LogOtpChannel)->send($phone, $code, $purpose);

            return;
        }

        Http::withHeaders([
            'authkey' => $authKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://control.msg91.com/api/v5/flow/', [
            'template_id' => $templateId,
            'mobiles' => '91'.ltrim($phone, '+0'),
            'otp' => $code,
        ]);
    }
}
