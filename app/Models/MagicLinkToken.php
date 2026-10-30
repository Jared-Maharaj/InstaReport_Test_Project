<?php

namespace App\Models;

use Database\Factories\MagicLinkTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'expires_at', 'consumed_at'])]
#[Hidden(['token_hash'])]
class MagicLinkToken extends Model
{
    /** @use HasFactory<MagicLinkTokenFactory> */
    use HasFactory;

    public const UPDATED_AT = null;
    
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}