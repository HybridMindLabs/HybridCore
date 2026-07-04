<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\Mail\EmailLogService;
use App\Services\Mail\MailConfigService;
use App\Services\Mail\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function __construct(
        private readonly MailConfigService $config,
        private readonly MailTemplateService $templates,
        private readonly EmailLogService $logs,
    ) {}

    public function settings(): Response
    {
        return Inertia::render('Admin/Email/Settings', [
            'settings' => $this->config->getSettings(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => 'required|in:smtp,log,sendmail',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        $this->config->saveSettings($data);

        return back()->with('success', 'Mail settings saved.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $result = $this->config->testConnection();

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $this->config->applyFromDatabase();

        $rendered = $this->templates->renderSlug('test', [
            'sent_at' => now()->toDateTimeString(),
        ]);

        $subject = $rendered['subject'] ?? 'Test Email from '.config('app.name');
        $body = $rendered['body'] ?? '<p>Test email.</p>';

        try {
            Mail::html($body, fn ($m) => $m->to($request->email)->subject($subject));
            $this->logs->log($request->email, $subject, 'sent', 'test');

            return response()->json(['success' => true, 'message' => 'Test email sent.']);
        } catch (\Throwable $e) {
            $this->logs->log($request->email, $subject, 'failed', 'test', $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function templatesIndex(): Response
    {
        return Inertia::render('Admin/Email/Templates/Index', [
            'templates' => EmailTemplate::orderBy('system', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function templateEdit(EmailTemplate $template): Response
    {
        return Inertia::render('Admin/Email/Templates/Edit', [
            'template' => $template,
        ]);
    }

    public function templateUpdate(Request $request, EmailTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'active' => 'boolean',
        ]);

        $template->update($data);
        $this->templates->invalidate($template->slug);

        return back()->with('success', 'Template saved.');
    }

    public function templatePreview(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string',
            'body_html' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $vars = array_merge(['app_name' => config('app.name')], $request->variables ?? []);
        $service = $this->templates;

        $tempTemplate = new EmailTemplate([
            'subject' => $request->subject,
            'body_html' => $request->body_html,
        ]);

        $rendered = $service->render($tempTemplate, $vars);

        return response()->json($rendered);
    }

    public function logs(Request $request): Response
    {
        $logs = $this->logs->paginate($request->only('status', 'template', 'search'));

        return Inertia::render('Admin/Email/Logs', [
            'logs' => $logs,
        ]);
    }
}
