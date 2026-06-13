# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.6] - 2026-06-13

> Frontend robustness pass. Rebuilds `js/dist` (forum + admin). **No** backend,
> migration, or settings changes; the shared `discussionListLayout.js` /
> `AvatarSettings.js` are **untouched** (`LAYOUT_VERSION` stays 5), so no
> coordination with `flarum-topic-rating` is needed.

### Fixed
- **The thumbnail box is no longer rendered until its first image has actually
  loaded.** Previously the slider painted its box (and reserved its tall, poster
  -shaped height) immediately and only hid it once the image failed — so a
  discussion whose `[img]` is invalid, removed, or hosted somewhere slow/down
  flashed an empty loading box on every page refresh or homepage search before it
  vanished. The slider now starts hidden (`--probing` ⇒ `display:none`, taking no
  space and painting nothing) and is **revealed only when its first image
  successfully loads** — exactly the "wait for the first image, then render the
  thumb, then load the rest" flow. A broken or slow-failing image therefore never
  shows a box at all: the row simply renders without a thumbnail.
  - This is fully effective even right after a hard refresh (no dependency on an
    in-session cache). A slow-but-valid image just appears a moment later instead
    of reserving an empty box up front.
  - Lazy-loading is preserved: the (hidden) slider can't be observed directly, so
    the IntersectionObserver now watches the visible discussion row instead, and
    the first image is fetched only when the row nears the viewport.
  - The old loading **spinner** has been removed entirely (no `--loading` state /
    keyframes) — there is no longer any loading indicator before the first image,
    per the requested behaviour.
- **Broken images are remembered for the session.** A URL that fails to load is
  cached (alongside the existing success cache) and dropped up front on the next
  render, so a discussion whose image 404s no longer re-probes (and a slider with
  several images shows its remaining good ones straight away).
- **`preloadImage()` now has an `onerror` handler** (previously only `onload`). A
  later slide whose image fails is recorded as broken instead of being silently
  re-probed on every list re-render and left blank in the autoplay rotation.
- **Admin “copy address” now reports failure and falls back.** `SupportModal`'s
  copy button only chained `.then()` on `navigator.clipboard.writeText()`; on a
  plain-HTTP admin panel (or when the browser denies clipboard access) the
  rejection was swallowed and the button gave no feedback. It now falls back to a
  legacy `execCommand('copy')` path and shows a failure icon if that fails too.

## [2.1.5] - 2026-06-12

> Conventions / documentation pass only. **No** behavioural, frontend, layout,
> migration-logic, or settings changes — `discussionListLayout.js` untouched
> (`LAYOUT_VERSION` stays 5), `js/dist` does **not** need rebuilding. Cutting this
> release also gives static analysers a version that already contains the 2.1.4
> backend fixes (eagerLoad firstPost, slow-path cache, fallback-path DRY) to scan.

### Changed
- Documented why `resolve()` is unavoidable inside the shared-avatar seed
  migration's `up` closure (migration closures have no constructor-injection
  entry point) — mirroring the same note already added for the `serializeToForum`
  transform closure in 2.1.4. No runtime change; the migration already ran on
  existing installs and its logic is unchanged.

## [2.1.4] - 2026-06-12

> Backend performance & internal refactor only. **No** frontend or layout changes
> (`discussionListLayout.js` untouched, `LAYOUT_VERSION` stays 5), no migrations,
> no new settings — `js/dist` does **not** need rebuilding.

### Performance
- **`thumbImages` no longer forces `firstPost` to be _serialized_ on every
  discussion-list row.** The extension registered `addDefaultInclude(['firstPost'])`,
  which not only eager-loads the relation our extractor needs but also serializes
  the entire first post — triggering a per-row `contentHtml` `Formatter::render()`
  in core's `PostResource` and enlarging the payload, neither of which the frontend
  uses (it reads only the `thumbImages` attribute). It now `eagerLoad`s the
  relation instead, so it is in memory for the extractor (no N+1) without being
  rendered or sent to the client. (Note: `flarum/sticky` independently
  force-includes `firstPost`, so on forums with Sticky enabled the first post is
  still serialized by core — but Thumb Sliders is no longer a second cause of it.)
- **The image-free slow-path render is now cached.** When the fast XML scan finds
  no image in a _rich_ (`<r>`) first post, the extractor falls back to a full s9e
  render to catch embed/oembed thumbnails. That result is now cached, keyed by a
  hash of the post's parsed content plus the size thresholds that affect the
  outcome, so repeat list loads of the same image-free rich posts skip the render.
  The key is content-derived, so an edit or a settings change yields a fresh key —
  the cache can never return a stale result.
