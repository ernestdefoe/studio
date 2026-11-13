<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Queue\RoutingQueue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\SyncQueue;

/**
 * Each discussion's cover: the first picture in its opening post.
 *
 * Worked out once per discussion and kept on the discussions row (three
 * nullable columns), so a page of the gallery reads it with the rows it
 * already loads. A discussion nobody has looked at since Studio was installed,
 * or whose opening post changed, has NULL there; the first document that
 * shows it fills every such discussion in that document at once:
 *
 *   one query for their opening posts, one for fof/upload's records of the
 *   pictures in them (when it is enabled), one UPDATE for all of them.
 *
 * So a page of 20 costs what a page of 1 does, and only the first time.
 *
 * Read from the opening post's stored XML, never by rendering it: a Markdown
 * or BBCode image is an IMG element, a fof/upload image a UPL-IMAGE-PREVIEW.
 * Pictures inside a spoiler are skipped, and a hidden, private or unapproved
 * opening post has no cover at all.
 *
 * The ratio (height per 1000 of width) is what lets the gallery draw a fixed
 * box before the picture arrives, so nothing moves as pictures load. It comes
 * from fof/upload's record, the IMG's own width and height, or the file on
 * this forum's disk. A picture hosted elsewhere is measured later on the
 * queue (MeasureCover), never during a page view; until then, and on a forum
 * without a queue worker, it has ratio 0 and the gallery uses a 4:3 box.
 */
final class Covers
{
    /** A discussion that has been looked at and has no picture. */
    public const NONE = '';

    /** Ratios are clamped to this, so one panorama cannot flatten a column. */
    public const MIN_RATIO = 250;

    public const MAX_RATIO = 4000;

    /**
     * Discussions waiting for their cover, by id. Static: the container builds
     * a fresh instance per resolve, and every field getter must share one batch.
     *
     * @var array<int, Discussion>
     */
    private static array $pending = [];

    public function __construct(
        protected ConnectionInterface $db,
        protected Paths $paths,
        protected UrlGenerator $url,
        protected ExtensionManager $extensions,
        protected Container $container,
        protected Queue $queue,
    ) {
    }

    /**
     * A cover field's value. Known: the value. Not yet: a closure the API
     * serializer runs once every model in the document has been walked, and
     * the first one to run fills all of them.
     *
     * @param callable(Discussion): mixed $read
     */
    public function defer(Discussion $discussion, callable $read): mixed
    {
        if ($discussion->getAttribute('studio_cover') !== null) {
            return $read($discussion);
        }

        self::$pending[(int) $discussion->id] = $discussion;

        return function () use ($discussion, $read) {
            $this->flush();

            return $read($discussion);
        };
    }

    /**
     * Fill every pending discussion.
     */
    public function flush(): void
    {
        $batch = self::$pending;
        self::$pending = [];

        $batch = array_filter($batch, fn (Discussion $d) => $d->getAttribute('studio_cover') === null);

        if (! $batch) {
            return;
        }

        $firstPostIds = array_values(array_filter(array_map(fn (Discussion $d) => (int) $d->first_post_id, $batch)));

        $posts = $firstPostIds
            ? $this->db->table('posts')->whereIn('id', $firstPostIds)->get()->keyBy('id')
            : collect();

        $found = [];
        $uuids = [];

        foreach ($batch as $id => $discussion) {
            $post = $posts->get((int) $discussion->first_post_id);

            $pictures = $post && $this->showable($post) ? self::pictures((string) $post->content) : [];
            $found[$id] = $pictures;

            if ($pictures && $pictures[0]['uuid']) {
                $uuids[] = $pictures[0]['uuid'];
            }
        }

        $uploads = $this->uploads($uuids);
        $rows = [];
        $remote = [];

        foreach ($batch as $id => $discussion) {
            $pictures = $found[$id];
            $cover = $pictures[0] ?? null;
            $url = self::NONE;
            $ratio = null;

            if ($cover) {
                [$url, $ratio] = $this->measure($cover, $uploads);

                if ($url === null) {
                    $url = self::NONE;
                } elseif ($ratio === null) {
                    $ratio = 0;

                    if (preg_match('#^https?://#i', $url)) {
                        $remote[$id] = $url;
                    }
                }
            }

            $row = ['studio_cover' => $url, 'studio_ratio' => $ratio, 'studio_images' => min(255, count($pictures))];
            $rows[$id] = $row;

            // This response reads them straight away.
            foreach ($row as $column => $value) {
                $discussion->setAttribute($column, $value);
            }
            $discussion->syncOriginalAttributes(array_keys($row));
        }

        $this->write($rows);
        $this->measureLater($remote);
    }

