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
                // Only serialize on the discussion endpoints themselves: the list
                // (firstPost eager-loaded in extend.php), Show and Create (the
                // discussion is the single primary resource — at most one lazy
                // load; Create loads firstPost only after the attributes).
                // Anywhere else the discussion is merely an included resource (a
                // user's posts, notifications, a post edit, …); computing the
                // attribute there lazy-loaded firstPost once per discussion (N+1)
                // for a value no discussion list reads. Omitting it also leaves
                // whatever the frontend store already holds untouched.
                //
                // Decided by the endpoint, not by `relationLoaded('firstPost')`:
                // since Flarum 2.0.2 the serializer settles a field's visibility
                // once per record and response, so it must depend on the record,
                // the actor and the request — not on which relations the instance
                // visited first happens to carry.
                ->visible(fn (Discussion $discussion, Context $context) => $context->listing(DiscussionResource::class)
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
            // on every row + empty arrays in the payload). Read it the way the forum
            // payload does ((bool) in extend.php): Flarum's settings form stores a
            // switched-off toggle as '' (it was missed here, so a disabled slider
            // still extracted on every row), other writers '0' / 'false'; the
            // registered default is a real bool true.
            $enabled = $this->settings->get('tryhackx-thumb-sliders.enabled', true);
            if (in_array($enabled, [false, '', '0', 0, 'false'], true)) {
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

            $maxImages = $this->numberSetting('max_images', 10);
            $minSize = $this->numberSetting('min_img_size', 50, true);
            $maxSize = $this->numberSetting('max_img_size', 5000);

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
     * A numeric setting. An emptied admin field is stored as '' — it used to be
     * cast to 0, so a cleared "Max image dimension" excluded every image with
     * known dimensions and a cleared "Maximum images" showed just one. Empty,
     * non-numeric or a maximum of 0 (meaningless for a count or an upper bound)
     * fall back to the default; a minimum of 0 is a real choice ("no minimum").
     */
    protected function numberSetting(string $key, int $default, bool $zeroAllowed = false): int
    {
        $raw = $this->settings->get('tryhackx-thumb-sliders.' . $key);
        if (! is_numeric($raw)) {
            return $default;
        }
        $value = max(0, (int) $raw);

        return $value === 0 && ! $zeroAllowed ? $default : $value;
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
