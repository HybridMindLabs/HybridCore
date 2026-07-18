<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\SettingsService;
use App\Support\MarkdownRenderer;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function index(): Response
    {
        // Flushed via Rule model events whenever a rule is saved/deleted.
        //
        // Cached as plain arrays, not as an Eloquent collection. A serialised
        // collection can come back as __PHP_Incomplete_Class, which Inertia then
        // ships to the page as {"__PHP_Incomplete_Class_Name": "..."} instead of
        // a list — every field reads as undefined and route('rules.show', slug)
        // throws. Arrays survive serialisation regardless.
        $rules = Cache::remember(Rule::LIST_CACHE_KEY, 3600, fn () => Rule::published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'excerpt', 'is_system', 'updated_at'])
            ->toArray());

        return Inertia::render('Web/Rules/Index', [
            'rules' => $rules,
            'seo' => [
                'title' => trans('rules.title').' — '.$this->settings->get('app_name', config('app.name')),
                'description' => trans('rules.seo_description'),
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

        // Rendered here, like the legal pages. The browser used to parse the
        // markdown, so the rule text only existed after JavaScript ran and the
        // heading ids were built with an ASCII-only pattern that reduced every
        // Cyrillic heading to the same value.
        ['html' => $html, 'toc' => $toc] = $this->markdown->render($rule->content);

        return Inertia::render('Web/Rules/Show', [
            'rule' => [
                'id' => $rule->id,
                'slug' => $rule->slug,
                'title' => $rule->title,
                'excerpt' => $rule->excerpt,
                'content' => $html,
                'is_system' => $rule->is_system,
                'updated_at' => $rule->updated_at->toIso8601String(),
            ],
            'toc' => $toc,
            // Counted from the prose. The page derived this from the raw
            // markdown, so syntax characters were counted as words.
            'reading_minutes' => max(1, (int) round($this->markdown->wordCount($html) / 200)),
            'allRules' => $allRules,
            'seo' => [
                'title' => $rule->title.' — '.$this->settings->get('app_name', config('app.name')),
                'description' => $rule->excerpt ?? strip_tags(substr($rule->content ?? '', 0, 160)),
            ],
        ]);
    }
}
