# Thumb Sliders for Flarum

A Flarum extension that displays an animated thumbnail image slider on the left side of each discussion in the discussion list. Images are automatically extracted from the first post content.

> **Latest (v1.0.1):** Support button moved to top of admin page with CSS improvements.

## Features

- **Automatic image extraction** - Detects images from the first post using fast XML parsing (s9e TextFormatter format), with HTML rendering fallback. Supports standard `<IMG>` tags and `fof/upload` `<UPL>` tags.
- **Smooth fade + scale animation** - Slick-style carousel with fade transitions and subtle zoom effect (`scale(1.2)` to `scale(1)`), powered by CSS transitions with no JavaScript animation libraries.
- **Lazy loading with IntersectionObserver** - Images are loaded only when the slider enters the viewport. Subsequent slides are preloaded in the background for seamless playback.
- **Smart layout integration** - Flexbox-based layout that automatically adapts when used alongside other extensions:
  - [**tryhackx/flarum-topic-rating**](https://github.com/TryHackX/flarum-topic-rating) - Star ratings are positioned cleanly next to tags
  - [**tryhackx/flarum-magnet-link**](https://github.com/TryHackX/flarum-magnet-link) - Magnet link elements integrate without overlap
  - [**fof/discussion-views**](https://github.com/FriendsOfFlarum/discussion-views) - View counters are placed alongside reply counts
- **Responsive design** - Slider scales down on mobile devices (60px width) with adjusted counter text. Full desktop layout with configurable width.
- **Dark mode support** - Automatically adapts background and shadow for dark themes.
- **Image filtering** - Configurable minimum/maximum dimension filters. Automatically excludes emojis, avatars, favicons, icons, badges, logos, and SVGs.
- **Admin panel settings** - Full control over slider behavior without touching code.

## Settings

| Setting | Default | Description |
|---|---|---|
| **Enable Thumb Sliders** | On | Global toggle for the entire extension |
| **Slider width** | 150px | Width of the thumbnail slider (50-400px). Height follows 2:3 poster ratio automatically |
| **Autoplay speed** | 1200ms | Time between slide transitions (500-10000ms) |
| **Max images** | 10 | Maximum images per slider (1-20) |
| **Min image dimension** | 50px | Images smaller than this are excluded |
| **Max image dimension** | 5000px | Images larger than this are excluded |

## Screenshots

![Admin panel with page management, BBCode editor with toolbar, and live preview](assets/preview.gif)

*Admin panel with page management, and live preview.*

## Support Development

If you find this extension useful, consider supporting its development:

- **Monero (XMR):** `45hvee4Jv7qeAm6SrBzXb9YVjb8DkHtFtFh7qkDMxS9zYX3NRi1dV27MtSdVC5X8T1YVoiG8XFiJkh4p9UncqWGxHi4tiwk`
- **Bitcoin (BTC):** `bc1qncavcek4kknpvykedxas8kxash9kdng990qed2`
- **Ethereum (ETH):** `0xa3d38d5Cf202598dd782C611e9F43f342C967cF5`

You can also find the donation option in the extension's admin settings panel.

## Compatibility

| Flarum version | Extension version | Branch |
|---|---|---|
| `^1.8` | `1.x` | [`flarum-1`](https://github.com/TryHackX/flarum-thumb-sliders/tree/flarum-1) |
| `^2.0` | `2.x` | [`main`](https://github.com/TryHackX/flarum-thumb-sliders/tree/main) / [`flarum-2`](https://github.com/TryHackX/flarum-thumb-sliders/tree/flarum-2) |

## Installation

```bash
composer require tryhackx/flarum-thumb-sliders
php flarum cache:clear
```
## Update

```bash
composer update tryhackx/flarum-thumb-sliders
php flarum cache:clear
```

## Configuration

1. Navigate to the **Administration** panel.
2. Find **Thumb Sliders** in the extensions list and enable it.
3. Click the extension to configure slider width, autoplay speed, image limits, and dimension filters.

## Links

- [GitHub](https://github.com/TryHackX/flarum-thumb-sliders)
- [Packagist](https://packagist.org/packages/tryhackx/flarum-thumb-sliders)
- [Report Issues](https://github.com/TryHackX/flarum-thumb-sliders/issues)

## License

MIT License. See [LICENSE](LICENSE) for details.
