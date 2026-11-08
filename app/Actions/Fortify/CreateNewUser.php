<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    // A person needs a few seconds to fill the form; a script does not.
    private const MIN_SECONDS_TO_FILL_FORM = 3;

    // Roughly the session lifetime; an older form is stale anyway.
    private const MAX_SECONDS_TO_FILL_FORM = 2 * 60 * 60;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->rejectBots($input);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * Reject sign-ups that fill the hidden "website" field, or that were
     * submitted too fast, too late or without a valid form timestamp.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function rejectBots(array $input): void
    {
        $token = $input['started_at'] ?? '';
        $startedAt = null;

        try {
            $startedAt = is_string($token) ? (int) Crypt::decryptString($token) : null;
        } catch (DecryptException) {
            // Missing or tampered with: treated the same as too fast.
        }

        $secondsTaken = $startedAt === null ? null : now()->timestamp - $startedAt;

        $looksLikeBot = filled($input['website'] ?? null)
            || $secondsTaken === null
            || $secondsTaken < self::MIN_SECONDS_TO_FILL_FORM
            || $secondsTaken > self::MAX_SECONDS_TO_FILL_FORM;

        if ($looksLikeBot) {
            throw ValidationException::withMessages([
                'signup' => 'We could not complete your sign-up. Please reload the page and try again.',
            ]);
        }
    }
}
