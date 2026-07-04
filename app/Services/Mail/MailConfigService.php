<?php

namespace App\Services\Mail;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

class MailConfigService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function applyFromDatabase(): void
    {
        $mailer = $this->settings->get('mail_mailer');

        if (empty($mailer)) {
            return;
        }

        Config::set('mail.default', $mailer);

        if ($mailer === 'smtp') {
            Config::set('mail.mailers.smtp.host', $this->settings->get('mail_host', '127.0.0.1'));
            Config::set('mail.mailers.smtp.port', (int) $this->settings->get('mail_port', 587));
            Config::set('mail.mailers.smtp.username', $this->settings->get('mail_username'));
            Config::set('mail.mailers.smtp.password', $this->settings->get('mail_password'));
            Config::set('mail.mailers.smtp.encryption', $this->settings->get('mail_encryption'));
        }

        $fromAddress = $this->settings->get('mail_from_address');
        if (! empty($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $this->settings->get('mail_from_name', config('app.name')));
        }
    }

    public function testConnection(): array
    {
        $this->applyFromDatabase();

        $mailer = $this->settings->get('mail_mailer');

        if ($mailer !== 'smtp') {
            return ['success' => true, 'message' => "Mailer '{$mailer}' does not use a network connection — nothing to test."];
        }

        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            $transport->start();

            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (TransportException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSettings(): array
    {
        return [
            'mail_mailer' => $this->settings->get('mail_mailer', 'log'),
            'mail_host' => $this->settings->get('mail_host', '127.0.0.1'),
            'mail_port' => $this->settings->get('mail_port', '587'),
            'mail_username' => $this->settings->get('mail_username', ''),
            'mail_password' => '',
            'mail_encryption' => $this->settings->get('mail_encryption', 'tls'),
            'mail_from_address' => $this->settings->get('mail_from_address', ''),
            'mail_from_name' => $this->settings->get('mail_from_name', ''),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->settings->setMany($data);
    }
}
