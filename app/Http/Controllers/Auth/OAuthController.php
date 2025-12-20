<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect to OAuth provider.
     */
    public function redirect(string $provider): JsonResponse
    {
        if (!$this->isProviderSupported($provider)) {
            return response()->json([
                'success' => false,
                'message' => 'OAuth provider not supported.',
            ], 400);
        }

        // Encode provider in state parameter for callback
        $state = base64_encode(json_encode(['provider' => $provider, 'nonce' => Str::random(32)]));

        $url = Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }

    /**
     * Handle OAuth callback.
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        // Verify state parameter contains correct provider
        $state = $request->get('state');
        if ($state) {
            try {
                $stateData = json_decode(base64_decode($state), true);
                if (isset($stateData['provider']) && $stateData['provider'] !== $provider) {
                    return response()->json([
                        'success' => false,
                        'message' => 'State parameter mismatch.',
                    ], 400);
                }
            } catch (\Exception $e) {
                // If state decoding fails, continue with provided provider
            }
        }

        if (!$this->isProviderSupported($provider)) {
            return response()->json([
                'success' => false,
                'message' => 'OAuth provider not supported.',
            ], 400);
        }

        try {
            // Get OAuth user from provider
            $oauthUser = Socialite::driver($provider)->stateless()->user();

            // Find or create user
            $user = $this->findOrCreateUser($oauthUser, $provider);

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account has been disabled.',
                ], 403);
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
                'user' => $this->formatUser($user),
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OAuth authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find or create user from OAuth data.
     */
    protected function findOrCreateUser($oauthUser, string $provider): User
    {
        // Try to find existing user by OAuth provider ID
        $user = User::where('oauth_provider', $provider)
            ->where('oauth_provider_id', $oauthUser->getId())
            ->first();

        if ($user) {
            // Update OAuth tokens
            $user->update([
                'oauth_access_token' => $oauthUser->token,
                'oauth_refresh_token' => $oauthUser->refreshToken ?? null,
            ]);

            return $user;
        }

        // Try to find existing user by email
        $user = User::where('email', $oauthUser->getEmail())->first();

        if ($user) {
            // Link OAuth account to existing user
            $user->update([
                'oauth_provider' => $provider,
                'oauth_provider_id' => $oauthUser->getId(),
                'oauth_access_token' => $oauthUser->token,
                'oauth_refresh_token' => $oauthUser->refreshToken ?? null,
            ]);

            return $user;
        }

        // Create new user
        return User::create([
            'uuid' => (string) Str::uuid(),
            'username' => $this->generateUsername($oauthUser),
            'email' => $oauthUser->getEmail(),
            'password' => null, // No password for OAuth users
            'oauth_provider' => $provider,
            'oauth_provider_id' => $oauthUser->getId(),
            'oauth_access_token' => $oauthUser->token,
            'oauth_refresh_token' => $oauthUser->refreshToken ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    /**
     * Generate unique username from OAuth user data.
     */
    protected function generateUsername($oauthUser): string
    {
        $email = $oauthUser->getEmail();
        
        // Get base username from nickname, name, or email
        $baseUsername = $oauthUser->getNickname() 
            ?? $oauthUser->getName() 
            ?? ($email ? explode('@', $email)[0] : 'user');

        // Clean username (alphanumeric and underscore only)
        $baseUsername = preg_replace('/[^a-z0-9_]/', '', strtolower($baseUsername));
        $baseUsername = substr($baseUsername, 0, 32);
        
        // Ensure we have at least some characters
        if (empty($baseUsername)) {
            $baseUsername = 'user';
        }

        // Ensure uniqueness
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $suffix = $counter++;
            $username = substr($baseUsername, 0, 32 - strlen((string)$suffix)) . $suffix;
        }

        return $username;
    }

    /**
     * Check if OAuth provider is supported.
     */
    protected function isProviderSupported(string $provider): bool
    {
        $supportedProviders = config('services.oauth_providers', []);
        return in_array($provider, $supportedProviders);
    }

    /**
     * Format user for response.
     */
    protected function formatUser(User $user): array
    {
        $data = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'oauth_provider' => $user->oauth_provider,
            'two_factor_enabled' => $user->two_factor_enabled,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];

        // Include account info for regular users
        if ($user->account) {
            $data['account'] = [
                'id' => $user->account->id,
                'uuid' => $user->account->uuid,
                'domain' => $user->account->domain,
                'status' => $user->account->status,
                'package' => $user->account->package->name,
            ];
        }

        return $data;
    }
}
