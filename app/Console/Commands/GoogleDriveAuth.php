<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('google:drive-auth')]
#[Description('Generate Google Drive OAuth2 refresh token for file uploads')]
class GoogleDriveAuth extends Command
{
    public function handle(): int
    {
        $clientId = config('services.google.oauth_client_id');
        $clientSecret = config('services.google.oauth_client_secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('Set GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET in .env first.');
            $this->newLine();
            $this->info('Steps:');
            $this->line('1. Go to https://console.cloud.google.com/apis/credentials');
            $this->line('2. Click "Create Credentials" → "OAuth client ID"');
            $this->line('3. Application type: "Web application"');
            $this->line('4. Authorized redirect URIs: add "urn:ietf:wg:oauth:2.0:oob"');
            $this->line('5. Copy Client ID and Client Secret to .env');

            return self::FAILURE;
        }

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->addScope(GoogleDrive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $redirectUri = config('app.url').'/admin/drive-config/callback';
        $client->setRedirectUri($redirectUri);

        $authUrl = $client->createAuthUrl();

        $this->newLine();
        $this->info('Open this URL in your browser and authorize:');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();
        $this->warn('After authorizing, you will be redirected. Copy the "code" parameter from the URL.');
        $this->newLine();

        $code = $this->ask('Paste the authorization code here');

        if (! $code) {
            $this->error('No code provided.');

            return self::FAILURE;
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode(trim($code));

            if (isset($token['error'])) {
                $this->error('Error: '.$token['error_description'] ?? $token['error']);

                return self::FAILURE;
            }

            $refreshToken = $token['refresh_token'] ?? null;

            if (! $refreshToken) {
                $this->error('No refresh token received. Make sure you revoked previous access and try again.');

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('Success! Add this to your .env:');
            $this->newLine();
            $this->line("GOOGLE_OAUTH_REFRESH_TOKEN={$refreshToken}");
            $this->newLine();
            $this->info('Then run: php artisan config:clear');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
