<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class OtpService
{
    public function __construct(private readonly OtpChannel $channel) {}

    /**
     * Issue a new OTP, invalidating prior unconsumed codes for the same (phone, purpose).
     */
    public function issue(string $phone, string $purpose = 'login', ?string $ip = null): OtpCode
    {
        return DB::transaction(function () use ($phone, $purpose, $ip) {
            OtpCode::where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $code = (string) random_int(100000, 999999);
            $otp = OtpCode::create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes((int) config('services.otp.ttl_minutes', 5)),
                'request_ip' => $ip,
            ]);

            $this->channel->send($phone, $code, $purpose);

            return $otp;
        });
    }

    public function verify(string $phone, string $code, string $purpose = 'login'): OtpCode
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new RuntimeException('No active OTP for this phone.');
        }

        if ($otp->isExpired()) {
            throw new RuntimeException('OTP has expired. Request a new one.');
        }

        $otp->increment('attempts');

        if ($otp->attempts > (int) config('services.otp.max_attempts', 5)) {
            $otp->update(['consumed_at' => now()]);
            throw new RuntimeException('Too many attempts. Request a new OTP.');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            throw new RuntimeException('Invalid OTP.');
        }

        $otp->update(['consumed_at' => now()]);

        return $otp;
    }
}
