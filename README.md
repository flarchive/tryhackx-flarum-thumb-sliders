# Thumb Sliders for Flarum

A Flarum extension that displays an animated thumbnail image slider on the
left side of each discussion in the discussion list. Images are automatically
extracted from the first post content.

> **Latest (2.1.7):** audit release — thumbnails of a moderator-hidden or
> still-private opening post are no longer exposed to users who cannot see that
> post, `thumbImages` no longer triggers an N+1 when discussions are included in
> other payloads, the min/max image-dimension settings now apply to every
> image that declares its size, broken slides are skipped, and the slider honours
> *reduce motion*. See the [CHANGELOG](CHANGELOG.md) for details.
>
> The avatar control is a **shared, per-device** section (Desktop / Mobile) —
> *Show* / *Replace with thumbnail when the topic has an image* / *Always
> replace* / **Hide avatar** — kept in sync with `flarum-topic-rating`. Built on
> the shared **restructured layout module** (mirrored with
> `flarum-topic-rating`): a clean flex/grid row
> `[thumb] [avatar] [title + info] [meta]` whose meta column adapts to whatever
> extensions are active (tags, rating, FoF discussion-views, replies).

> **Note:** This extension supports **Flarum 2.x** only. The old **1.x** line
> (Flarum 1.8) has been discontinued and is no longer published.

## Features

- **Automatic image extraction** — Detects images from the first post via
  fast XML parsing of the s9e/TextFormatter format, with HTML rendering
  fallback. Supports standard `<IMG>` tags and `fof/upload` `<UPL>` tags.
- **Smooth fade + scale animation** — Slick-style carousel with fade
  transitions and a subtle zoom (`scale(1.2)` → `scale(1)`), pure CSS.
- **Lazy loading with IntersectionObserver** — Slider loads its first
  image only when it enters the viewport; subsequent slides are
  pre-loaded in the background for seamless playback. A thumbnail is only
  shown once an image has actually loaded; broken images are skipped (the next
  working one takes over, or the fallback is shown).
- **Respects “reduce motion”** — with the OS-level *reduce motion* preference
  the slider shows its first image statically (no autoplay, zoom or fade).
- **Configurable fallback** — When the first post has no usable image you
  can show nothing, a built-in placeholder, or your own uploaded image.
- **Shared avatar control** *(per device; synced with `flarum-topic-rating`)* —
  a Desktop/Mobile section to *Show* the author avatar, *Replace* it with the
  thumbnail (when an image is present, or always), or **Hide** it entirely for
  a lighter list. The same section appears in the Topic Rating admin and the
  two stay in sync. *Replace* modes need a thumbnail (real image or fallback)
  so the row is never left empty; **Hide** works regardless.
- **Shared layout module** — Drops a clean flex/grid layout for
  `DiscussionListItem` and a right-side meta column that hosts tags,
  rating, FoF views and replies. Mirrored with `flarum-topic-rating` so
  installing either gives you the same layout; foreign tags / FoF views
  are extracted from their ItemList contributions and re-placed by key.
- **Mobile-friendly** — Smaller thumb (56px), tighter paddings, no more
  pixel-perfect `top:` / `right:` offsets to break when extensions move.
- **Dark mode support** — Adapts background and shadow.
- **Image filtering** — Configurable minimum / maximum dimension filters
  (applied to images whose size is declared, e.g. `[img width=… height=…]`);
  emojis, avatars, favicons and SVGs are excluded automatically.
- **Respects post visibility** — images from an opening post that a moderator
  has hidden (or that is still awaiting approval) are only used for users who
  can see that post.
- **Admin panel settings** — Full control over slider behaviour without
  touching code.

## Settings

| Setting | Default | What it does |
| --- | --- | --- |
| **Enable Thumb Sliders** | On | Master switch. |
| **Slider width** | 150 px | Width of the slider (50–400; out-of-range values are clamped). Height follows 2:3 poster ratio. |
| **Autoplay speed** | 1200 ms | Time between slide transitions (500–10000; out-of-range values are clamped). |
| **Max images per discussion** | 10 | Up to 20. |
| **Min image dimension** | 50 px | Skip images whose declared width or height is smaller than this. |
| **Max image dimension** | 5000 px | Skip images whose declared width or height is larger than this. |
| **Fallback when no image is available** | No thumbnail | One of: no thumbnail / built-in placeholder / custom uploaded image. |
| **Avatars — Desktop** / **Mobile** ¹ | Show avatar | Per device: *Show* / *Replace with thumbnail when the topic has an image* / *Always replace* / *Hide avatar*. |

