<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function index(): Response
    {
        $games = Game::withCount('servers')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Game $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'icon' => $g->icon,
                'color' => $g->color,
                'query_driver' => $g->query_driver,
                'default_port' => $g->default_port,
                'is_active' => $g->is_active,
                'sort_order' => $g->sort_order,
                'servers_count' => $g->servers_count,
            ]);

        return Inertia::render('Admin/Servers/Games/Index', ['games' => $games]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'unique:games,slug', 'alpha_dash'],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'query_driver' => ['required', 'string', 'max:50'],
            'default_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        Game::create($data);

        return back()->with('success', 'Game created.');
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', "unique:games,slug,{$game->id}"],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'query_driver' => ['required', 'string', 'max:50'],
            'default_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $game->update($data);

        return back()->with('success', 'Game updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();

        return back()->with('success', 'Game deleted.');
    }
}
