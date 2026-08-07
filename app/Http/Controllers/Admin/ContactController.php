<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $messages = ContactMessage::orderByDesc('created_at')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            }))
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Contact/Index', [
            'messages' => $messages,
            'unreadCount' => ContactMessage::whereNull('read_at')->count(),
            'filters' => ['search' => $search],
        ]);
    }

    public function show(ContactMessage $contact): Response
    {
        $contact->markRead();

        return Inertia::render('Admin/Contact/Show', [
            'message' => $contact,
        ]);
    }

    public function reply(Request $request, ContactMessage $contact): RedirectResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to($contact->email, $contact->name)
            ->send(new ContactReplyMail($contact, $request->input('body')));

        $contact->update([
            'reply_body' => $request->input('body'),
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Reply sent to '.$contact->email);
    }

    public function destroy(ContactMessage $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('admin.contact.index')
            ->with('success', 'Message deleted.');
    }
}
