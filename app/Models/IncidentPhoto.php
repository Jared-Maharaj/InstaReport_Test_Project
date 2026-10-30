<?php

namespace App\Models;

use Database\Factories\IncidentPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['path'])]
class IncidentPhoto extends Model
{
    /** @use HasFactory<IncidentPhotoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}