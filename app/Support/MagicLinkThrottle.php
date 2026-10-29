<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class MagicLinkThrottle
{
    public function forRequest(string $email, string $ip): void
    {
        $window = config('magiclink.window_seconds');

        $this->reserve([
            $this->key('request-ip', $ip) => ['limit' => config('magiclink.ip_requests_per_window'), 'seconds' => $window],
            $this->key('request-email', $email) => ['limit' => config('magiclink.email_requests_per_window'), 'seconds' => $window],
            $this->key('request-cooldown', $email) => ['limit' => 1, 'seconds' => config('magiclink.request_cooldown_seconds')],
        ]);
    }

    public function forVerification(string $ip): void
    {
        $this->reserve([
            $this->key('verify-ip', $ip) => [
                'limit' => config('magiclink.verification_attempts_per_window'),
                'seconds' => config('magiclink.window_seconds'),
            ],
        ]);
    }

    private function reserve(array $limits): void
    {
        try {
            Cache::lock('magic-link:throttle-lock', 10)->block(2, function () use ($limits): void {
                $now = now()->getTimestamp();
                $counters = [];
                $retryAfter = 0;

                foreach ($limits as $key => $limit) {
                    $counter = Cache::get($key);

                    if ($counter === null || $counter['expires_at'] <= $now) {
                        $counter = ['attempts' => 0, 'expires_at' => $now + $limit['seconds']];
                    }

                    if ($counter['attempts'] >= $limit['limit']) {
                        $retryAfter = max($retryAfter, $counter['expires_at'] - $now);
                    }

                    $counters[$key] = $counter;
                }

                if ($retryAfter > 0) {
                    throw new TooManyRequestsHttpException($retryAfter, 'Too many attempts. Please try again later.');
                }

                foreach ($counters as $key => $counter) {
                    $counter['attempts']++;
                    Cache::put($key, $counter, $counter['expires_at'] - $now);
                }
            });
        } catch (LockTimeoutException) {
            throw new ServiceUnavailableHttpException(2, 'Sign-in is temporarily busy. Please try again.');
        }
    }

    private function key(string $scope, string $value): string
    {
        return 'magic-link:'.$scope.':'.hash_hmac('sha256', $value, config('app.key'));
    }
}