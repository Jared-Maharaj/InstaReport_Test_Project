<?php

use App\Models\MagicLinkToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn (): int => MagicLinkToken::query()->where('expires_at', '<=', now()->subDay())->delete())
    ->daily()
    ->name('prune-magic-link-tokens');