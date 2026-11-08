<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

/**
 * Valid sign-up input, as the register form submits it: the hidden honeypot
 * field is empty and the form was loaded ten seconds ago.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signupInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        'website' => '',
        'started_at' => Crypt::encryptString((string) now()->subSeconds(10)->timestamp),
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip(function () {
    return Features::enabled(Features::registration());
}, 'Registration support is enabled.');

test('the registration form carries the bot traps', function () {
    $this->get('/register')
        ->assertSee('name="website"', false)
        ->assertSee('name="started_at"', false);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('new users can register', function () {
    $response = $this->post('/register', signupInput());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('suspicious sign-ups are rejected', function (array $overrides) {
    $this->post('/register', signupInput($overrides))->assertSessionHasErrors('signup');

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'honeypot filled in' => fn () => ['website' => 'http://spam.example'],
    'submitted too fast' => fn () => ['started_at' => Crypt::encryptString((string) now()->timestamp)],
    'form too old' => fn () => ['started_at' => Crypt::encryptString((string) now()->subHours(3)->timestamp)],
    'timestamp missing' => fn () => ['started_at' => null],
    'timestamp tampered with' => fn () => ['started_at' => 'garbage'],
])->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
