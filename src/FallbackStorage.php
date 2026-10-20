<?php

namespace TryHackX\ThumbSliders;

/**
 * Single source of truth for the fallback-image storage location.
 *
 * The path is referenced by the three upload/list/delete controllers and by the
 * serializer in extend.php; defining it once here keeps them from drifting (a
 * partial edit used to risk pointing controllers at different directories).
 */
final class FallbackStorage
{
    /** Subdirectory under the `flarum-assets` disk where fallback images live. */
    public const DIR = 'extensions/tryhackx-thumb-sliders/fallback';
}
