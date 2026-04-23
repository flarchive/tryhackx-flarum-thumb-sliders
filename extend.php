<?php

namespace TryHackX\ThumbSliders;

use Flarum\Extend;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Discussion\Discussion;
use TryHackX\ThumbSliders\Api\Controller\UploadFallbackImageController;
use TryHackX\ThumbSliders\Api\Controller\ListFallbackImagesController;
use TryHackX\ThumbSliders\Api\Controller\DeleteFallbackImageController;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    // Ensure firstPost is always included in discussion list API responses
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addInclude('firstPost'),

    // Add thumbImages attribute to Discussion API response
    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attributes(function ($serializer, $model, $attributes) {
            try {
                $firstPost = $model->firstPost;

                if (!$firstPost || $firstPost->type !== 'comment') {
                    $attributes['thumbImages'] = [];
                    return $attributes;
                }

                // Get the raw XML content from the database (not the accessor-transformed text)
                $rawXml = $firstPost->getRawOriginal('content');

                if (empty($rawXml)) {
                    $attributes['thumbImages'] = [];
                    return $attributes;
                }

                // Get settings
                $settings = resolve('flarum.settings');
                $maxImages = (int) $settings->get('tryhackx-thumb-sliders.max_images', 10);

                // Try fast XML extraction first (no rendering needed)
                $images = ImageExtractor::extractFromXml($rawXml, $maxImages);

                // If no images found via XML, fall back to full HTML rendering
                if (empty($images)) {
                    $formatter = resolve(\Flarum\Formatter\Formatter::class);
                    $html = $formatter->render($rawXml, $firstPost);

                    $minSize = (int) $settings->get('tryhackx-thumb-sliders.min_img_size', 50);
                    $maxSize = (int) $settings->get('tryhackx-thumb-sliders.max_img_size', 5000);

                    $images = ImageExtractor::extract($html, $minSize, $maxSize, $minSize, $maxSize, $maxImages);
                }

                $attributes['thumbImages'] = $images;
            } catch (\Throwable $e) {
                $attributes['thumbImages'] = [];
            }

            return $attributes;
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
        ->serializeToForum('thumbSlidersSliderWidth', 'tryhackx-thumb-sliders.slider_width')
        ->serializeToForum('thumbSlidersAutoplaySpeed', 'tryhackx-thumb-sliders.autoplay_speed')
        ->serializeToForum('thumbSlidersEnabled', 'tryhackx-thumb-sliders.enabled', function ($value) {
            return (bool) $value;
        })
        ->serializeToForum('thumbSlidersFallbackMode', 'tryhackx-thumb-sliders.fallback_mode')
        ->serializeToForum('thumbSlidersFallbackImageUrl', 'tryhackx-thumb-sliders.fallback_image', function ($value) {
            if (empty($value)) {
                return '';
            }
            try {
                $factory = resolve(\Illuminate\Contracts\Filesystem\Factory::class);
                $disk = $factory->disk('flarum-assets');
                $path = 'extensions/tryhackx-thumb-sliders/fallback/' . $value;
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
