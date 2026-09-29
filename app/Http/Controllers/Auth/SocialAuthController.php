<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialUser;

use App\Models\User;

class SocialAuthController extends Controller
{
    private const PROVIDERS = [
        'google' => [
            'column' => 'google_id',
            'scopes' => ['openid','profile','email'],
        ],

        'facebook' => [
            'column' => 'facebook_id',
            'scopes' => ['email'],
        ],

        'github' => [
            'column' => 'github_id',
            'scopes' => ['user:email'],
        ],
    ];

    public function redirect(string $provider): RedirectResponse
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return $this->redirectError('Unsupported social login provider.');
        }

        try {

            $driver = Socialite::driver($provider)->stateless()
                ->scopes(self::PROVIDERS[$provider]['scopes']);

            return $driver->redirect();

        } catch (\Throwable $e) {

            Log::error('Social login failed.', [
                'provider' => $provider,
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return $this->redirectError($e->getMessage()); // only debugging-এর

            // return $this->redirectError(
            //     'Unable to connect to ' . ucfirst($provider) . '.'
            // );
        }

    }

    public function callback(string $provider): RedirectResponse
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return $this->redirectError('Unsupported social login provider.');
        }

        try {

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            // if (blank($socialUser->getEmail())) {
            //     return $this->redirectError(
            //         ucfirst($provider) . ' account has no email address.'
            //     );
            // }

            $user = DB::transaction(function () use ($provider, $socialUser) {

                return $this->findOrCreateUser(
                    $provider,
                    $socialUser
                );

            });

            return $this->loginAndRedirect($user);

        } catch (\Throwable $e) {

            Log::error('Social login failed.', [
                'provider' => $provider,
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return $this->redirectError($e->getMessage()); // only for debugs

            // return $this->redirectError(
            //     ucfirst($provider) . ' login failed. Please try again.'
            // );
        }
    }

     /**
     * Find existing user or create a new one.
     */
    private function findOrCreateUser(string $provider,  SocialUser $socialUser ): User {

        $providerColumn = self::PROVIDERS[$provider]['column'];

        // 1. Find by provider ID
        $user = User::where($providerColumn, $socialUser->getId())->lockForUpdate()->first();

        if ($user) {
            return $this->updateExistingUser($user, $providerColumn, $socialUser);
        }

        // 2. Find by email
       $email = $socialUser->getEmail();

        if (!blank($email)) {

            $user = User::where('email', $email)->lockForUpdate()->first();

            if ($user) {

                $user->{$providerColumn} = $socialUser->getId();

                if (filled($socialUser->getAvatar()) && $user->photo !== $socialUser->getAvatar()) {
                    $user->photo = $socialUser->getAvatar();
                }

                $user->save();

                return $user;
            }
        }

        // 3. Create new account
        return $this->createUser(
            $providerColumn,
            $socialUser
        );
    }

    /**
     * Create new user.
     */
    private function createUser( string $providerColumn, SocialUser $socialUser ): User
    {
        $email = $socialUser->getEmail();

        $name =
            $socialUser->getName()
            ?: $socialUser->getNickname()
            ?: ($email
                ? explode('@', $email)[0]
                : ucfirst($providerColumn) . '_' . Str::random(8));

        return User::create([
            'name' => $name,
            'email' => $email,
            $providerColumn => $socialUser->getId(),
            'photo' => $socialUser->getAvatar(),
            'password' => Hash::make(Str::password(40)),
            'email_verified_at' => $email ? now() : null,
        ]);
    }

    /**
     * Update existing linked user.
     */
    private function updateExistingUser(
        User $user,
        string $providerColumn,
        SocialUser $socialUser
    ): User {

        $changed = false;

        if (blank($user->{$providerColumn})) {
            $user->{$providerColumn} = $socialUser->getId();
            $changed = true;
        }

        if ( blank($user->photo) && filled($socialUser->getAvatar()) ) {
            $user->photo = $socialUser->getAvatar();
            $changed = true;
        }

        if (blank($user->name) && filled($socialUser->getName()) && $user->name !== $socialUser->getName()) {
            $user->name = $socialUser->getName();
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $user;
    }

    /**
     * Login user and redirect frontend.
     */
    private function loginAndRedirect(User $user): RedirectResponse
    {
        $user->tokens()->delete();

        $token = $user->createToken('social-login')->plainTextToken;

        $frontend = rtrim(config('app.frontend_url'), '/');
        // dd(config('app.frontend_url'));
        return redirect( $frontend.'/auth/social?token='.urlencode($token) );
    }

    /**
     * Redirect with error message.
     */
    private function redirectError(string $message): RedirectResponse
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        return redirect(  $frontend . '/login?error=' . urlencode($message) );
    }













    // login with google
    public function loginWithFacebook()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            $user = User::where('facebook_id', $facebookUser->id)->first();

            if (!$user) {
                $user = User::where('email', $facebookUser->email)->first();

                if ($user) {
                    $user->facebook_id = $facebookUser->id;
                    $user->save();
                } else {
                    $user = User::create([
                        'name' => $facebookUser->name,
                        'email' => $facebookUser->email,
                        'facebook_id' => $facebookUser->id,
                        'photo' => $facebookUser->avatar_original,
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    ]);
                }
            }

            Auth::login($user);

            $token = $user->createToken('social-login')->plainTextToken;

            return redirect(env('FRONTEND_URL') . '/auth/social?token=' . urlencode($token));

        } catch (\Throwable $e) {

            return redirect(
                env('FRONTEND_URL') .
                '/login?error=' . urlencode($e->getMessage())
            );
        }
    }

    // login with google
    public function loginWithGoogle()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            if (!$googleUser->getEmail()) {
                return redirect(
                    env('FRONTEND_URL') .
                    '/login?error=' . urlencode('Google account has no email address.')
                );
            }

            DB::beginTransaction();

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    $user->google_id = $googleUser->getId();
                    $user->save();
                } else {
                    $user = User::create([
                        'name'      => $googleUser->getName(),
                        'email'     => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'photo'     => $googleUser->getAvatar(),
                        'password'  => bcrypt(Str::random(32)),
                    ]);
                }
            }

            Auth::login($user);

            $token = $user->createToken('social-login')->plainTextToken;

            DB::commit();

            return redirect(
                env('FRONTEND_URL') .
                '/auth/social?token=' . urlencode($token)
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Google Login Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return redirect(
                env('FRONTEND_URL') .
                '/login?error=' . urlencode('Google login failed. Please try again.')
            );
        }
    }

    // login with github
    public function loginWithGithub()
    {
        try {
            $githubUser = Socialite::driver('github')->stateless()->user();

            if (!$githubUser->getEmail()) {
                return redirect(
                    env('FRONTEND_URL') .
                    '/login?error=' . urlencode('GitHub account has no public email address.')
                );
            }

            DB::beginTransaction();

            // First check by github_id
            $user = User::where('github_id', $githubUser->getId())->first();

            if (!$user) {

                // Check existing account by email
                $user = User::where('email', $githubUser->getEmail())->first();

                if ($user) {

                    // Link GitHub account
                    $user->github_id = $githubUser->getId();

                    if (!$user->photo && $githubUser->getAvatar()) {
                        $user->photo = $githubUser->getAvatar();
                    }

                    $user->save();

                } else {

                    // Create new user
                    $user = User::create([
                        'name'      => $githubUser->getName() ?: $githubUser->getNickname(),
                        'email'     => $githubUser->getEmail(),
                        'github_id' => $githubUser->getId(),
                        'photo'     => $githubUser->getAvatar(),
                        'password'  => bcrypt(Str::random(32)),
                    ]);
                }
            }

            Auth::login($user);

            $token = $user->createToken('social-login')->plainTextToken;

            DB::commit();

            return redirect(
                env('FRONTEND_URL') .
                '/auth/social?token=' . urlencode($token)
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('GitHub Login Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return redirect(
                env('FRONTEND_URL') .
                '/login?error=' . urlencode('GitHub login failed. Please try again.')
            );
        }
    }
}
