<?php

namespace App\Http\Requests\Mess;

/**
 * Validates the Edit Expense form. Same rules as StoreExpenseRequest — the
 * category still drives the kind, bazar-kind still requires purchased_by, and
 * the receipt is an optional replacement (omitting it keeps the existing one).
 */
class UpdateExpenseRequest extends StoreExpenseRequest
{
}
