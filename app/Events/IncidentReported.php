<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class IncidentReported implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Incident $incident) {}
}