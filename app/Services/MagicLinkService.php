<?php

namespace App\Services;

use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MagicLinkService
{
    public function send(string $email): void
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(config('magiclink.expires_in_minutes'));

        MagicLinkToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $link = rtrim(config('app.url'), '/').route('auth.magiclink.verify', ['token' => $token], false);

        $user->notify(new MagicLinkNotification($link, $expiresAt->toIso8601String()));
    }

    public function consume(string $token): User
    {
        return DB::transaction(function () use ($token): User {
            $hash = hash('sha256', $token);
            $now = now();

            $consumed = MagicLinkToken::query()
                ->where('token_hash', $hash)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', $now)
                ->update(['consumed_at' => $now]);

            if ($consumed !== 1) {
                throw new UnprocessableEntityHttpException('This sign-in link is invalid or has expired. Please request a new link.');
            }

            $record = MagicLinkToken::query()->where('token_hash', $hash)->firstOrFail();
            $user = $record->user()->firstOrFail();

            MagicLinkToken::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now]);

            return $user;
        }, 3);
    }
}