    /**
     * Every picture in a post's stored XML, in order.
     *
     * @return list<array{src: string, uuid: ?string, width: int, height: int}>
     */
    public static function pictures(string $xml): array
    {
        if ($xml === '' || ! preg_match('/<(?:IMG|img|UPL-IMAGE-PREVIEW)\b/', $xml)) {
            return [];
        }

        // A picture inside a spoiler must not become the thing everyone sees first.
        $xml = (string) preg_replace('#<(SPOILER|ISPOILER|DETAILS)\b[^>]*>.*?</\1>#s', '', $xml);

        preg_match_all('/<(IMG|img|UPL-IMAGE-PREVIEW)\b([^>]*)>/', $xml, $tags, PREG_SET_ORDER);

        $pictures = [];

        foreach ($tags as [, $tag, $attributes]) {
            preg_match_all('/([a-zA-Z_:-]+)="([^"]*)"/', $attributes, $pairs, PREG_SET_ORDER);
            $a = [];

            foreach ($pairs as [, $name, $value]) {
                $a[strtolower($name)] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
            }

            $upload = $tag === 'UPL-IMAGE-PREVIEW';
            $src = self::safe($upload ? ($a['url'] ?? null) : ($a['src'] ?? null));

            if ($src === null) {
                continue;
            }

            $pictures[] = [
                'src' => $src,
                'uuid' => $upload && isset($a['uuid']) && preg_match('/^[a-zA-Z0-9-]{1,64}$/', $a['uuid']) ? $a['uuid'] : null,
                'width' => (int) ($a['image_width'] ?? $a['width'] ?? 0),
                'height' => (int) ($a['image_height'] ?? $a['height'] ?? 0),
            ];
        }

        return $pictures;
    }

    /**
     * 🚨 The address lands in an img src. Only an http(s) address or a path on
     * this forum gets through, never one with a quote, bracket, backslash or
     * space that could close the value.
     */
    public static function safe(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 2000 || preg_match('/["\'()\\\\\s<>]/', $url)) {
            return null;
        }

