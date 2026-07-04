<?php

namespace App\Jobs;

use App\Models\PageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPageView implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly array $data) {}

    public function handle(): void
    {
        PageView::create($this->data);
    }
}
