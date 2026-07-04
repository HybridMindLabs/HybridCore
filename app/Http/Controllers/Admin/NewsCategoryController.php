<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NewsCategoryController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        return Inertia::render('Admin/News/Categories/Index', [
            'categories' => NewsCategory::withCount('articles')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (NewsCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'color' => $c->color,
                    'icon' => $c->icon,
                    'articles_count' => $c->articles_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/News/Categories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? '') ?: Str::slug($data['name']);

        $cat = NewsCategory::create($data);
        $this->activity->log('news.category.created', "Created news category \"{$cat->name}\"");

        return redirect()->route('admin.news.categories.index')->with('success', 'Category created.');
    }

    public function edit(NewsCategory $category): Response
    {
        return Inertia::render('Admin/News/Categories/Edit', [
            'category' => $category->only(['id', 'name', 'slug', 'description', 'color', 'icon', 'meta_title', 'meta_description', 'sort_order']),
        ]);
    }

    public function update(Request $request, NewsCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        $this->activity->log('news.category.updated', "Updated news category \"{$category->name}\"");

        return redirect()->route('admin.news.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(NewsCategory $category): RedirectResponse
    {
        $this->activity->log('news.category.deleted', "Deleted news category \"{$category->name}\"");
        $category->delete();

        return redirect()->route('admin.news.categories.index')->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?NewsCategory $cat = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/', Rule::unique('news_categories', 'slug')->ignore($cat?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
