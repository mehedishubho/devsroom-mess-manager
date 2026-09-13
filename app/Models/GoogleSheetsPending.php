<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A row that changed in the DB and still needs to be mirrored to Google Sheets.
 *
 * One row per (mess, model, record): a second change to the same record upserts
 * this row in place, so a burst coalesces. The unique index is what makes the
 * coalescing safe under concurrent writes.
 */
#[Fillable(['mess_id', 'model', 'record_id', 'operation'])]
class GoogleSheetsPending extends Model
{
    /** "pending" is uncountable, so the table is deliberately singular. */
    protected $table = 'google_sheets_pending';

    public const OPERATION_UPSERT = 'upsert';

    public const OPERATION_DELETE = 'delete';

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
        ];
    }

    protected function model(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => ltrim($value, '\\'),
        );
    }

    public function isDelete(): bool
    {
        return $this->operation === self::OPERATION_DELETE;
    }
}
