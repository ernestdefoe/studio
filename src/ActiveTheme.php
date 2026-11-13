<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio;

use Flarum\Extension\ExtensionManager;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Is this visitor looking at Studio?
 *
 * Always yes, unless ernestdefoe/wardrobe is serving each visitor a theme of
 * their own. Then it is yes only for the visitors on Studio, and Studio's
 * per-request work (its font preloads, the browser toolbar colour and the
 * cover fields on discussions) stands down for everyone else, so a
 * visitor on Bespoke or Warren pays nothing for Studio being installed.
 *
 * 🚨 Soft: Wardrobe is never required and its classes are named as strings.
 * Asked once per request.
 */
final class ActiveTheme
{
    public const ID = 'ernestdefoe-studio';

    private const REGISTRY = 'Ernestdefoe\\Wardrobe\\ThemeRegistry';

    /**
     * Shared by every instance: the container makes a fresh one per resolve.
     *
     * @var \WeakMap<ServerRequestInterface, bool>|null
     */
    private static ?\WeakMap $answers = null;

    public function __construct(protected ExtensionManager $extensions, protected Container $container)
    {
    }

    public function for(?ServerRequestInterface $request): bool
    {
        if (! $request || ! $this->extensions->isEnabled('ernestdefoe-wardrobe') || ! class_exists(self::REGISTRY)) {
            return true;
        }

        self::$answers ??= new \WeakMap();

        return self::$answers[$request] ??= $this->ask($request);
    }

    private function ask(ServerRequestInterface $request): bool
    {
        try {
            $cookie = $request->getCookieParams()['wardrobe_theme'] ?? null;
            $chosen = $this->container->make(self::REGISTRY)->resolve(RequestUtil::getActor($request), is_string($cookie) ? $cookie : null);
        } catch (\Throwable) {
            return true;
        }

        // null: Wardrobe is serving the shared stylesheet, which has every
        // enabled theme in it, Studio included.
        return $chosen === null || $chosen === self::ID;
    }
}
