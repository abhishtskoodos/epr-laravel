<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthController extends Controller
{
    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'purpose' => ['nullable', 'in:login,phone_verify'],
        ]);

        $otp->issue($data['phone'], $data['purpose'] ?? 'login', $request->ip());

        return response()->json([
            'message' => 'OTP issued. Check your SMS.',
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'code' => ['required', 'regex:/^\d{4,8}$/'],
        ]);

        try {
            $otp->verify($data['phone'], $data['code']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::firstOrCreate(
                ['phone' => $data['phone']],
                ['name' => 'User '.$data['phone'], 'is_active' => true],
            );
            if ($user->wasRecentlyCreated) {
                $user->assignRole('candidate');
            }
            $user->forceFill([
                'phone_verified_at' => now(),
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            return $user;
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'phone'])
                + ['roles' => $user->getRoleNames(), 'permissions' => $user->getAllPermissions()->pluck('name')],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'is_active', 'last_login_at']),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
