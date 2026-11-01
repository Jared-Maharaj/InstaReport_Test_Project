<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'reporter_id', 'type', 'category', 'description', 'severity', 'status', 'address', 'latitude', 'longitude', 'occurred_at'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public static function generateReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $reference = 'INC-';

        for ($index = 0; $index < 12; $index++) {
            $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $reference;
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class);
    }
}