- **Extraction is skipped entirely when the slider is turned off.** When
  `tryhackx-thumb-sliders.enabled` is false the frontend renders no slider, so
  `imagesFor()` now returns immediately instead of scanning (and possibly
  rendering) every first post.

### Changed
- **Internal — single source of truth for the fallback storage path.** The
  `extensions/tryhackx-thumb-sliders/fallback` directory string was duplicated in
  the three upload/list/delete controllers and the settings serializer; it now
  lives once in `TryHackX\ThumbSliders\FallbackStorage::DIR`. No behavioural change.
- Documented why `resolve()` is unavoidable inside the `serializeToForum` transform
  closure (a Settings transform is a plain closure with no DI entry point).

## [2.1.3] - 2026-06-11

> Re-publish of the 2.1.2 fixes (late-`onload` null guard + image-URL cache).
> No functional changes beyond the 2.1.2 entry below.

## [2.1.2] - 2026-06-09

### Fixed
- **`Uncaught TypeError: Cannot read properties of null (reading
  'querySelectorAll')` while searching quickly.** A slider can be unmounted (the
  discussion list re-renders as you type in the filters) while one of its images
  is still loading; the late `Image.onload` then ran against the already
  cleaned-up `this.dom`. The image-load callbacks now bail out when the slider
  has been removed.

### Performance
- **Thumbnails no longer re-probe / re-fade when the list re-renders.** A
  module-level cache remembers image URLs that have already loaded this session,
  so a remounted slider (e.g. while live-searching the discussion list) shows the
  cached image instantly — no extra probe `Image()`, no loading fade, no
  perceived re-download of images that were already on screen a moment ago.

## [2.1.1] - 2026-06-08

> Security, performance, robustness and i18n fixes. No new settings or migrations.

### Security
- **SVG is no longer accepted as a fallback image.** The fallback-image upload
  previously allowed SVG and only blocked literal `<script>` tags, leaving script
  execution possible through other SVG vectors (`onload=` and other event
  attributes, `<use href="javascript:…">`, `<foreignObject>`, CSS `expression()`).
  Because the fallback image is served from a public URL, a crafted SVG uploaded
  by an admin would have been **stored XSS** reachable by every visitor. SVG (and
  the stray `text/xml` / `application/xml` MIME types) are now rejected — only
  raster formats (webp/jpeg/png/gif/bmp/avif) are accepted, validated with
  `getimagesizefromstring()`. The fallback **list** endpoint and the forum-side
  fallback-URL serializer also refuse non-raster entries, so any pre-existing
  `.svg` can no longer be listed, selected, or served. (Upload is admin-only, so
  this is defence-in-depth hardening rather than an anonymously-reachable hole.)

### Changed
- **Shared discussion-list layout module hardened** (in lock-step with
  `flarum-topic-rating` — the file stays byte-identical between the two): the
  idempotency guard now tolerates a third-party script clobbering the
  `window.tryhackxDLL` global with a non-object, and warns in the console when two
  installed copies report different `LAYOUT_VERSION`s (catches a partial
  multi-extension upgrade instead of silently using the older layout). The
  rendered layout is unchanged — `LAYOUT_VERSION` stays 5.
- **No more wasted formatter render on text-only discussions.** `thumbImages`
  extraction fell back to a full s9e TextFormatter render whenever the fast XML
  scan found no images — including for pure plain-text first posts, on every
  discussion-list request, uncached. It now skips that render for posts s9e roots
  as plain text (`<t>…</t>`, which can never produce an `<img>`), keeping the
  render only for rich posts (`<r>…</r>`) that might carry an embed/oembed
  thumbnail. Thumbnail results are unchanged; the per-row cost on text-heavy
  lists is removed.
- **`thumbImages` logic moved out of `extend.php`** into a dedicated,
  constructor-injected `Api\DiscussionThumbFields` class (settings / formatter /
  logger injected once per request instead of `resolve()` inside the closure).
  Mirrors `flarum-topic-rating`'s `DiscussionRatingFields` and makes the logic
  unit-testable.
- **Admin "Cancel" button styling** for core's *Reset extension settings* modal
  now extends the modal's prototype (`oncreate`/`onupdate`) instead of running a
  whole-document `MutationObserver` for the entire admin session — same approach
  as `flarum-topic-rating`.
- **Deleting a fallback image now uses a styled Flarum modal** (with a preview of
  the image and its filename) instead of the browser's native `window.confirm()`
  popup — new admin component `DeleteFallbackImageModal`.
- `composer.json` now requires `php: ^8.3` (was `^8.2`), matching `flarum/core`'s
  own requirement.

### Added
- **Polish translation (`locale/pl.yml`)** for the extension's own strings; the
  shared `tryhackx-avatars.*` block is kept byte-identical with the copy in
  `flarum-topic-rating`.

