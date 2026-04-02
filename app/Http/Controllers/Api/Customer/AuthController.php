<?php

namespace App\Http\Controllers\Api\Customer;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => [__('These credentials do not match our records.')],
            ]);
        }

        if ($user->isSuspended()) {
            return response()->json(['message' => __('Your account has been suspended.')], 403);
        }

        $token = $user->createToken($request->device_name, ['customer:read'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'company_name' => $user->company_name,
                'referral_code' => $user->referral_code,
            ],
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
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'company_name' => $user->company_name,
            'referral_code' => $user->referral_code,
            'is_suspended' => $user->is_suspended,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }
}
