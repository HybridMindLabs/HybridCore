<?php

namespace App\Services\Extensions\Registries;

use App\Models\Media;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Where library images are used, for the media library's "Used on" list and
 * its delete guard. Core registers pages, news, rules and the rest; an
 * extension registers every model column that can hold an image:
 *
 *   $registry->mediaUsages()->register(
 *       label: 'Shop package',
 *       model: ShopPackage::class,
 *       columns: ['image_path'],
 *       title: fn (ShopPackage $p) => $p->name,
 *       url: fn (ShopPackage $p) => route('admin.shop.packages.edit', $p),
 *   );
 *
 * A column may hold a storage path, a full URL, Markdown/HTML or JSON —
 * matching covers all of them.
 */
class MediaUsageRegistry
{
    /** @var array<int, array{label: string, model: class-string<Model>, columns: array<int, string>, title: Closure, url: Closure|null}> */
    private array $sources = [];

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $columns
     * @param  Closure(Model): string  $title
     * @param  Closure(Model): ?string|null  $url  admin edit link, if there is one
     */
    public function register(string $label, string $model, array $columns, Closure $title, ?Closure $url = null): void
    {
        $this->sources[] = compact('label', 'model', 'columns', 'title', 'url');
    }

    /** @return array<int, array{type: string, title: string, url: string|null}> */
    public function find(Media $media): array
    {
        $path = $media->path;
        // Narrow by file name in SQL (no "/", whose escaping differs between
        // databases and inside JSON), then confirm the whole path here.
        $like = '%'.addcslashes(basename($path), '%_').'%';
        $needles = [$path, str_replace('/', '\\/', $path)];

        $usages = [];
        foreach ($this->sources as $source) {
            try {
                $rows = $source['model']::query()
                    ->where(fn ($q) => collect($source['columns'])->each(fn (string $c) => $q->orWhere($c, 'like', $like)))
                    ->limit(200)
                    ->get();
            } catch (Throwable $e) {
                // A missing table (extension disabled, not migrated) is not a usage.
                report($e);

                continue;
            }

            foreach ($rows as $row) {
                $hit = collect($source['columns'])->contains(function (string $c) use ($row, $needles) {
                    $value = $row->getRawOriginal($c);

                    return is_string($value) && collect($needles)->contains(fn ($n) => str_contains($value, $n));
                });

                if ($hit) {
                    $usages[] = [
                        'type' => $source['label'],
                        'title' => (string) ($source['title'])($row),
                        'url' => $source['url'] ? ($source['url'])($row) : null,
                    ];
                }
            }
        }

        return $usages;
    }

    /**
     * Point every registered column that holds $old at $new instead, in
     * whichever form it holds it (plain or JSON-escaped). Returns how many
     * values changed. Written with a plain update, so model events and
     * caches are not touched — clear the cache afterwards.
     */
    public function repoint(string $old, string $new): int
    {
        $like = '%'.addcslashes(basename($old), '%_').'%';
        $pairs = [$old => $new, str_replace('/', '\\/', $old) => str_replace('/', '\\/', $new)];
        $changed = 0;

        foreach ($this->sources as $source) {
            try {
                $rows = $source['model']::query()
                    ->where(fn ($q) => collect($source['columns'])->each(fn (string $c) => $q->orWhere($c, 'like', $like)))
                    ->get();
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            foreach ($rows as $row) {
                $updates = [];
                foreach ($source['columns'] as $column) {
                    $value = $row->getRawOriginal($column);
                    if (is_string($value) && ($replaced = strtr($value, $pairs)) !== $value) {
                        $updates[$column] = $replaced;
                    }
                }

                if ($updates !== []) {
                    $source['model']::query()->whereKey($row->getKey())->update($updates);
                    $changed += count($updates);
                }
            }
        }

        return $changed;
    }
}
