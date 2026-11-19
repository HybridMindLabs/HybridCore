<?php

namespace App\Support;

use App\Models\NewsArticle;
use App\Models\Server;
use App\Services\Extensions\Registries\PageBlockRegistry;

/**
 * The page-builder blocks that ship with core. Extensions add their own
 * through ExtensionRegistry::pageBlocks().
 */
final class CorePageBlocks
{
    /** Icons a column can show; the public component maps the same names. */
    public const COLUMN_ICONS = [
        '' => 'None', 'Server' => 'Server', 'Shield' => 'Shield', 'Trophy' => 'Trophy', 'Users' => 'Users',
        'Zap' => 'Lightning', 'Star' => 'Star', 'Heart' => 'Heart', 'Gamepad2' => 'Gamepad', 'Gift' => 'Gift',
        'Crown' => 'Crown', 'Rocket' => 'Rocket', 'MessageCircle' => 'Chat', 'Clock' => 'Clock', 'Globe' => 'Globe', 'Lock' => 'Lock',
    ];

    public static function register(PageBlockRegistry $blocks): void
    {
        $buttons = [
            'key' => 'buttons', 'type' => 'list', 'label' => 'Buttons', 'max_items' => 3,
            'fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'max' => 60],
                ['key' => 'url', 'type' => 'url', 'label' => 'Link'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => ['primary' => 'Primary', 'secondary' => 'Secondary'], 'default' => 'primary'],
            ],
        ];

