import app from 'flarum/forum/app';

/** A forum-side Studio string. Every word on screen comes through here. */
export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-studio.forum.${key}`, params as any);

/** Whether an extension with forum JS is enabled. Never a build-time import. */
export function has(id: string): boolean {
  const extensions = (globalThis as any).flarum?.extensions;

  return !!extensions && id in extensions;
}

/*
 * Flarum's own phone breakpoint (@screen-phone-max). Layout that depends on it
 * redraws when a window is dragged across the line.
 */
const phoneQuery = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia('(max-width: 767.98px)') : null;

phoneQuery?.addEventListener?.('change', () => m.redraw());

export function isPhone(): boolean {
  return !!phoneQuery?.matches;
}

/** Dark mode, as the page is drawn right now (core stamps it on <html>). */
export function isDark(): boolean {
  return (document.documentElement.getAttribute('data-theme') || '').startsWith('dark');
}

/** The full-bleed gallery with the icon rail: dark mode, when the forum has it on. */
export function railMode(): boolean {
  return isDark() && document.documentElement.getAttribute('data-studio-rail') !== 'off';
}

/** Where a discussion link opens, as core's list does: the first unread post. */
export function nearFor(discussion: any): number {
  return Math.min(discussion.lastPostNumber() ?? 0, (discussion.lastReadPostNumber() || 0) + 1);
}

/** A discussion's tags, when flarum/tags is enabled. */
export function tagsOf(discussion: any): any[] {
  return (typeof discussion.tags === 'function' && (discussion.tags() || []).filter(Boolean)) || [];
}

/** The first top-level (primary) tag: the card's "medium". */
export function mediumOf(discussion: any): any | null {
  return tagsOf(discussion).find((tag) => tag.position() !== null && !tag.parent?.()) || tagsOf(discussion)[0] || null;
}

/** A secondary tag ("Critique wanted"), shown on the picture. */
export function flagOf(discussion: any): any | null {
  return tagsOf(discussion).find((tag) => tag.position() === null) || null;
}

/**
 * The box a cover is drawn in, height per 1000 of width. Unknown (0) is 4:3;
 * anything taller than 2:3 or wider than 2:1 is cropped to that, so one
 * panorama or one scroll-long screenshot doesn't take over a column.
 */
export function boxRatio(ratio: number | null | undefined): number {
  if (!ratio) return 750;

  return Math.max(500, Math.min(1500, ratio));
}

/*
 * Core keeps a post's DOM between redraws unless something it watches has
 * changed. The opening post holds Studio's stage and card, so it watches this
 * too: anything of Studio's inside a post that changes calls bump().
 */
let tick = 0;

export function studioTick(): number {
  return tick;
}

export function bump(): void {
  tick++;
  m.redraw();
}
