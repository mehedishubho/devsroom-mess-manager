<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleSheetsConfig;
use Google\Client;
use Google\Service\Sheets;
use RuntimeException;

/**
 * Builds an authenticated Google API client from a mess's stored credentials.
 *
 * Supports the two modes the config screen offers:
 *  - `service_account`: an uploaded/pasted JSON key (no consent flow; the
 *    spreadsheet must be shared with the key's client_email).
 *  - `oauth`: client id + secret + a long-lived refresh token (the same shape
 *    the Drive backup feature uses).
 */
class GoogleSheetsClientFactory
{
    public function make(GoogleSheetsConfig $config): Sheets
    {
        return new Sheets($this->client($config));
    }

    public function client(GoogleSheetsConfig $config): Client
    {
        $client = new Client;
        $client->setApplicationName((string) config('app.name', 'Mess Management'));
        $client->setScopes([Sheets::SPREADSHEETS]);

        if ($config->authMode() === GoogleSheetsConfig::AUTH_OAUTH) {
            $this->configureOauth($client, $config);
        } else {
            $this->configureServiceAccount($client, $config);
        }

        return $client;
    }

    private function configureServiceAccount(Client $client, GoogleSheetsConfig $config): void
    {
        $json = $config->safeSecret('service_account_json');

        if ($json === null) {
            throw new RuntimeException(__('Google Sheets service-account key is missing.'));
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException(__('The Google service-account key is not valid JSON.'), 0, $e);
        }

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account') {
            throw new RuntimeException(__('The Google service-account key is not a service-account credential.'));
        }

        $client->setAuthConfig($decoded);
    }

    private function configureOauth(Client $client, GoogleSheetsConfig $config): void
    {
        $secret = $config->safeSecret('oauth_client_secret');
        $refreshToken = $config->safeSecret('oauth_refresh_token');

        if (blank($config->oauth_client_id) || $secret === null || $refreshToken === null) {
            throw new RuntimeException(__('Google Sheets OAuth credentials are incomplete (client id, secret and refresh token are all required).'));
        }

        $client->setClientId((string) $config->oauth_client_id);
        $client->setClientSecret($secret);

        try {
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (\Throwable $e) {
            throw new RuntimeException(__('Google rejected the refresh token.'), 0, $e);
        }

        if (! is_array($token) || isset($token['error'])) {
            $detail = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? '') : '';

            throw new RuntimeException(trim(__('Google rejected the refresh token.').' '.$detail));
        }
    }
}
