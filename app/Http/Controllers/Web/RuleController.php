<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): Response
    {
        // Flushed via Rule model events whenever a rule is saved/deleted.
        $rules = Cache::remember(Rule::LIST_CACHE_KEY, 3600, fn () => Rule::published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'excerpt', 'is_system', 'updated_at']));

        return Inertia::render('Web/Rules/Index', [
            'rules' => $rules,
            'seo' => [
                'title' => 'Rules — '.$this->settings->get('app_name', config('app.name')),
                'description' => 'Read the community rules and guidelines.',
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $rule = Rule::published()->where('slug', $slug)->firstOrFail();

        $allRules = Rule::published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'is_system', 'excerpt']);

        return Inertia::render('Web/Rules/Show', [
            'rule' => $rule,
            'allRules' => $allRules,
            'seo' => [
                'title' => $rule->title.' — '.$this->settings->get('app_name', config('app.name')),
                'description' => $rule->excerpt ?? strip_tags(substr($rule->content ?? '', 0, 160)),
            ],
        ]);
    }
}
