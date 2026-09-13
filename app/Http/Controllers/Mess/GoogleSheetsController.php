<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\UpdateGoogleSheetsSettingsRequest;
use App\Jobs\BackfillGoogleSheetsJob;
use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsPending;
use App\Models\Mess;
use App\Services\GoogleSheets\Contracts\SheetsGateway;
use App\Services\GoogleSheets\SheetSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Manager screen for the Google Sheets mirror.
 *
 * The screen only stores configuration and triggers probes/backfill — the actual
 * mirroring happens in SyncGoogleSheetsJob, which is dispatched from model
 * events. Secrets are written encrypted and rendered blank on the form.
 */
class GoogleSheetsController extends Controller
{
    public function __construct(private readonly SheetsGateway $gateway) {}

    public function edit(): View
    {
        $config = $this->currentConfig();
        $spreadsheetId = $config?->resolvedSpreadsheetId();

        return view('mess.google-sheets.edit', [
            'config' => $config,
            'tables' => $this->tables($config),
            'pendingCount' => $config === null
                ? 0
                : GoogleSheetsPending::query()->where('mess_id', $config->mess_id)->count(),
            'serviceAccountEmail' => $config?->serviceAccountEmail(),
            'sheetUrl' => $spreadsheetId !== null
                ? 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/edit'
                : null,
            'hasCredentials' => (bool) $config?->hasCredentials(),
            'hasServiceAccountKey' => filled($config?->safeSecret('service_account_json')),
            'hasOauthSecret' => filled($config?->safeSecret('oauth_client_secret')),
            'hasOauthRefreshToken' => filled($config?->safeSecret('oauth_refresh_token')),
        ]);
    }

    public function update(UpdateGoogleSheetsSettingsRequest $request): RedirectResponse
    {
        $messId = Mess::activeId();

        if ($messId === null) {
            return back()->withErrors(['google_sheets' => __('No mess is configured yet.')]);
        }

        $config = GoogleSheetsConfig::forMess($messId) ?? new GoogleSheetsConfig(['mess_id' => $messId]);
        $payload = $request->payload();

        $config->fill($payload);

        if (array_key_exists('service_account_json', $payload)) {
            // Re-derive the displayed address from the newly stored key.
            $config->service_account_email = null;
        }

        $config->save();

        GoogleSheetsConfig::flushCache($messId);

        return redirect()
            ->route('mess.google-sheets.edit')
            ->with('success', __('Google Sheets settings saved.'));
    }

    public function testConnection(Request $request): JsonResponse|RedirectResponse
    {
        $config = $this->currentConfig();

        if ($config === null || ! $config->hasCredentials()) {
            return $this->result($request, false, __('Add credentials before testing the connection.'));
        }

        if (! $config->isReady()) {
            return $this->result($request, false, __('Add a spreadsheet id before testing the connection.'));
        }

        try {
            $result = $this->gateway->testConnection($config);
        } catch (Throwable $e) {
            return $this->result($request, false, $e->getMessage());
        }

        $title = $result['title'] !== '' ? $result['title'] : __('untitled spreadsheet');

        return $this->result($request, true, __('Connected to :title.', ['title' => $title]));
    }

    public function backfill(Request $request): JsonResponse|RedirectResponse
    {
        $config = $this->currentConfig();

        if ($config === null || ! $config->isReady()) {
            return $this->result($request, false, __('Save a spreadsheet and credentials before syncing existing data.'));
        }

        BackfillGoogleSheetsJob::dispatch((int) $config->mess_id);

        return $this->result($request, true, __('Sync queued. Existing rows will be written to the sheet shortly.'));
    }

    public function createSpreadsheet(Request $request): JsonResponse|RedirectResponse
    {
        $config = $this->currentConfig();

        if ($config === null || ! $config->hasCredentials()) {
            return $this->result($request, false, __('Save credentials before creating a spreadsheet.'));
        }

        $title = trim((string) $request->input('title', ''));

        if ($title === '') {
            $messName = Mess::find($config->mess_id)?->name ?? __('Mess');
            $title = __(':mess — Mess Data', ['mess' => $messName]);
        }

        try {
            $created = $this->gateway->createSpreadsheet($config, $title);
        } catch (Throwable $e) {
            return $this->result($request, false, $e->getMessage());
        }

        $config->forceFill(['spreadsheet_id' => $created['id']])->save();
        GoogleSheetsConfig::flushCache((int) $config->mess_id);

        return $this->result($request, true, $created['url'] !== ''
            ? __('Spreadsheet created: :url', ['url' => $created['url']])
            : __('Spreadsheet created.'));
    }

    private function currentConfig(): ?GoogleSheetsConfig
    {
        $messId = Mess::activeId();

        return $messId === null ? null : GoogleSheetsConfig::forMess($messId);
    }

    /**
     * Tab rows for the config table (tab name, column count, enabled toggle).
     *
     * @return array<int, array{key: string, tab: string, columns: int, enabled: bool}>
     */
    private function tables(?GoogleSheetsConfig $config): array
    {
        $enabled = $config?->enabledModels(SheetSchema::models()) ?? SheetSchema::models();
        $rows = [];

        foreach (SheetSchema::models() as $model) {
            $key = SheetSchema::keyFor($model);

            if ($key === null) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'tab' => (string) SheetSchema::tabFor($model),
                'columns' => count(SheetSchema::headersFor($model)),
                'enabled' => in_array($model, $enabled, true),
            ];
        }

        return $rows;
    }

    /**
     * Reply as JSON for the fetch()-driven buttons, or as a redirect+flash when
     * the form is submitted without JavaScript.
     */
    private function result(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message]);
        }

        return $ok
            ? back()->with('success', $message)
            : back()->withErrors(['google_sheets' => $message]);
    }
}
