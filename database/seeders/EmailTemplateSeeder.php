<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(MailTemplateService $service): void
    {
        foreach ($service->defaultTemplates() as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
