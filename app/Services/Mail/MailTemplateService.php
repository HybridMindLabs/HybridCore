<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Cache;

class MailTemplateService
{
    private const CACHE_TTL = 300;

    public function find(string $slug): ?EmailTemplate
    {
        return Cache::remember("email_template.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return EmailTemplate::where('slug', $slug)->where('active', true)->first();
        });
    }

    public function render(EmailTemplate $template, array $variables = []): array
    {
        $subject = $this->interpolate($template->subject, $variables);
        $body = $this->interpolate($template->body_html, $variables);

        return ['subject' => $subject, 'body' => $body];
    }

    public function renderSlug(string $slug, array $variables = []): ?array
    {
        $template = $this->find($slug);

        if (! $template) {
            return null;
        }

        return $this->render($template, $variables);
    }

    public function invalidate(string $slug): void
    {
        Cache::forget("email_template.{$slug}");
    }

    private function interpolate(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
            $text = str_replace('{{ '.$key.' }}', (string) $value, $text);
        }

        return $text;
    }

    public function defaultTemplates(): array
    {
        return [
            [
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {{ app_name }}, {{ username }}!',
                'body_html' => $this->welcomeTemplate(),
                'variables' => ['app_name', 'username', 'login_url'],
                'system' => true,
                'active' => true,
            ],
            [
                'slug' => 'account_banned',
                'name' => 'Account Banned',
                'subject' => 'Your account on {{ app_name }} has been suspended',
                'body_html' => $this->bannedTemplate(),
                'variables' => ['app_name', 'username', 'reason', 'appeal_url'],
                'system' => true,
                'active' => true,
            ],
            [
                'slug' => 'digest',
                'name' => 'Activity Digest',
                'subject' => 'You have {{ count }} new updates on {{ app_name }}',
                'body_html' => $this->digestTemplate(),
                'variables' => ['app_name', 'username', 'count', 'items_html', 'view_url', 'unsubscribe_url'],
                'system' => true,
                'active' => true,
            ],
            [
                'slug' => 'test',
                'name' => 'Test Email',
                'subject' => 'Test Email from {{ app_name }}',
                'body_html' => $this->testTemplate(),
                'variables' => ['app_name', 'sent_at'],
                'system' => true,
                'active' => true,
            ],
        ];
    }

    private function welcomeTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Welcome</title></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#22d3ee;padding:24px 32px;">
        <h1 style="color:#0a0f1a;margin:0;font-size:24px;">{{ app_name }}</h1>
      </td></tr>
      <tr><td style="padding:32px;">
        <h2 style="color:#f1f5f9;margin:0 0 16px;">Welcome, {{ username }}!</h2>
        <p style="color:#94a3b8;margin:0 0 24px;line-height:1.6;">Your account has been created. You can now log in and explore the community.</p>
        <a href="{{ login_url }}" style="display:inline-block;background:#22d3ee;color:#0a0f1a;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">Login now</a>
      </td></tr>
      <tr><td style="padding:16px 32px;border-top:1px solid #1f2937;">
        <p style="color:#4b5563;margin:0;font-size:12px;">You're receiving this because you registered at {{ app_name }}.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    private function bannedTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Account Suspended</title></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#ef4444;padding:24px 32px;">
        <h1 style="color:#fff;margin:0;font-size:24px;">{{ app_name }}</h1>
      </td></tr>
      <tr><td style="padding:32px;">
        <h2 style="color:#f1f5f9;margin:0 0 16px;">Account Suspended</h2>
        <p style="color:#94a3b8;margin:0 0 8px;line-height:1.6;">Hello {{ username }},</p>
        <p style="color:#94a3b8;margin:0 0 24px;line-height:1.6;">Your account has been suspended for the following reason:</p>
        <blockquote style="background:#1f2937;border-left:4px solid #ef4444;margin:0 0 24px;padding:12px 16px;color:#f1f5f9;">{{ reason }}</blockquote>
        <a href="{{ appeal_url }}" style="display:inline-block;background:#ef4444;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">Submit an appeal</a>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    private function digestTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Activity Digest</title></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#22d3ee;padding:24px 32px;">
        <h1 style="color:#0a0f1a;margin:0;font-size:24px;">{{ app_name }}</h1>
      </td></tr>
      <tr><td style="padding:32px;">
        <h2 style="color:#f1f5f9;margin:0 0 8px;">Hi {{ username }},</h2>
        <p style="color:#94a3b8;margin:0 0 24px;">You have <strong style="color:#22d3ee;">{{ count }}</strong> new updates.</p>
        {{ items_html }}
        <a href="{{ view_url }}" style="display:inline-block;background:#22d3ee;color:#0a0f1a;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;margin-top:24px;">View all</a>
      </td></tr>
      <tr><td style="padding:16px 32px;border-top:1px solid #1f2937;">
        <p style="color:#4b5563;margin:0;font-size:12px;"><a href="{{ unsubscribe_url }}" style="color:#22d3ee;">Unsubscribe</a> from digest emails.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    private function testTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Test Email</title></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#22d3ee;padding:24px 32px;">
        <h1 style="color:#0a0f1a;margin:0;font-size:24px;">{{ app_name }}</h1>
      </td></tr>
      <tr><td style="padding:32px;">
        <h2 style="color:#f1f5f9;margin:0 0 16px;">Test email working!</h2>
        <p style="color:#94a3b8;margin:0 0 8px;">Your email configuration is set up correctly.</p>
        <p style="color:#4b5563;font-size:12px;margin:24px 0 0;">Sent at: {{ sent_at }}</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
