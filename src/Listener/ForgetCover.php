<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Listener;

use Flarum\Post\Post;
use Illuminate\Database\ConnectionInterface;

/**
 * An opening post was edited, hidden, restored or approved: its discussion's
 * cover is worked out again the next time anyone sees it. One UPDATE, and
 * nothing at all for a reply.
 */
final class ForgetCover
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function handle(object $event): void
    {
        $post = $event->post ?? null;

        if (! $post instanceof Post || (int) $post->number !== 1 || ! $post->discussion_id) {
            return;
        }

        $this->db->table('discussions')
            ->where('id', $post->discussion_id)
            ->update(['studio_cover' => null, 'studio_ratio' => null, 'studio_images' => null]);
    }
}
