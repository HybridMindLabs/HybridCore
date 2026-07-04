<?php

namespace Hybridcore\Announcements\Services;

use Hybridcore\Announcements\Models\Announcement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnnouncementService
{
    private const CACHE_KEY = 'announcements.active';

    private const CACHE_TTL = 60;

    /**
     * Returns currently visible announcements, sorted by sort order.
     * Caches plain arrays (not Eloquent Collections) to avoid serialization
     * issues when the extension autoloader hasn't registered yet on cache read.
     *
     * @return Collection<int, Announcement>
     */
    public function active(?int $limit = null): Collection
    {
        // Cache plain arrays — safe to serialize regardless of autoload order.
        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Announcement::visible()
                ->orderBy('sort')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        });

        $items = collect($rows)->map(fn (array $row) => (new Announcement)->forceFill($row));

        return $limit ? $items->take($limit) : $items;
    }

    /** Flush the active cache (call after any admin save). */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function create(array $data): Announcement
    {
        $announcement = Announcement::create($data);
        $this->flush();

        return $announcement;
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        $announcement->update($data);
        $this->flush();

        return $announcement;
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
        $this->flush();
    }
}