        return preg_match('#^(https?://|/(?!/))#i', $url) ? $url : null;
    }

    public static function ratio(int $width, int $height): ?int
    {
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return max(self::MIN_RATIO, min(self::MAX_RATIO, (int) round($height * 1000 / $width)));
    }

    private function showable(object $post): bool
    {
        return ($post->type ?? 'comment') === 'comment'
            && empty($post->hidden_at)
            && empty($post->is_private)
            && (! property_exists($post, 'is_approved') || (bool) $post->is_approved);
    }

    /**
     * fof/upload's records for these pictures: the thumbnail to show (smaller
     * than the original) and the original's size. One query.
     *
     * @param list<string> $uuids
     * @return array<string, array{url: ?string, ratio: ?int}>
     */
    private function uploads(array $uuids): array
    {
        $file = 'FoF\\Upload\\File';
        $repository = 'FoF\\Upload\\Repositories\\FileRepository';

        if (! $uuids || ! $this->extensions->isEnabled('fof-upload') || ! class_exists($file) || ! class_exists($repository)) {
            return [];
        }

        try {
            $files = $file::query()->whereIn('uuid', array_unique($uuids))->get();
            $repo = $this->container->make($repository);
        } catch (\Throwable) {
            return [];
        }

        $out = [];

        foreach ($files as $f) {
            try {
                $thumbnail = method_exists($repo, 'getThumbnailUrlForFile') ? $repo->getThumbnailUrlForFile($f) : null;
                $url = method_exists($repo, 'getUrlForFile') ? $repo->getUrlForFile($f) : null;
            } catch (\Throwable) {
                $thumbnail = $url = null;
            }

            $out[(string) $f->uuid] = [
                'url' => self::safe($thumbnail ?: $url),
                'ratio' => self::ratio((int) $f->getAttribute('image_width'), (int) $f->getAttribute('image_height')),
            ];
        }

        return $out;
    }

    /**
     * The address to show and its ratio, without leaving this server.
     *
     * @param array{src: string, uuid: ?string, width: int, height: int} $picture
     * @param array<string, array{url: ?string, ratio: ?int}> $uploads
     * @return array{0: ?string, 1: ?int}
     */
    private function measure(array $picture, array $uploads): array
    {
        $upload = $picture['uuid'] ? ($uploads[$picture['uuid']] ?? null) : null;
        $url = $upload['url'] ?? $picture['src'];
        $ratio = $upload['ratio'] ?? self::ratio($picture['width'], $picture['height']);

        return [$url, $ratio ?? $this->local($url)];
    }

    /**
     * The size of a picture stored under this forum's public folder.
     */
    private function local(string $url): ?int
    {
        $base = rtrim($this->url->to('forum')->base(), '/');
        $path = str_starts_with($url, $base.'/') ? substr($url, strlen($base)) : (str_starts_with($url, '/') ? $url : null);

        if ($path === null) {
            return null;
        }

        $root = realpath($this->paths->public);
        $file = $root ? realpath($root.rawurldecode((string) strtok($path, '?#'))) : false;

        // 🚨 Inside the public folder and nowhere else, however the path is spelled.
        if (! $root || ! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file) || filesize($file) > 50 * 1024 * 1024) {
            return null;
        }

        $size = @getimagesize($file);

        return $size ? self::ratio((int) $size[0], (int) $size[1]) : null;
    }

    /**
     * One UPDATE for the whole batch.
     *
     * @param array<int, array{studio_cover: string, studio_ratio: ?int, studio_images: int}> $rows
     */
    private function write(array $rows): void
    {
        if (! $rows) {
            return;
        }

        $grammar = $this->db->getQueryGrammar();
        $id = $grammar->wrap('id');
        $sets = [];
        $bindings = [];

        // PostgreSQL types an untyped placeholder as text, which an integer
        // column refuses; MySQL has no CAST to INTEGER or VARCHAR at all.
        $pg = $this->db->getDriverName() === 'pgsql';

        foreach (['studio_cover' => 'VARCHAR', 'studio_ratio' => 'INTEGER', 'studio_images' => 'INTEGER'] as $column => $type) {
            $sql = [];

            foreach ($rows as $key => $row) {
                $sql[] = $pg ? "WHEN ? THEN CAST(? AS $type)" : 'WHEN ? THEN ?';
                array_push($bindings, $key, $row[$column]);
            }

            $sets[] = $grammar->wrap($column).' = CASE '.$id.' '.implode(' ', $sql).' END';
        }

        $ids = array_keys($rows);
        $bindings = array_merge($bindings, $ids);

        $this->db->update(
            'UPDATE '.$grammar->wrapTable('discussions').' SET '.implode(', ', $sets).' WHERE '.$id.' IN ('.implode(', ', array_fill(0, count($ids), '?')).')',
            $bindings
        );
    }

    /**
     * Pictures hosted elsewhere are measured on the queue, never in a page
     * view: on a forum whose queue runs jobs inline (no worker), not at all.
     *
     * @param array<int, string> $remote
     */
    private function measureLater(array $remote): void
    {
        if (! $remote) {
            return;
        }

        $driver = $this->queue instanceof RoutingQueue ? $this->queue->getDriver() : $this->queue;

        if ($driver instanceof SyncQueue) {
            return;
        }

        foreach ($remote as $id => $url) {
            $this->queue->push(new MeasureCover($id, $url));
        }
    }
}
