# Studio — notes for whoever works on this next

Image-first masonry theme for Flarum 2 (ernestdefoe/studio, MIT). One session
owns Studio's work; changes go to the Command Center for review before release.

## Shape
- `src/Covers.php`: each discussion's cover (first picture of the opening
  post) kept in `discussions.studio_cover|studio_ratio|studio_images`.
  NULL = not worked out, '' = no picture. Filled in a batch by deferred API
  field closures (DiscussionFields): 2 queries for any page size (+1 with
  fof/upload). Tests: tests/integration/api/QueryCountTest.php.
- `src/MeasureCover.php`: queued remote measuring, public addresses only,
  pinned with CURLOPT_RESOLVE, 128 KB cap. Never queued on a sync queue.
- JS: `forum/gallery` (masonry, chips, mode switch), `forum/work` (stage,
  maker card, spec sheet). LESS in `less/`, phone.less last.

## Traps hit building it
- Masonry columns must be dealt from the column COUNT only (fixed nominal
  width for estimates), or a redraw after measuring re-deals every card.
- Core retains a post's DOM (SubtreeRetainer): anything of Studio's inside the
  opening post calls `bump()` (util.ts) to redraw.
- `DiscussionsUserPage` is a lazy chunk: extend it by module name.
- Core's pre-paint style `[data-theme^=dark] body` beats `html body`; use
  `html[data-theme] body`.
- Core's post is a grid (avatar column + main): hiding the avatar needs a
  one-column grid or the post collapses to 0 width.
- `ColorScheme` enum from ThemeMode is not exported at runtime: use strings.
- Flarum test requests ignore a query string in the path: pass params apart.
- Local test forum: ~/Herd/studiodev (prefix sd_); shots + behaviour checks in
  ~/github/scopes/studio-shots (behave.mjs, sweep.mjs, widgets.mjs).