### Fixed
- The `thumbImages` extraction `catch (\Throwable)` no longer swallows failures
  silently — it logs a warning (with the discussion id) before degrading to
  "no thumbnail", so real problems are diagnosable.

## [2.1.0] - 2026-06-03

> The "Replace avatar with thumbnail" setting grows up: it is now a **shared,
> per-device avatar section** (Desktop / Mobile) with a new **Hide avatar**
> mode, kept in sync with `flarum-topic-rating`. Your previous choice is
> migrated automatically. The shared layout module advances in lock-step with
> topic-rating.

### Added
- **Shared avatar section — Desktop & Mobile.** Replaces the single
  *Replace avatar with thumbnail* select with a per-device control offering
  *Show avatar* / *Replace with thumbnail when the topic has an image* /
  *Always replace with thumbnail* / **Hide avatar**. The new **Hide** mode
  removes the author avatar entirely for a lighter list (most useful on mobile)
  and works even when no thumbnail is shown. The section also appears in the
  `flarum-topic-rating` admin — both extensions read/write the same neutral
  `tryhackx-avatars.mode_desktop` / `…_mobile` keys (serialized as
  `tryhackxAvatarModeDesktop` / `…Mobile`), so a change in either is reflected
  in the other.
- **Migration** `2026_06_03_000000_seed_shared_avatar_mode` — seeds the new
  shared keys from the old `tryhackx-thumb-sliders.avatar_mode` (`none → Show`;
  `with_image` and `always` preserved) so existing installs keep their choice.
  Idempotent, and never overwrites a value already set via the new UI.

### Changed
- **Shared discussion-list layout module → `LAYOUT_VERSION = 5`**
  (byte-identical with `flarum-topic-rating`): per-device avatar modes plus
  topic-rating's rating placement.
- **Admin avatar selects** render at their natural width and clip long labels
  with an ellipsis instead of overflowing the card on narrow / mobile widths.

### Removed
- The single `tryhackx-thumb-sliders.avatar_mode` setting and the
  `thumbSlidersAvatarMode` forum attribute (replaced by the shared per-device
  avatar section above). The old value is **migrated automatically** — no
  manual action needed. The thumbnail **fallback** setting is unchanged.

## [2.0.8] - 2026-06-01

> Discussion-list layout polish for the shared restructured layout
> (mirrored with `flarum-topic-rating`). Tags get a proper mobile home,
> the desktop meta column stops colliding with the controls dropdown, and
> the layout now re-flows reliably on resize / orientation change. No new
> settings, no migrations, no API changes.

### Changed
- **Mobile tag placement.** On phones the discussion's tags now render
  inside `.DiscussionListItem-main`, on their own line right below the
  author/info line, wrapping across the full row width (`flex-wrap`)
  instead of stacking one-per-line in the narrow right-hand meta column
  (which made tag-heavy rows very tall). Tablet/desktop is unchanged —
  tags stay in the meta column.
- **Mobile tag size pinned** to `font-size: 11px` on
  `.DiscussionListItem-mobileTags` (≈9px labels with `TagLabel`'s own
  `0.85em`), so tags on mobile match or sit just below the desktop tag
  size instead of inheriting the larger body font.
- Mobile `.DiscussionListItem-main` right padding dropped from `4px` to
  `0` — the flex `gap` already separates the main column from the meta
  column, so the extra padding was redundant.
- Shared layout module bumped to `LAYOUT_VERSION = 4`.

### Fixed
- **Desktop meta column no longer slides under the controls (⋮) dropdown.**
  On tablet+ the meta column (tags / rating / views / replies) now reserves
  `28px` on its right so it clears the absolutely-positioned controls icon.
  Previously, on rows without a thumbnail or rating, the meta column reached
  far enough right to overlap the ⋮ (most visible on hover/active rows).
