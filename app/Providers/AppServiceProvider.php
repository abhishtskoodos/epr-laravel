<?php

namespace App\Providers;

use App\Services\Otp\LogOtpChannel;
use App\Services\Otp\Msg91OtpChannel;
use App\Services\Otp\OtpChannel;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpChannel::class, function () {
            return match ((string) config('services.otp.channel', 'log')) {
                'msg91' => new Msg91OtpChannel,
                default => new LogOtpChannel,
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
