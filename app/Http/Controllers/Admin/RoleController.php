<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Support\AvailableIcons;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->orderBy('sort')
            ->get()
            ->map(fn (Role $role) => $this->present($role));

        return Inertia::render('Admin/Roles/Index', ['roles' => $roles]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
            'availableIcons' => AvailableIcons::ROLE_ICONS,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'icon' => $data['icon'],
        ]);

        $role->permissions()->sync(
            Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id'),
        );

        return redirect()->route('admin.roles.index')->with('success', __('roles.created'));
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                ...$this->present($role),
                'permissions' => $role->permissions->pluck('slug'),
            ],
            'permissionGroups' => $this->permissionGroups(),
            'availableIcons' => AvailableIcons::ROLE_ICONS,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        // System roles (e.g. Owner) keep their slug — everything else is editable.
        $role->update([
            'name' => $data['name'],
            'slug' => $role->is_system ? $role->slug : $data['slug'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'icon' => $data['icon'],
        ]);

        $before = $role->permissions()->pluck('slug')->sort()->values()->all();

        $role->permissions()->sync(
            Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id'),
        );

        $role->forgetPermissionCache();

        $after = $role->permissions()->pluck('slug')->sort()->values()->all();

        // Permissions are a pivot the model audit can't see — log the diff explicitly.
        if ($before !== $after) {
            $this->activity->log('role.permissions_changed', "Changed permissions of role \"{$role->name}\"", $role, [
                'added' => array_values(array_diff($after, $before)),
                'removed' => array_values(array_diff($before, $after)),
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', __('roles.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            abort(403, __('roles.cannot_delete_system'));
        }

        $name = $role->name;
        $role->forgetPermissionCache();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', __('roles.deleted'));
    }

    /** @return array<string, mixed> */
    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'color' => $role->color,
            'icon' => $role->icon,
            'is_system' => $role->is_system,
            'sort' => $role->sort,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'permissions_count' => $role->permissions()->count(),
        ];
    }

    /** @return array<int, array{group: string, permissions: array<int, array<string, string>>}> */
    private function permissionGroups(): array
    {
        // Extensions register permissions at boot; persist any new ones so they can be assigned.
        app(ExtensionRegistry::class)->permissions()->sync();

        return Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => (string) $group,
                'permissions' => $items->map(fn (Permission $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
