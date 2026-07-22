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

            // Cloud credentials (UI-editable). Secret fields are nullable so an
            // empty password box means "keep the stored value" — the controller
            // only overwrites a secret when the submitted value is non-empty.
            'gdrive_client_id' => ['nullable', 'string', 'max:255'],
            'gdrive_client_secret' => ['nullable', 'string'],
            'gdrive_refresh_token' => ['nullable', 'string'],
            'gdrive_folder_id' => ['nullable', 'string', 'max:255'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'r2_secret' => ['nullable', 'string', 'max:255'],
            'r2_region' => ['nullable', 'string', 'max:32'],
            'r2_bucket' => ['nullable', 'string', 'max:255'],
            'r2_endpoint' => ['nullable', 'string', 'max:255'],
            'r2_use_path_style' => ['sometimes', 'boolean'],
        ];
    }
}
