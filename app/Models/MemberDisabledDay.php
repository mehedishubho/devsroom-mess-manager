<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mess_id', 'member_id', 'date', 'reason', 'entered_by'])]
class MemberDisabledDay extends Model
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
