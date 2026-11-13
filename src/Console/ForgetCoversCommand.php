<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Console;

use Flarum\Console\AbstractCommand;
use Illuminate\Database\ConnectionInterface;

/**
 * Forget every discussion's cover, so each is worked out again the next time
 * it is seen. For after moving uploads to a new host or CDN.
 */
final class ForgetCoversCommand extends AbstractCommand
{
    public function __construct(protected ConnectionInterface $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('studio:forget-covers')
            ->setDescription('Work out every discussion\'s Studio cover again the next time it is seen.');
    }

    protected function fire(): int
    {
        $count = $this->db->table('discussions')->whereNotNull('studio_cover')
            ->update(['studio_cover' => null, 'studio_ratio' => null, 'studio_images' => null]);

        $this->info("Forgot $count covers.");

        return 0;
    }
}
