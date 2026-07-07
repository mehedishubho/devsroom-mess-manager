<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mess_id', 'date', 'reason'])]
class MessClosedDay extends Model
{
    use BelongsToActiveMess, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }
}
