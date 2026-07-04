<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmailLogService
{
    public function log(
        string $to,
        string $subject,
        string $status = 'sent',
        ?string $templateSlug = null,
        ?string $error = null,
        ?int $userId = null,
    ): EmailLog {
        return EmailLog::create([
            'to' => $to,
            'subject' => $subject,
            'template_slug' => $templateSlug,
            'status' => $status,
            'error' => $error,
            'user_id' => $userId,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }

    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = EmailLog::with('user')->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['template'])) {
            $query->where('template_slug', $filters['template']);
        }

        if (! empty($filters['search'])) {
            $query->where('to', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate($perPage);
    }
}
