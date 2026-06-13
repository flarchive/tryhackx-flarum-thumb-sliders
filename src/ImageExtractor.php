<?php

namespace TryHackX\ThumbSliders;

class ImageExtractor
{
    /**
     * Extract image URLs from HTML content, filtering by dimension attributes.
     *
     * @param string $html The HTML content to parse
     * @param int $minWidth Minimum image width
     * @param int $maxWidth Maximum image width
     * @param int $minHeight Minimum image height
     * @param int $maxHeight Maximum image height
     * @param int $maxImages Maximum number of images to return
     * @return array Array of image URL strings
     */
    public static function extract(
        string $html,
        int $minWidth = 100,
        int $maxWidth = 5000,
        int $minHeight = 100,
        int $maxHeight = 5000,
        int $maxImages = 10
    ): array {
        if (empty($html)) {
            return [];
        }

        // Hard safety cap - never return more than 20 images
        $maxImages = min($maxImages, 20);

        $images = [];

        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        // Suppress warnings for malformed HTML
        @$dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR
        );

        $imgTags = $dom->getElementsByTagName('img');

        foreach ($imgTags as $img) {
            if (count($images) >= $maxImages) {
                break;
            }

            $src = $img->getAttribute('src');

            if (empty($src)) {
                continue;
            }

            // Skip data URIs (likely icons/emojis)
            if (strpos($src, 'data:') === 0) {
                continue;
            }

            // Skip common non-content images
            if (self::isExcludedImage($src)) {
                continue;
            }

            // Check dimension attributes if available
            $width = (int) $img->getAttribute('width');
            $height = (int) $img->getAttribute('height');

            // If dimensions are specified in attributes, filter by them
            if ($width > 0 && ($width < $minWidth || $width > $maxWidth)) {
                continue;
            }
            if ($height > 0 && ($height < $minHeight || $height > $maxHeight)) {
                continue;
            }

            // Also check style attribute for dimensions
            $style = $img->getAttribute('style');
            if ($style) {
                $styleDimensions = self::parseDimensionsFromStyle($style);
                if ($styleDimensions['width'] > 0 && ($styleDimensions['width'] < $minWidth || $styleDimensions['width'] > $maxWidth)) {
                    continue;
                }
                if ($styleDimensions['height'] > 0 && ($styleDimensions['height'] < $minHeight || $styleDimensions['height'] > $maxHeight)) {
                    continue;
                }
            }

            // Fix Windows backslash paths in URLs
            $images[] = str_replace('\\', '/', $src);
        }

        return array_values(array_unique($images));
    }

    /**
     * Extract image URLs directly from Flarum's s9e XML format.
     * This is MUCH faster than rendering the XML to HTML first.
     *
     * Handles:
     * - Standard IMG tags: <IMG src="..."/>
     * - fof/upload UPL tags: <UPL ... url="..." .../>
     * - Markdown image tags: <e>![alt](url)</e> with <IMG src="url"/>
     *
     * @param string $xml The s9e TextFormatter XML content
     * @param int $maxImages Maximum number of images to return
     * @return array Array of image URL strings
     */
    public static function extractFromXml(string $xml, int $maxImages = 10): array
    {
        if (empty($xml)) {
            return [];
        }

        // Hard safety cap
        $maxImages = min($maxImages, 20);

        $images = [];

        // Extract from IMG tags (standard Flarum image format)
        // e.g., <IMG src="http://example.com/image.jpg" alt="" title=""/>
        if (preg_match_all('/<IMG[^>]+src="([^"]+)"[^>]*\/?>/i', $xml, $matches)) {
            foreach ($matches[1] as $src) {
                $src = html_entity_decode($src, ENT_QUOTES, 'UTF-8');
                $src = str_replace('\\', '/', $src); // Fix Windows paths
                if (!self::isExcludedImage($src) && strpos($src, 'data:') !== 0) {
                    $images[] = $src;
                    if (count($images) >= $maxImages) {
                        break;
                    }
                }
            }
        }

        // Extract from UPL tags (fof/upload)
        // e.g., <UPL ... url="..." .../>
        if (count($images) < $maxImages && preg_match_all('/<UPL[^>]+url="([^"]+)"[^>]*\/?>/i', $xml, $matches)) {
            foreach ($matches[1] as $src) {
                $src = html_entity_decode($src, ENT_QUOTES, 'UTF-8');
                $src = str_replace('\\', '/', $src); // Fix Windows paths
                // Only include image-type uploads
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)(\?.*)?$/i', $src)) {
                    if (!self::isExcludedImage($src)) {
                        $images[] = $src;
                        if (count($images) >= $maxImages) {
                            break;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Check if the image URL matches common non-content patterns (emojis, avatars, icons).
     */
    private static function isExcludedImage(string $src): bool
    {
        $excludePatterns = [
            '/emoji/',
            '/emojis/',
            '/smileys/',
            '/smilies/',
            '/avatar',
            '/favicon',
            '/icon',
            '/badge',
            '/logo',
            '.svg',
        ];

        $srcLower = strtolower($src);
        foreach ($excludePatterns as $pattern) {
            if (strpos($srcLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse width/height from inline style attribute.
     */
    private static function parseDimensionsFromStyle(string $style): array
    {
        $dimensions = ['width' => 0, 'height' => 0];

        if (preg_match('/width\s*:\s*(\d+)\s*px/i', $style, $matches)) {
            $dimensions['width'] = (int) $matches[1];
        }
        if (preg_match('/height\s*:\s*(\d+)\s*px/i', $style, $matches)) {
            $dimensions['height'] = (int) $matches[1];
        }

        return $dimensions;
    }
}
