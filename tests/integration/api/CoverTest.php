<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Tests\integration\api;

use ErnestDefoe\Studio\Tests\integration\StudioTestCase;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Revised;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class CoverTest extends StudioTestCase
{
    #[Test]
    public function each_discussion_gets_its_first_picture_and_count()
    {
        [$status, $body] = $this->get('/api/discussions');
        $this->assertSame(200, $status, json_encode($body));

        $covers = $this->covers($body);

        $this->assertSame(['https://example.com/a.png', 0, 2], $covers[1], 'A picture hosted elsewhere waits for the queue: ratio 0');
        $this->assertSame(['https://cdn.example.com/u.jpg', 1500, 1], $covers[2], 'fof/upload off: the stored address and its own size');
        $this->assertSame([null, 0, 0], $covers[3]);
        $this->assertSame([null, 0, 0], $covers[4], 'A picture in a spoiler is never the cover');
    }

    #[Test]
    public function a_hidden_opening_post_gives_no_cover_even_to_an_admin()
    {
        [, $body] = $this->get('/api/discussions/5', 1);

        $this->assertNull($body['data']['attributes']['studioCover']);
    }

    #[Test]
    public function the_cover_is_kept_on_the_row_and_read_from_there()
    {
        $this->get('/api/discussions');

        $row = $this->database()->table('discussions')->where('id', 1)->first();
        $this->assertSame('https://example.com/a.png', $row->studio_cover);
        $this->assertSame('', $this->database()->table('discussions')->where('id', 3)->value('studio_cover'), "'' = looked at, no picture");

        // Change the stored value: the API must now read it, not the post.
        $this->database()->table('discussions')->where('id', 1)->update(['studio_cover' => 'https://example.com/kept.png']);
        [, $body] = $this->get('/api/discussions/1');

        $this->assertSame('https://example.com/kept.png', $body['data']['attributes']['studioCover']);
    }

    #[Test]
    public function editing_the_opening_post_forgets_the_cover()
    {
        $this->get('/api/discussions');

        $post = CommentPost::query()->find(1);
        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new Revised($post, \Flarum\User\User::query()->find(1), 'old'));

        $this->assertNull($this->database()->table('discussions')->where('id', 1)->value('studio_cover'));
        $this->assertNotNull($this->database()->table('discussions')->where('id', 2)->value('studio_cover'), 'Only that discussion');
    }

    #[Test]
    public function the_fields_only_touch_the_rows_they_list()
    {
        $this->get('/api/discussions?page[limit]=2');

        $filled = $this->database()->table('discussions')->whereNotNull('studio_cover')->pluck('id')->all();

        $this->assertCount(2, $filled);
    }

    #[Test]
    public function a_guest_sees_covers_too()
    {
        [$status, $body] = $this->get('/api/discussions');

        $this->assertSame(200, $status);
        $this->assertSame('https://example.com/a.png', $this->covers($body)[1][0]);
    }
}