- **Tag layout re-flows reliably when the viewport crosses the phone
  breakpoint** (window resize, device rotation). The previous build
  recorded the "last breakpoint" inside `onbeforeupdate`, which never runs
  on mount, so the *first* desktop↔mobile switch after page load was
  silently dropped (tags didn't move until a second toggle). The flag is
  now written in `contentView` (runs on every render incl. mount), and an
  `onbeforeupdate` override forces a one-off rebuild on the render where
  the breakpoint flips — beating core's `SubtreeRetainer`, which otherwise
  pins each row to whichever layout it first rendered with.

### Removed
- Dead CSS custom property `--thumb-slider-width` (it was set on the
  content element in `ThumbSlider`'s `oncreate` but never read by any
  stylesheet). The `has-ThumbSlider` marker class is **kept** — it is a
  deliberate hook for `flarum-homepage-blocks`' mobile fallback, which
  targets `.DiscussionListItem-content:not(.has-ThumbSlider)` when neither
  thumb-sliders nor topic-rating is active.

## [2.0.7] - 2026-05-30

### Added
- Admin select setting **Replace avatar with thumbnail**
  (`tryhackx-thumb-sliders.avatar_mode`, default *off*) with three modes:
  - `none` — show both avatar and thumbnail.
  - `with_image` — hide the avatar only when the discussion has a real
    extracted image (fallbacks still show the avatar).
  - `always` — always hide the avatar so the thumbnail (or its configured
    fallback) takes its place. If both image and fallback are missing the
    avatar is kept, so the row is never empty.
- **Shared restructured layout module** for `DiscussionListItem`
  (`js/src/forum/discussionListLayout.js`) mirrored with
  `flarum-topic-rating`. Overrides `contentView()` (not the whole `view`)
  so slidable, the controls dropdown and the rest of core stay intact.
  Builds a clean flex row `[thumb][author][main][meta]` and extracts
  known keys (`thumbSlider`, `rating`, `discussion-views`, `tags`) from
  the standard ItemLists into a right-side meta column. Installs
  idempotently behind a global guard, regardless of which extension
  initialises first.

### Changed
- LESS for the discussion list was rewritten on top of the new layout:
  no more `.LayoutFixes()` mixin with absolute `top:`/`right:` offsets,
  no more `:has()` permutations for tag/rating/views/replies. The
  remaining feature CSS (slider visuals, fallback placeholder, dark
  theme) is unchanged.
- Mobile content padding is now `10px 6px 10px 6px` (was `10px 12px 10px 0`)
  so the thumbnail is no longer pinned to the very edge of the viewport.
- Desktop content padding aligned to the same horizontal rhythm:
  `12px 6px 12px 6px` (was `12px 16px 12px 0`), so the row breathes
  evenly on both sides on every viewport.
- Override of core's tablet+ `.DiscussionListItem` padding from
  `25 / 15` to a symmetric `12 / 12` so the outer row padding matches
  the inner content padding (no more uneven left/right gap on desktop).
- Cap the right meta column at `max-width: 18%` on the restructured
  layout, so a discussion with many tags wraps them down inside the
  narrow column instead of pushing the row wider. Tags themselves keep
  the natural `flex-wrap: wrap` behaviour. (We briefly tried mirroring
  core's `flarum/tags` truncate-with-hover-expand from
  `flarum/tags/less/forum.less` on the meta-item, but it didn't feel
  right in the meta-column context and isn't shipped in this release.)
- A scoped override sets `.DiscussionListItem-main { padding-right: 4px }`
  on phone and `.DiscussionListItem-title { margin-right: 32px }` on
  desktop (inside the restructured layout), overriding core /
  `flarum/tags` values so they survive a fresh install of either.
- Magnet-link integration: the list tooltip now only fires when the
  discussion's first post actually contains a `<MAGNET>` tag, so
  hovering a non-magnet discussion no longer flashes a "Loading magnet
  info…" tooltip. (Implemented in `flarum-magnet-link`; the layout
  module preserves `.DiscussionListItem-main` so the magnet tooltip
  hook keeps working.)

### Fixed
- **Cancel button in core's "Reset extension settings" modal** now
  uses Flarum's standard `Button--inverted` style so it doesn't render
  as a plain borderless button. Implemented with a small
  `MutationObserver` that adds the `Button--inverted` class to the
  Cancel button when the modal appears in the DOM (the modal class
  is lazy-loaded by core and not statically importable, so we can't
  extend its prototype directly). Each TryHackX extension registers
  this independently; repeated `classList.add` of the same class is
  a no-op.
- The `flarum-magnet-link` tooltip's mouse hook depends on
  `.DiscussionListItem-main`; the new `contentView` override re-emits
  that element verbatim, so magnets keep working when the layout is
  active.
- Avatar size on the mobile restructured layout is set explicitly
  (`.Avatar--size(30px)`) instead of relying on a chain of cascading
  overrides.

## [2.0.1] - 2026-04-09

### Changed
- Moved support button to the top of the admin settings page.
- Removed margin-top / padding-top / border-top CSS from the support
  button section.

## [2.0.0] - Initial tracked release

### Added
- Animated thumbnail image slider for the discussion list.
- Automatic image extraction from first post content.
- Smooth fade + scale animation with CSS transitions.
- Lazy loading with IntersectionObserver.
- Smart layout integration with topic-rating, magnet-link, and
  discussion-views extensions.
- Responsive design with mobile scaling.
- Dark mode support.
- Configurable image dimension filters.
- Admin panel settings for slider width, autoplay speed, max images, and
  dimension limits.
