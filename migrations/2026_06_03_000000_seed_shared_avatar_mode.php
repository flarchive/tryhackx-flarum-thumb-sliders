<?php

/**
 * Seed the new shared, per-device avatar settings from the old single
 * `tryhackx-thumb-sliders.avatar_mode` value so existing installs keep their
 * choice after the split into desktop/mobile (and the rename none -> show).
 *
 * The new keys live in a neutral `tryhackx-avatars.*` namespace shared with
 * flarum-topic-rating. We only seed when the new keys are still unset, so this
 * never clobbers a value already chosen via the new UI, and it is safe to run
 * regardless of whether topic-rating is also installed.
 */

use Flarum\Settings\SettingsRepositoryInterface;

return [
    'up' => function () {
        // Migration up/down closures do not support constructor injection, so
        // resolve() is the only way to reach the settings repository here.
        /** @var SettingsRepositoryInterface $settings */
        $settings = resolve(SettingsRepositoryInterface::class);

        $old = $settings->get('tryhackx-thumb-sliders.avatar_mode');

        $map = [
            'none' => 'show',
            'with_image' => 'with_image',
            'always' => 'always',
        ];

        $new = $map[$old] ?? null;

        // Nothing meaningful was stored before -> let the serializer default
        // ('show') apply; don't write anything.
        if ($new === null) {
            return;
        }

        if ($settings->get('tryhackx-avatars.mode_desktop') === null) {
            $settings->set('tryhackx-avatars.mode_desktop', $new);
        }

        if ($settings->get('tryhackx-avatars.mode_mobile') === null) {
            $settings->set('tryhackx-avatars.mode_mobile', $new);
        }
    },

    'down' => function () {
        // No-op: the `tryhackx-avatars.*` keys are shared with flarum-topic-rating,
        // so rolling back this extension must not delete them.
    },
];
