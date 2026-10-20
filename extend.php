<?php

namespace TryHackX\ThumbSliders;

use Flarum\Extend;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Endpoint;
use TryHackX\ThumbSliders\Api\DiscussionThumbFields;
use TryHackX\ThumbSliders\FallbackStorage;
use TryHackX\ThumbSliders\Api\Controller\UploadFallbackImageController;
use TryHackX\ThumbSliders\Api\Controller\ListFallbackImagesController;
use TryHackX\ThumbSliders\Api\Controller\DeleteFallbackImageController;

// Shared avatar-display mode, coordinated with flarum-topic-rating. Kept valid
// here (instead of via ->default()) because BOTH extensions serialize the same
// neutral `tryhackx-avatars.*` keys, and ->default() throws if the same key is
// registered twice. The serializer is duplicate-safe: ForumResource fields are
// keyed by attribute name (last-wins) and both extensions yield the same value.
$normalizeAvatarMode = function ($value) {
    $v = is_string($value) ? $value : '';

    return in_array($v, ['show', 'with_image', 'always', 'hide'], true) ? $v : 'show';
};

// Clamp a numeric setting to the range its admin input advertises. The number
// field's min/max are only HTML hints and are not enforced on save, so a typo
// (e.g. a 120 ms autoplay, or a 0 / 99999 px width) would otherwise reach every
// visitor's browser as-is.
$clampInt = function (int $min, int $max, int $default) {
    return function ($value) use ($min, $max, $default) {
        $v = (int) $value;

        return $v > 0 ? max($min, min($max, $v)) : $default;
    };
};

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ApiResource(DiscussionResource::class))
        // Add the thumbImages array attribute (definition + extraction live in a
        // dedicated, constructor-injected class — see DiscussionThumbFields).
        ->fields(DiscussionThumbFields::class)
        // Eager-load firstPost for the thumbImages getter WITHOUT serializing it.
        // The core Index endpoint does not include firstPost; addDefaultInclude
        // would not only eager-load it but ALSO serialize it on every row, which
        // forces a per-row contentHtml Formatter::render() (PostResource) and
        // bloats the payload. The frontend reads only the `thumbImages` attribute,
        // so eagerLoad gives imagesFor() the in-memory relation (no N+1) without
        // the render / payload cost.
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['firstPost']);
        }),

    // Register default settings
    (new Extend\Settings())
        ->default('tryhackx-thumb-sliders.min_img_size', 50)
        ->default('tryhackx-thumb-sliders.max_img_size', 5000)
        ->default('tryhackx-thumb-sliders.max_images', 10)
        ->default('tryhackx-thumb-sliders.slider_width', 150)
        ->default('tryhackx-thumb-sliders.autoplay_speed', 1200)
        ->default('tryhackx-thumb-sliders.enabled', true)
        ->default('tryhackx-thumb-sliders.fallback_mode', 'none')
        ->default('tryhackx-thumb-sliders.fallback_image', '')
        ->serializeToForum('thumbSlidersSliderWidth', 'tryhackx-thumb-sliders.slider_width', $clampInt(50, 400, 150))
        ->serializeToForum('thumbSlidersAutoplaySpeed', 'tryhackx-thumb-sliders.autoplay_speed', $clampInt(500, 10000, 1200))
        ->serializeToForum('thumbSlidersEnabled', 'tryhackx-thumb-sliders.enabled', function ($value) {
            return (bool) $value;
        })
        ->serializeToForum('thumbSlidersFallbackMode', 'tryhackx-thumb-sliders.fallback_mode', function ($value) {
            return in_array($value, ['none', 'default', 'custom'], true) ? $value : 'none';
        })
        ->serializeToForum('tryhackxAvatarModeDesktop', 'tryhackx-avatars.mode_desktop', $normalizeAvatarMode)
        ->serializeToForum('tryhackxAvatarModeMobile', 'tryhackx-avatars.mode_mobile', $normalizeAvatarMode)
        ->serializeToForum('thumbSlidersFallbackImageUrl', 'tryhackx-thumb-sliders.fallback_image', function ($value) {
            // Must be a bare filename inside the fallback directory — the same
            // rule the delete endpoint enforces — so a hand-edited setting can
            // never point outside it (e.g. `../../avatars/x.png`).
            if (!is_string($value) || !preg_match('/^[a-zA-Z0-9._-]+$/', $value) || str_contains($value, '..')) {
                return '';
            }
            // Defence-in-depth: never emit a non-raster fallback URL (a legacy
            // .svg could linger in the setting even though uploads now reject SVG).
            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            if (!in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'avif'], true)) {
                return '';
            }
            try {
                // resolve() is unavoidable here: a Settings `serializeToForum`
                // transform is a plain closure with no DI entry point (unlike
                // ->fields() classes), so the filesystem factory cannot be
                // constructor-injected at this position.
                $factory = resolve(\Illuminate\Contracts\Filesystem\Factory::class);
                $disk = $factory->disk('flarum-assets');
                $path = FallbackStorage::DIR . '/' . $value;
                return $disk->exists($path) ? $disk->url($path) : '';
            } catch (\Throwable $e) {
                return '';
            }
        }),

    (new Extend\Routes('api'))
        ->post('/thumb-sliders/uploads', 'thumb-sliders.upload', UploadFallbackImageController::class)
        ->get('/thumb-sliders/uploads', 'thumb-sliders.list', ListFallbackImagesController::class)
        ->delete('/thumb-sliders/uploads/{filename}', 'thumb-sliders.delete', DeleteFallbackImageController::class),
];
