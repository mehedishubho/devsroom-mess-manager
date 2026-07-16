<?php

declare(strict_types=1);

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the super-admin Backup "Configure" form (schedule + retention
 * + per-provider, per-group toggles).
 *
 * Route-level `role:super-admin` middleware already authorizes access.
 * The 4 toggle rules use 'sometimes' so partial form posts (omitting a
 * section) still validate — the hidden-checkbox pattern submits 0 for
 * unchecked boxes so the keys are always present in practice.
 */
class UpdateBackupConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frequency' => ['required', 'in:off,daily,weekly,monthly'],
            'run_at' => ['required', 'date_format:H:i'],
            'keep_all_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_mb' => ['required', 'integer', 'min:100', 'max:1000000'],
            'gdrive_backup' => ['sometimes', 'boolean'],
            'gdrive_uploads' => ['sometimes', 'boolean'],
            'r2_backup' => ['sometimes', 'boolean'],
            'r2_uploads' => ['sometimes', 'boolean'],
        ];
    }
}
