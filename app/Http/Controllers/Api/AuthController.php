<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ── Email / Password ────────────────────────────────────

    public function register(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:150',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'id'            => Str::uuid(),
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'username'      => $this->generateUsername($request->full_name),
            'password'      => Hash::make($request->password),
            'auth_provider' => 'email',
            'status'        => 'active',   // actif immédiatement (vérif. email optionnelle)
            'email_verified_at' => now(),  // auto-vérifié pour l'API sans serveur mail
        ]);

        $token = $user->createToken('web-register')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء حسابك بنجاح.',
            'token'   => $token,
            'user'    => $user->only(['id', 'full_name', 'email', 'role', 'status']),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if (in_array($user->status, ['suspended', 'banned'])) {
            return response()->json(['message' => 'تم تعليق حسابك'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('web-login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج']);
    }

    public function verifyPassword(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'كلمة المرور غير صحيحة'], 422);
        }

        return response()->json(['verified' => true]);
    }

    public function me(Request $request)
    {
        return response()->json($this->userResource($request->user()));
    }

    public function update(Request $request)
    {
        $request->validate([
            'full_name'    => 'sometimes|string|max:150',
            'full_name_ar' => 'sometimes|string|max:150',
            'username'     => 'sometimes|string|max:50|unique:users,username,' . $request->user()->id,
            'bio'          => 'sometimes|string|max:500',
            'profession'   => 'sometimes|string|max:100',
        ]);

        $request->user()->update($request->only([
            'full_name', 'full_name_ar', 'username', 'bio', 'profession',
        ]));

        return response()->json($this->userResource($request->user()->fresh()));
    }

    // ── Google OAuth ─────────────────────────────────────────

    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL') . '/auth/error?message=google_failed');
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'id'                => Str::uuid(),
                'full_name'         => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'username'          => $this->generateUsername($googleUser->getName()),
                'google_avatar'     => $googleUser->getAvatar(),
                'avatar_url'        => $googleUser->getAvatar(),
                'google_token'      => $googleUser->token,
                'auth_provider'     => 'google',
                'status'            => 'active',
                'email_verified_at' => now(),
                'role'              => 'reader',
            ]
        );

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('google-oauth')->plainTextToken;

        return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function generateUsername(string $name): string
    {
        $base     = Str::slug($name, '_') ?: 'user';
        $username = $base;
        $i        = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $i++;
        }

        return $username;
    }

    private function userResource(User $user): array
    {
        return [
            'id'           => $user->id,
            'full_name'    => $user->full_name,
            'full_name_ar' => $user->full_name_ar,
            'email'        => $user->email,
            'username'     => $user->username,
            'avatar_url'   => $user->avatar_url ?? $user->google_avatar,
            'role'         => $user->role,
            'status'       => $user->status,
            'karma_points' => $user->karma_points,
            'provider'     => $user->auth_provider,
        ];
    }
}
