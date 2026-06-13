<?php

namespace TryHackX\ThumbSliders\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Formatter\Formatter;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;
use TryHackX\ThumbSliders\ImageExtractor;

/**
 * Builds the `thumbImages` attribute on the DiscussionResource.
 *
 * Extracted from extend.php so settings / formatter / logger arrive via
 * constructor injection (resolved once per request) instead of resolve() calls
 * inside a per-model closure, and so the logic is independently testable.
 *
 * Registered via `->fields(DiscussionThumbFields::class)`.
 */
class DiscussionThumbFields
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Formatter $formatter,
        protected LoggerInterface $log,
        protected CacheRepository $cache
    ) {
    }

    public function __invoke(): array
    {
        return [
            Schema\Arr::make('thumbImages')
                ->get(fn (Discussion $discussion, Context $context) => $this->imagesFor($discussion)),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function imagesFor(Discussion $discussion): array
    {
        try {
            // Honour the extension's own on/off switch: when disabled the frontend
            // renders no slider, so the extraction below would be pure waste (CPU
            // on every row + empty arrays in the payload). Mirror the frontend's
            // truthiness check — Flarum persists the toggle as a '1'/'0' string,
            // while the registered default is a real bool true.
            $enabled = $this->settings->get('tryhackx-thumb-sliders.enabled', true);
            if ($enabled === false || $enabled === '0' || $enabled === 0 || $enabled === 'false') {
                return [];
            }

            $firstPost = $discussion->firstPost;

            if (! $firstPost || $firstPost->type !== 'comment') {
                return [];
            }

            $rawXml = (string) $firstPost->getRawOriginal('content');
            if ($rawXml === '') {
                return [];
            }

            $maxImages = (int) $this->settings->get('tryhackx-thumb-sliders.max_images', 10);

            // Fast path: pull <IMG>/<UPL> straight out of the s9e XML (no render).
            $images = ImageExtractor::extractFromXml($rawXml, $maxImages);

            // Slow path: only fall back to a full HTML render when the post has
            // RICH content that could carry an image the XML scan missed (e.g. an
            // oembed / media-embed thumbnail). s9e TextFormatter roots a pure
            // plain-text post in `<t>…</t>` (no tags at all) and anything with
            // markup in `<r>…</r>`; a `<t>` post can never render an <img>, so
            // skipping the expensive, uncached render for it is free. This is what
            // turned a per-row render on text-only discussion lists into a no-op.
            if (empty($images) && strncmp(ltrim($rawXml), '<t', 2) !== 0) {
                $minSize = (int) $this->settings->get('tryhackx-thumb-sliders.min_img_size', 50);
                $maxSize = (int) $this->settings->get('tryhackx-thumb-sliders.max_img_size', 5000);

                // Cache ONLY the expensive render+extract. The key is fully
                // determined by the post's parsed content plus the size thresholds
                // that affect the result, so it can never go stale: an edit yields
                // new XML (new key) and a settings change yields a new key; old
                // keys expire by TTL. The cheap XML fast-path above still runs on
                // every request and short-circuits for any post that already has an
                // <IMG>/<UPL>, so only rich, image-free posts ever reach here.
                $cacheKey = 'tryhackx-ts:img:' . md5($rawXml . '|' . $minSize . '|' . $maxSize . '|' . $maxImages);

                $images = $this->cache->remember($cacheKey, 86400, function () use ($rawXml, $firstPost, $minSize, $maxSize, $maxImages) {
                    $html = $this->formatter->render($rawXml, $firstPost);

                    return ImageExtractor::extract($html, $minSize, $maxSize, $minSize, $maxSize, $maxImages);
                });
            }

            return $images;
        } catch (\Throwable $e) {
            // Degrade gracefully (no thumbnail) but leave a breadcrumb rather than
            // swallowing the failure entirely, so real problems are diagnosable.
            $this->log->warning('tryhackx-thumb-sliders: thumbImages extraction failed', [
                'discussionId' => $discussion->id ?? null,
                'exception' => $e,
            ]);

            return [];
        }
    }
}
