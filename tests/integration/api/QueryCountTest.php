<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Tests\integration\api;

use ErnestDefoe\Studio\Tests\integration\StudioTestCase;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * 🚨 No N+1: the gallery's first look at 20 uncached discussions costs what
 * it costs for 1, and once filled, Studio adds no query at all.
 */
class QueryCountTest extends StudioTestCase
{
    protected int $extra = 20;

    private function queriesFor(string $path): int
    {
        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $db->flushQueryLog();
        $db->enableQueryLog();
        $this->get($path);
        $queries = array_filter($db->getQueryLog(), fn ($q) => ! str_contains($q['query'], 'access_tokens'));
        $db->disableQueryLog();

        return count($queries);
    }

    #[Test]
    public function filling_1_and_20_covers_costs_the_same()
    {
        // Studio's share of each page: the page with covers to fill, less the
        // same page once they are filled. Core's own queries differ between a
        // page of 1 and a page of 20 (its counts, its includes); Studio's don't.
        $cost = function (string $path) {
            $this->database()->table('discussions')->update(['studio_cover' => null]);
            $fill = $this->queriesFor($path);

            return $fill - $this->queriesFor($path);
        };

        // A first request also warms the app's own caches.
        $this->queriesFor('/api/discussions');

        $this->assertSame(2, $cost('/api/discussions?page[limit]=1'));
        $this->assertSame(2, $cost('/api/discussions?page[limit]=20'), 'Studio must fill a page of covers in a fixed number of queries');
    }

    #[Test]
    public function once_filled_studio_adds_nothing()
    {
        $this->queriesFor('/api/discussions?page[limit]=20');

        $filled = $this->queriesFor('/api/discussions?page[limit]=20');

        // Turn Studio's columns back to "unknown": the same page now costs 2 more (opening posts, one UPDATE).
        $this->database()->table('discussions')->update(['studio_cover' => null]);
        $refill = $this->queriesFor('/api/discussions?page[limit]=20');

        $this->assertSame($filled + 2, $refill);
    }
}
