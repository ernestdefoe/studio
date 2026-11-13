<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ErnestDefoe\Studio\ActiveTheme;
use ErnestDefoe\Studio\Api\DiscussionFields;
use ErnestDefoe\Studio\Console\ForgetCoversCommand;
use ErnestDefoe\Studio\Listener\ForgetCover;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\Post\Event as PostEvent;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Psr\Http\Message\ServerRequestInterface;

/*
 * A colour setting, as LESS will take it: a #hex, or the default.
 * 🚨 Setting keys and defaults are written out here, never read from a class
 * constant: extend.php runs on every request, and autoloading Studio's own
 * classes at load time fails the whole site while composer swaps its files.
 */
$hex = fn (string $default) => fn ($value) => is_string($value) && preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', trim($value)) ? trim($value) : $default;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        /*
         * Stamped on <html> by the server, so the first paint already has the
         * right fonts. CLOSURES, never strings: core calls whatever
         * is_callable() accepts, and a string can name a PHP function.
         */
        ->content(function (Document $document, ServerRequestInterface $request) {
            // A visitor Wardrobe is serving another theme gets none of this.
            if (! resolve(ActiveTheme::class)->for($request)) {
                return;
            }

            $settings = resolve(SettingsRepositoryInterface::class);
            $fonts = (bool) $settings->get('ernestdefoe-studio.fonts', true);

            $document->extraAttributes['data-studio-fonts'] = fn () => $fonts ? 'on' : 'off';

            if ($fonts) {
                // The two faces every page draws above the fold.
                $assets = resolve(Filesystem::class)->disk('flarum-assets');

                foreach (['schibsted-grotesk-latin-wght-normal', 'instrument-serif-latin-400-italic'] as $face) {
                    $document->head[] = '<link rel="preload" href="'.e($assets->url('extensions/ernestdefoe-studio/fonts/'.$face.'.woff2')).'" as="font" type="font/woff2" crossorigin>';
                }
            }
        }),

    /*
     * Where the fonts are, as an absolute URL baked into the stylesheet: under
     * Wardrobe the stylesheet is served from its own path, where a relative
     * url() 404s.
     */
    (new Extend\Theme())
        ->addCustomLessVariable('studio-fonts', fn () => '"'.resolve(Filesystem::class)->disk('flarum-assets')->url('extensions/ernestdefoe-studio/fonts/').'"'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(DiscussionFields::class),

    (new Extend\Event())
        ->listen(PostEvent\Revised::class, ForgetCover::class)
        ->listen(PostEvent\Hidden::class, ForgetCover::class)
        ->listen(PostEvent\Restored::class, ForgetCover::class)
        ->listen('Flarum\Approval\Event\PostWasApproved', ForgetCover::class),

    (new Extend\Console())
        ->command(ForgetCoversCommand::class),

    (new Extend\Settings())
        ->default('ernestdefoe-studio.fonts', true)
        ->default('ernestdefoe-studio.rail', true)
        ->default('ernestdefoe-studio.chips', true)
        ->default('ernestdefoe-studio.toggle', true)
        ->default('ernestdefoe-studio.counts', true)

        // read by less/tokens.less
        ->registerLessConfigVar('studio-accent', 'ernestdefoe-studio.accent', $hex('#C2341A'))
        ->registerLessConfigVar('studio-ground', 'ernestdefoe-studio.ground', $hex('#F3F2EF'))

        // read by the gallery (forum/gallery): dark mode is the full-bleed
        // gallery with an icon rail
        ->serializeToForum('studioRail', 'ernestdefoe-studio.rail', 'boolval')
        // read by the gallery's toolbar: a chip per primary tag
        ->serializeToForum('studioChips', 'ernestdefoe-studio.chips', 'boolval')
        // read by the gallery's toolbar: the Gallery mode / Light switch
        ->serializeToForum('studioToggle', 'ernestdefoe-studio.toggle', 'boolval')
        // read by the cards: replies (and views, with fof/discussion-views)
        ->serializeToForum('studioCounts', 'ernestdefoe-studio.counts', 'boolval'),
];
