<?php

namespace App\Models\Concerns;

use App\Models\Scopes\MessScope;

trait BelongsToActiveMess
{
    public static function bootBelongsToActiveMess(): void
    {
        static::addGlobalScope(new MessScope);
    }
}
