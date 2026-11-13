<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Api;

use ErnestDefoe\Studio\ActiveTheme;
use ErnestDefoe\Studio\Covers;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;

/**
 * A discussion's cover, for the gallery card: the picture, its box (height
 * per 1000 of width, 0 when unknown) and how many pictures the opening post
 * has. Columns on the row the API already loaded; see Covers for the one-off
 * fill.
 *
 * Only for a visitor who is looking at Studio: under Wardrobe, a visitor on
 * another theme gets none of it and fills nothing.
 */
final class DiscussionFields
{
    public function __construct(protected Covers $covers, protected ActiveTheme $theme)
    {
    }

    public function __invoke(): array
    {
        $visible = fn (Discussion $discussion, Context $context) => $this->theme->for($context->request);

        return [
            Schema\Str::make('studioCover')
                ->nullable()
                ->visible($visible)
                ->get(fn (Discussion $d) => $this->covers->defer($d, fn (Discussion $d) => $d->getAttribute('studio_cover') ?: null)),

            Schema\Integer::make('studioRatio')
                ->visible($visible)
                ->get(fn (Discussion $d) => $this->covers->defer($d, fn (Discussion $d) => (int) $d->getAttribute('studio_ratio'))),

            Schema\Integer::make('studioImages')
                ->visible($visible)
                ->get(fn (Discussion $d) => $this->covers->defer($d, fn (Discussion $d) => (int) $d->getAttribute('studio_images'))),
        ];
    }
}
