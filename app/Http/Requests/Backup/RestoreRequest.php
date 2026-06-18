<?php

declare(strict_types=1);

namespace App\Http\Requests\Backup;

use App\Models\Mess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-03 the typed-mess-name confirm Form Request.
 *
 * The restore POST is the most destructive surface in the app. Belt-and-
 * suspenders: the route middleware already enforces role:super-admin
 * (T-06-03-01) + throttle:5,1 (T-06-03-04); this Form Request is the SECOND
 * factor — the operator MUST type the active mess name exactly.
 *
 * Research Open Question #3 LOCKED: the typed-confirm target is the active
 * mess's `name` column (NOT app_name, NOT slug).
 *
 * When no active mess exists (pre-onboarding), the rule degrades to an
 * unmatchable sentinel so the restore can NEVER proceed in that state.
 */
class RestoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Belt-and-suspenders: the route middleware already enforces role:super-admin.
        return true;
    }

    public function rules(): array
    {
        $activeMessName = $this->activeMessName();

        return [
            'path' => ['required', 'string'],
            'mess_name' => [
                'required',
                'string',
                $activeMessName !== null ? "in:{$activeMessName}" : 'in:__no_active_mess__',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mess_name.required' => __('Typing the mess name is required to confirm the restore.'),
            'mess_name.in' => __('You must type the active mess name exactly to confirm the restore.'),
        ];
    }

    /**
     * Resolve the active mess name (the typed-confirm target).
     * Mirrors BackupController::activeMessName() — Mess exposes activeId().
     */
    private function activeMessName(): ?string
    {
        $id = Mess::activeId();

        return $id !== null ? Mess::find($id)?->name : null;
    }
}