¹ **Shared** setting — the same Desktop/Mobile avatar section appears in the `flarum-topic-rating` admin, and changing it in either extension updates the other. The *Replace* modes only take effect when a thumbnail is shown; *Hide* applies regardless. Upgrading from ≤2.0.x migrates your old *Replace avatar with thumbnail* choice automatically.

## Screenshots

![Mobile view of the discussion list across multiple TryHackX layout combinations](assets/ALL_MOBILE.png)

*Mobile view — discussion list rendered with different combinations of TryHackX extensions (thumbnails + ratings + views, thumbnails + views, thumbnails only, ratings only, views only, vanilla Flarum).*

![Thumb Sliders admin settings — slider geometry, image limits, fallback and avatar replacement](assets/Thumb_Sliders.png)

*Thumb Sliders admin panel — master switch, slider width / autoplay speed, max images per discussion, min / max image dimensions, fallback when no image is available, avatar-replacement mode and the fallback image library.*

![Desktop discussion list with the full TryHackX stack — thumbnail sliders, star ratings and the magnet button](assets/ALL_VIA_MAGNETS.png)

*Desktop discussion list with the full TryHackX stack — the thumbnail slider sits on the left, star ratings on the right, with the magnet button next to each topic title.*

![Desktop discussion list — magnet tooltip mid-load on a topic](assets/ALL_VIA_MAGNETS_v2.png)

*Desktop discussion list — hover state showing the magnet tooltip loading inline; the shared layout module keeps the thumb / avatar / title / meta columns from overlapping.*

## Support Development

If you find this extension useful, consider supporting its development:

- **Monero (XMR):** `45hvee4Jv7qeAm6SrBzXb9YVjb8DkHtFtFh7qkDMxS9zYX3NRi1dV27MtSdVC5X8T1YVoiG8XFiJkh4p9UncqWGxHi4tiwk`
- **Bitcoin (BTC):** `bc1qncavcek4kknpvykedxas8kxash9kdng990qed2`
- **Ethereum (ETH):** `0xa3d38d5Cf202598dd782C611e9F43f342C967cF5`

You can also find the donation option in the extension's admin settings panel.

## Compatibility

| Flarum version | Extension version | Branch |
| --- | --- | --- |
| `^2.0` | `2.x` | [`main`](https://github.com/TryHackX/flarum-thumb-sliders/tree/main) |

### Plays well with

- [**tryhackx/flarum-topic-rating**](https://github.com/TryHackX/flarum-topic-rating) — Star rating slots into the right meta column.
- [**tryhackx/flarum-magnet-link**](https://github.com/TryHackX/flarum-magnet-link) — Magnet tooltip integrates without overlapping.
- [**fof/discussion-views**](https://github.com/FriendsOfFlarum/discussion-views) — View counter sits between rating and replies in the meta column.
- `flarum/tags` — Tag labels (including floating-tags) are relocated to the top of the meta column.

## Installation

```bash
composer require tryhackx/flarum-thumb-sliders
php flarum cache:clear
```

## Updating

```bash
composer update tryhackx/flarum-thumb-sliders
php flarum cache:clear
```

## Configuration

1. Navigate to the **Administration** panel.
2. Find **Thumb Sliders** and enable it.
3. Click the extension to configure slider width, autoplay speed, image
   limits, dimension filters, fallback image, and the avatar replacement
   mode.

## How the layout module works

The shared module overrides `DiscussionListItem.contentView()`. Inside,
it still calls `this.contentItems()`, `this.infoItems()` and
`this.statsItems()`, so any extension's contributions still flow in — it
just pulls known keys out (`thumbSlider`, `rating`, `discussion-views`,
`tags`) and lays them out in a clean structure. The override installs
idempotently (a single global guard) regardless of whether *Thumb
Sliders* or *Topic Rating* loads first, so the two never compete.

## Development

```bash
cd js && npm install && npm run build   # rebuild js/dist after changing js/src
composer install
composer test:setup                     # once: creates the SQLite test install
composer test                           # unit + integration tests
```

## Links

- [GitHub](https://github.com/TryHackX/flarum-thumb-sliders)
- [Packagist](https://packagist.org/packages/tryhackx/flarum-thumb-sliders)
- [Report Issues](https://github.com/TryHackX/flarum-thumb-sliders/issues)

## License

MIT License. See [LICENSE](LICENSE) for details.
