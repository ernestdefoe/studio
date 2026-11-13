<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Discussions 1–5 by user 2, each opening post (id = discussion id):
 *   1 a Markdown picture            2 a fof/upload picture (fof/upload off)
 *   3 plain text                    4 a picture in a spoiler only
 *   5 a picture, but the post is hidden
 * Plus $extra more discussions with a picture each, for the query counts.
 */
abstract class StudioTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected int $extra = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-studio');

        $discussions = [];
        $posts = [];
        $xml = [
            1 => '<r><p>Glaze test <IMG src="https://example.com/a.png"><s>![](</s>https://example.com/a.png<e>)</e></IMG> and <IMG src="https://example.com/b.png">b</IMG></p></r>',
            2 => '<r><p><UPL-IMAGE-PREVIEW url="https://cdn.example.com/u.jpg" uuid="u1" image_width="1000" image_height="1500">x</UPL-IMAGE-PREVIEW></p></r>',
            3 => '<t><p>Plain words</p></t>',
            4 => '<r><SPOILER><IMG src="https://example.com/secret.png">x</IMG></SPOILER></r>',
            5 => '<r><p><IMG src="https://example.com/hidden.png">x</IMG></p></r>',
        ];

        for ($i = 6; $i < 6 + $this->extra; $i++) {
            $xml[$i] = '<r><p><IMG src="https://example.com/'.$i.'.png" width="800" height="600">x</IMG></p></r>';
        }

        foreach ($xml as $id => $content) {
            $at = Carbon::now()->subMinutes(100 - $id);
            $discussions[] = ['id' => $id, 'title' => "Work $id", 'slug' => "work-$id", 'created_at' => $at, 'last_posted_at' => $at, 'user_id' => 2, 'first_post_id' => $id, 'comment_count' => 1, 'last_post_number' => 1];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'number' => 1, 'created_at' => $at, 'user_id' => 2, 'type' => 'comment', 'content' => $content] + ($id === 5 ? ['hidden_at' => $at, 'hidden_user_id' => 1] : []);
        }

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Discussion::class => $discussions,
            Post::class => $posts,
        ]);
    }

    /** @return array{0: int, 1: mixed} */
    protected function get(string $path, ?int $actor = null): array
    {
        // The test request reads no query string from the path: pass it apart.
        [$path, $query] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($query, $params);

        $response = $this->send($this->request('GET', $path, $actor ? ['authenticatedAs' => $actor] : [])->withQueryParams($params));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** The discussions' cover fields, by id. */
    protected function covers(array $body): array
    {
        $out = [];

        foreach ($body['data'] as $d) {
            $a = $d['attributes'];
            $out[(int) $d['id']] = [array_key_exists('studioCover', $a) ? $a['studioCover'] : 'absent', $a['studioRatio'] ?? 'absent', $a['studioImages'] ?? 'absent'];
        }

        ksort($out);

        return $out;
    }
}
