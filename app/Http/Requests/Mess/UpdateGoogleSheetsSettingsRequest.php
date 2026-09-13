<?php

namespace App\Http\Requests\Mess;

use App\Models\GoogleSheetsConfig;
use App\Services\GoogleSheets\SheetSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoogleSheetsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->canManageMess();
    }

    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'auth_mode' => ['required', Rule::in([
                GoogleSheetsConfig::AUTH_SERVICE_ACCOUNT,
                GoogleSheetsConfig::AUTH_OAUTH,
            ])],
            'service_account_json' => ['nullable', 'string', $this->validServiceAccountJson(...)],
            'oauth_client_id' => ['nullable', 'string', 'max:255'],
            'oauth_client_secret' => ['nullable', 'string', 'max:1024'],
            'oauth_refresh_token' => ['nullable', 'string', 'max:4096'],
            'spreadsheet_id' => ['nullable', 'string', 'max:1024'],
            'tabs' => ['nullable', 'array'],
            'tabs.*' => ['boolean'],
        ];
    }

    /**
     * Normalize the submitted form into the columns stored on the config.
     *
     * Blank secret inputs mean "keep the stored value" — they are omitted from
     * the payload entirely so the encrypted columns are never overwritten with
     * an empty string.
     */
    public function payload(): array
    {
        $data = $this->validated();

        $tabs = [];
        foreach (SheetSchema::models() as $model) {
            $key = SheetSchema::keyFor($model);

            if ($key !== null) {
                $tabs[$model] = (bool) ($data['tabs'][$key] ?? false);
            }
        }

        $payload = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'auth_mode' => $data['auth_mode'],
            'oauth_client_id' => $data['oauth_client_id'] ?? null,
            'spreadsheet_id' => $data['spreadsheet_id'] ?? null,
            'tabs' => $tabs,
        ];

        foreach (['service_account_json', 'oauth_client_secret', 'oauth_refresh_token'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $payload[$secret] = $data[$secret];
            }
        }

        return $payload;
    }

    /**
     * A pasted service-account key must be valid JSON of type `service_account`,
     * otherwise the failure only surfaces later inside a queued job.
     */
    private function validServiceAccountJson(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $decoded = json_decode((string) $value, true);

            if (! is_array($decoded)) {
                $fail(__('The service-account key is not valid JSON.'));

                return;
            }

            if (($decoded['type'] ?? null) !== 'service_account') {
                $fail(__('The service-account key must be a Google service-account credential (its "type" must be "service_account").'));
            }
        };
    }
}
