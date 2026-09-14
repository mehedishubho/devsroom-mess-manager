<?php

declare(strict_types=1);

namespace App\Http\Requests\Backup;

use App\Models\Mess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an uploaded backup archive for a disaster-recovery restore.
 *
 * Same second factor as RestoreRequest — the operator must type the active
 * mess name — but the source is a file upload rather than a path on the
 * backups disk. Hard limits on size and extension keep a stray (or hostile)
 * upload from filling the disk or handing something non-archive to the
 * unzip/import path.
 */
class UploadRestoreRequest extends FormRequest
{
    /** Max upload size in kilobytes (500 MB — a full DB + files zip). */
    public const MAX_KILOBYTES = 512000;

    public function authorize(): bool
    {
        // Belt-and-suspenders: the route middleware already enforces role:super-admin.
        return true;
    }

    public function rules(): array
    {
        $activeMessName = $this->activeMessName();

        return [
            'file' => ['required', 'file', 'mimes:zip', 'max:'.self::MAX_KILOBYTES],
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
            'file.mimes' => __('The backup must be a .zip archive produced by this application.'),
            'file.max' => __('The backup archive is larger than the :mb MB limit.', ['mb' => (int) (self::MAX_KILOBYTES / 1024)]),
            'mess_name.required' => __('Typing the mess name is required to confirm the restore.'),
            'mess_name.in' => __('You must type the active mess name exactly to confirm the restore.'),
        ];
    }

    private function activeMessName(): ?string
    {
        $id = Mess::activeId();

        return $id !== null ? Mess::find($id)?->name : null;
    }
}
