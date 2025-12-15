<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Verify 2FA code during login.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        try {
            $data = decrypt($request->temp_token);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'temp_token' => ['Invalid or expired token.'],
            ]);
        }

        if ($data['expires_at'] < now()->timestamp) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token has expired. Please login again.'],
            ]);
        }

        $user = User::find($data['user_id']);

        if (!$user) {
            throw ValidationException::withMessages([
                'temp_token' => ['User not found.'],
            ]);
        }

        // Verify 2FA code
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            // Check recovery codes
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
            $codeIndex = array_search($request->code, $recoveryCodes);

            if ($codeIndex === false) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid two-factor authentication code.'],
                ]);
            }

            // Remove used recovery code
            unset($recoveryCodes[$codeIndex]);
            $user->two_factor_recovery_codes = array_values($recoveryCodes);
            $user->save();
        }

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Enable 2FA for the user.
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        // Get the secret from session or generate new one
        $secret = session('2fa_secret', $this->google2fa->generateSecretKey());

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        // Generate recovery codes
        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->toArray();

        // Enable 2FA
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        // Clear session
        session()->forget('2fa_secret');

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA for the user.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        // Verify the code
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        // Disable 2FA
        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }

    /**
     * Get QR code for 2FA setup.
     */
    public function qrcode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();

        // Store in session for verification
        session(['2fa_secret' => $secret]);

        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_code' => base64_encode($qrCodeSvg),
        ]);
    }
}
