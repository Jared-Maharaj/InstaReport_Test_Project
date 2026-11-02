<?php

namespace Tests\Feature;

use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MagicLinkAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_magic_link_issuance_is_secure_and_limited_to_existing_users(): void
    {
        $this->freezeTime();
        config(['app.url' => 'https://reports.example.com']);
        $user = User::factory()->create(['email' => 'reporter@example.com']);
        Notification::fake();

        $known = $this->withHeader('Host', 'attacker.example')->postJson('/api/auth/magiclink', ['email' => ' REPORTER@EXAMPLE.COM ']);
        $unknown = $this->postJson('/api/auth/magiclink', ['email' => 'unknown@example.com']);

        $known->assertAccepted()->assertExactJson(['message' => 'If this email is registered, a sign-in link will be sent shortly.']);
        $unknown->assertAccepted()->assertExactJson($known->json());
        Notification::assertSentTo($user, MagicLinkNotification::class);
        Notification::assertCount(1);
        $mail = Notification::sent($user, MagicLinkNotification::class)->sole();
        $this->assertStringStartsWith('https://reports.example.com/api/auth/magiclink/verify?token=', $mail->link);
        parse_str(parse_url($mail->link, PHP_URL_QUERY), $query);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $query['token']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('magic_link_tokens', 1);
        $this->assertDatabaseHas('magic_link_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $query['token']),
            'expires_at' => now()->addMinutes(15)->format('Y-m-d H:i:s'),
            'consumed_at' => null,
        ]);
        $this->assertDatabaseMissing('magic_link_tokens', ['token_hash' => $query['token']]);
        $known->assertDontSee($query['token']);
    }

    public function test_a_valid_link_establishes_a_session_and_cannot_be_reused(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $token = str_repeat('a', 64);
        $record = $this->createToken($user, $token);
        $sibling = $this->createToken($user, str_repeat('b', 64));
        $this->withSession(['_token' => 'original-csrf']);
        $oldSessionId = session()->getId();

        $response = $this->getJson('/api/auth/magiclink/verify?token='.$token);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($record->fresh()->consumed_at);
        $this->assertNotNull($sibling->fresh()->consumed_at);
        $sessionId = session()->getId();
        $this->assertNotSame($oldSessionId, $sessionId);
        $this->assertNotSame('original-csrf', $response->json('csrf_token'));
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $oldSessionId]);

        $this->forgetSession();
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId)->getJson('/api/auth/user')
            ->assertOk()->assertJsonPath('data.id', $user->id);
        $this->forgetSession();
        $this->withCookie(config('session.cookie'), 'fresh-unauthenticated-session')
            ->getJson('/api/auth/magiclink/verify?token='.$token)->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_expired_and_forged_links_fail_without_consuming_a_valid_link(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $valid = $this->createToken($user, str_repeat('a', 64));
        $expired = $this->createToken($user, str_repeat('b', 64));
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();
        $boundary = $this->createToken($user, str_repeat('c', 64));
        $boundary->forceFill(['expires_at' => now()])->save();

        foreach ([str_repeat('b', 64), str_repeat('c', 64), str_repeat('a', 63).'d', str_repeat('e', 64)] as $token) {
            $this->getJson('/api/auth/magiclink/verify?token='.$token)->assertUnprocessable();
            $this->assertGuest();
        }

        $this->assertNull($valid->fresh()->consumed_at);
        $this->assertNull($expired->fresh()->consumed_at);
        $this->assertNull($boundary->fresh()->consumed_at);
    }

    private function createToken(User $user, string $token): MagicLinkToken
    {
        return MagicLinkToken::factory()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
        ]);
    }

    private function forgetSession(): void
    {
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}