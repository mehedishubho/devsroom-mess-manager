<?php

declare(strict_types=1);

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the super-admin Backup "Configure" form (schedule + retention).
 * Route-level `role:super-admin` middleware already authorizes access.
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
        ];
    }
}
