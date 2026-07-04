<?php

namespace Hybridcore\Announcements\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Hybridcore\Announcements\Http\Requests\AnnouncementRequest;
use Hybridcore\Announcements\Models\Announcement;
use Hybridcore\Announcements\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $service) {}

    public function index(): Response
    {
        return Inertia::render('Extensions/hybridcore/announcements/Admin/Index', [
            'announcements' => Announcement::orderBy('sort')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Extensions/hybridcore/announcements/Admin/Form');
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.announcements.index')
            ->with('success', trans('announcements::messages.created'));
    }

    public function edit(Announcement $announcement): Response
    {
        return Inertia::render('Extensions/hybridcore/announcements/Admin/Form', [
            'announcement' => $announcement,
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->service->update($announcement, $request->validated());

        return redirect()->route('admin.announcements.index')
            ->with('success', trans('announcements::messages.updated'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->service->delete($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('success', trans('announcements::messages.deleted'));
    }
}
