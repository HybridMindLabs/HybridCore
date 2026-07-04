<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Menus/Index', [
            'menus' => Menu::withCount('items')->orderBy('name')->get()->map(fn (Menu $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'slug' => $m->slug,
                'location' => $m->location,
                'items_count' => $m->items_count,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $menu = Menu::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'location' => $data['location'] ?: null,
        ]);

        return redirect()->route('admin.menus.edit', $menu)->with('success', 'Menu created.');
    }

    public function edit(Menu $menu): Response
    {
        $items = $menu->items()->with('children')->whereNull('parent_id')->get();

        return Inertia::render('Admin/Menus/Edit', [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'location' => $menu->location,
            ],
            'items' => $items->map(fn ($i) => $this->formatItem($i)),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $menu->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'location' => $data['location'] ?: null,
        ]);

        return back()->with('success', 'Menu updated.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return redirect()->route('admin.menus.index')->with('success', 'Menu deleted.');
    }

    public function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', Rule::in(['_self', '_blank'])],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
        ]);

        $maxSort = MenuItem::where('menu_id', $menu->id)
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort') ?? 0;

        $menu->items()->create([
            'label' => $data['label'],
            'url' => $data['url'],
            'target' => $data['target'] ?? '_self',
            'parent_id' => $data['parent_id'] ?? null,
            'sort' => $maxSort + 10,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', Rule::in(['_self', '_blank'])],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
        ]);

        $item->update([
            'label' => $data['label'],
            'url' => $data['url'],
            'target' => $data['target'] ?? '_self',
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return back()->with('success', 'Item updated.');
    }

    public function reorder(Request $request, Menu $menu): RedirectResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
        ])['items'];

        foreach ($items as $row) {
            MenuItem::where('id', $row['id'])->where('menu_id', $menu->id)->update([
                'sort' => $row['sort'],
                'parent_id' => $row['parent_id'] ?? null,
            ]);
        }

        return back()->with('success', 'Order saved.');
    }

    public function destroyItem(Menu $menu, MenuItem $item): RedirectResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    /** @return array<string, mixed> */
    private function formatItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'label' => $item->label,
            'url' => $item->url,
            'target' => $item->target,
            'sort' => $item->sort,
            'parent_id' => $item->parent_id,
            'children' => $item->children->map(fn ($c) => $this->formatItem($c))->values()->toArray(),
        ];
    }
}