        $blocks->register('hero', 'Hero', 'PageBlockHero', [
            ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Small label above the title', 'max' => 60],
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 160],
            ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'max' => 400],
            ['key' => 'image', 'type' => 'image', 'label' => 'Background image'],
            ['key' => 'align', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Centre'], 'default' => 'left'],
            $buttons,
        ], icon: 'Sparkles', category: 'layout', sort: 10, description: 'Large title with background and buttons.');

        $blocks->register('text', 'Text', 'PageBlockText', [
            ['key' => 'body', 'type' => 'markdown', 'label' => 'Text'],
        ], fn (array $d) => ['html' => SafeMarkdown::toHtml($d['body'])],
            icon: 'Type', sort: 20, description: 'Formatted text (Markdown).');

        $blocks->register('image', 'Image', 'PageBlockImage', [
            ['key' => 'url', 'type' => 'image', 'label' => 'Image'],
            ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text', 'max' => 200],
            ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 200],
            ['key' => 'width', 'type' => 'select', 'label' => 'Width', 'options' => ['normal' => 'Content width', 'wide' => 'Wide', 'full' => 'Full width'], 'default' => 'normal'],
        ], icon: 'Image', category: 'media', sort: 30, description: 'A single image with caption.');

        $blocks->register('gallery', 'Gallery', 'PageBlockGallery', [
            ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
            ['key' => 'images', 'type' => 'list', 'label' => 'Images', 'max_items' => 24, 'fields' => [
                ['key' => 'url', 'type' => 'image', 'label' => 'Image'],
                ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text', 'max' => 200],
                ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 200],
            ]],
        ], icon: 'Images', category: 'media', sort: 40, description: 'Grid of images.');

        $blocks->register('columns', 'Columns', 'PageBlockColumns', [
            ['key' => 'columns', 'type' => 'list', 'label' => 'Columns', 'max_items' => 3, 'fields' => [
                ['key' => 'icon', 'type' => 'select', 'label' => 'Icon', 'options' => self::COLUMN_ICONS, 'default' => ''],
                ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                ['key' => 'body', 'type' => 'markdown', 'label' => 'Text', 'max' => 4000],
            ]],
        ], fn (array $d) => ['html' => array_map(fn (array $c) => SafeMarkdown::toHtml($c['body']), $d['columns'])],
            icon: 'Columns3', category: 'layout', sort: 50, description: 'Two or three columns side by side.');

        $blocks->register('servers', 'Live servers', 'PageBlockServers', [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
            ['key' => 'game_id', 'type' => 'game', 'label' => 'Game'],
            ['key' => 'limit', 'type' => 'number', 'label' => 'How many', 'min' => 1, 'max' => 24, 'default' => 6],
            ['key' => 'hide_offline', 'type' => 'toggle', 'label' => 'Hide offline servers', 'default' => false],
        ], fn (array $d) => ['servers' => self::servers($d)],
            icon: 'Server', category: 'community', sort: 60, description: 'Your game servers with live player counts.');

        $blocks->register('news', 'Latest news', 'PageBlockNews', [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
            ['key' => 'limit', 'type' => 'number', 'label' => 'How many', 'min' => 1, 'max' => 12, 'default' => 3],
        ], fn (array $d) => ['articles' => self::news($d['limit'])],
            icon: 'Newspaper', category: 'community', sort: 70, description: 'The newest published articles.');

        $blocks->register('faq', 'FAQ', 'PageBlockFaq', [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
            ['key' => 'items', 'type' => 'list', 'label' => 'Questions', 'max_items' => 40, 'fields' => [
                ['key' => 'question', 'type' => 'text', 'label' => 'Question', 'max' => 300],
                ['key' => 'answer', 'type' => 'markdown', 'label' => 'Answer', 'max' => 4000],
            ]],
        ], fn (array $d) => ['answers' => array_map(fn (array $i) => SafeMarkdown::toHtml($i['answer']), $d['items'])],
            icon: 'CircleHelp', sort: 80, description: 'Questions that open to show the answer.');

        $blocks->register('cta', 'Call to action', 'PageBlockCta', [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 160],
            ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'max' => 400],
            $buttons,
        ], icon: 'MousePointerClick', category: 'layout', sort: 90, description: 'A highlighted box with buttons.');

        $blocks->register('video', 'Video', 'PageBlockVideo', [
            ['key' => 'url', 'type' => 'url', 'label' => 'YouTube link'],
            ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 200],
        ], fn (array $d) => ['embed' => self::youtubeEmbed($d['url'])],
            icon: 'Video', category: 'media', sort: 100, description: 'Embedded YouTube video.');

        $blocks->register('divider', 'Divider', 'PageBlockDivider', [
            ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => ['line' => 'Line', 'space' => 'Empty space'], 'default' => 'line'],
            ['key' => 'size', 'type' => 'select', 'label' => 'Spacing', 'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'], 'default' => 'md'],
        ], icon: 'Minus', category: 'layout', sort: 110, description: 'A line or empty space between blocks.');
    }

    /** @return array<int, array<string, mixed>> */
    private static function servers(array $data): array
    {
        return Server::active()->visible()
            ->when($data['game_id'], fn ($q, $gameId) => $q->where('game_id', $gameId))
            ->with(['latestSnapshot', 'game:id,name,slug'])
            ->orderBy('id')
            ->limit($data['hide_offline'] ? 100 : $data['limit'])
            ->get()
            ->filter(fn (Server $s) => ! $data['hide_offline'] || $s->is_online)
            ->take($data['limit'])
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name ?? $s->latestSnapshot?->name ?? $s->address,
                'address' => $s->address,
                'game' => $s->game?->name,
                'game_slug' => $s->game?->slug,
                'map' => $s->latestSnapshot?->map,
                'players' => $s->latestSnapshot?->players_online ?? 0,
                'max_players' => $s->latestSnapshot?->players_max ?? 0,
                'online' => $s->is_online,
                'url' => route('servers.show', [$s->game->slug, $s->ip, $s->port]),
                'connect_url' => route('servers.connect', [$s->game->slug, $s->ip, $s->port]),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function news(int $limit): array
    {
        return NewsArticle::published()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (NewsArticle $a) => [
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'image' => $a->featured_image_url,
                'date' => $a->published_at?->toDateString(),
                'url' => route('news.show', $a->slug),
            ])
            ->all();
    }

    /** youtube.com/watch?v=, youtu.be/ and /shorts/ links to a privacy-enhanced embed URL. */
    public static function youtubeEmbed(string $url): ?string
    {
        if (! preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$m[1];
    }
}
