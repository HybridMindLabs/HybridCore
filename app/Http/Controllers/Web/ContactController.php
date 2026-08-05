<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\CaptchaService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CaptchaService $captcha,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Web/Contact', [
            'captcha' => [
                'provider' => $this->captcha->activeProvider(),
                'site_key' => $this->captcha->siteKey(),
            ],
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        if (! $this->captcha->verify($request)) {
            return back()->withErrors(['captcha_token' => 'Captcha verification failed. Please try again.'])->withInput();
        }

        $msg = ContactMessage::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'ip' => $request->ip(),
        ]);

        $recipient = $this->settings->get('contact_recipient_email') ?: config('mail.from.address');

        if ($recipient) {
            Mail::to($recipient)->queue(new ContactMessageMail($msg));
        }

        Notification::send(
            User::where('is_admin', true)->get(),
            new SystemNotification(
                message: "New contact message from {$msg->name}: {$msg->subject}",
                level: 'info',
                actionUrl: route('admin.contact.show', $msg),
                actionLabel: 'View message',
            ),
        );

        return back()->with('success', 'Your message has been sent. We\'ll get back to you soon!');
    }
}
