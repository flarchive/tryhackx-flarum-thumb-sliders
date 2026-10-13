<?php

namespace TryHackX\ThumbSliders\Api;

use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Formatter\Formatter;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
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
                // Only serialize where firstPost is already in memory (the
                // discussion list, via eagerLoad in extend.php) or the discussion
                // is the single primary resource (Show / Create: at most one
                // lazy load — Create loads firstPost only after the attributes).
                // Anywhere else the discussion is merely an included resource (a
                // user's posts, notifications, a post edit, …); computing the
                // attribute there lazy-loaded firstPost once per discussion (N+1)
                // for a value no discussion list reads. Omitting it also leaves
                // whatever the frontend store already holds untouched.
                ->visible(fn (Discussion $discussion, Context $context) => $discussion->relationLoaded('firstPost')
                    || $context->showing(DiscussionResource::class)
                    || $context->creating(DiscussionResource::class))
                ->get(fn (Discussion $discussion, Context $context) => $this->imagesFor($discussion, $context->getActor())),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function imagesFor(Discussion $discussion, User $actor): array
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

            // A moderator can hide the opening post (and flarum/approval keeps an
            // unapproved one private) while the discussion itself stays listed.
            // The eager-loaded firstPost is NOT visibility-scoped on 2.0
            // pre-releases (rc.x), so never expose its images to an actor who
            // could not open that post. Normal posts skip the extra query.
            if (($firstPost->hidden_at !== null || $firstPost->is_private) && ! $this->postVisibleTo($firstPost, $actor)) {
                return [];
            }

            $rawXml = (string) $firstPost->getRawOriginal('content');
            if ($rawXml === '') {
                return [];
            }

            $maxImages = (int) $this->settings->get('tryhackx-thumb-sliders.max_images', 10);
            $minSize = (int) $this->settings->get('tryhackx-thumb-sliders.min_img_size', 50);
            $maxSize = (int) $this->settings->get('tryhackx-thumb-sliders.max_img_size', 5000);

            // Fast path: pull <IMG>/<UPL> straight out of the s9e XML (no render).
            $images = ImageExtractor::extractFromXml($rawXml, $maxImages, $minSize, $maxSize);

            // Slow path: only fall back to a full HTML render when the post has
            // RICH content that could carry an image the XML scan missed (e.g. an
            // oembed / media-embed thumbnail). s9e TextFormatter roots a pure
            // plain-text post in `<t>…</t>` (no tags at all) and anything with
            // markup in `<r>…</r>`; a `<t>` post can never render an <img>, so
            // skipping the expensive, uncached render for it is free. This is what
            // turned a per-row render on text-only discussion lists into a no-op.
            if (empty($images) && strncmp(ltrim($rawXml), '<t', 2) !== 0) {
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

    /**
     * Whether the actor may see this (hidden or private) post, per core's and
     * extensions' post visibility scopes.
     */
    protected function postVisibleTo(Post $post, User $actor): bool
    {
        return Post::query()->whereVisibleTo($actor)->whereKey($post->getKey())->exists();
    }
}
