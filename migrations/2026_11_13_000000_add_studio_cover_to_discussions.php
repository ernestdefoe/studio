<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// A discussion's cover (Covers), worked out once and kept with the row.
// NULL = not worked out yet, '' = no picture. Nullable, no index: only ever
// read with the row.
return [
    'up' => function (Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->string('studio_cover', 2048)->nullable();
            $table->unsignedSmallInteger('studio_ratio')->nullable();
            $table->unsignedTinyInteger('studio_images')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->dropColumn(['studio_cover', 'studio_ratio', 'studio_images']);
        });
    },
];
