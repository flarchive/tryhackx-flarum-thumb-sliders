<?php

namespace TryHackX\ThumbSliders\Tests\unit;

use Flarum\Testing\unit\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TryHackX\ThumbSliders\ImageExtractor;

class ImageExtractorTest extends TestCase
{
    #[Test]
    public function extracts_img_tags_from_s9e_xml_in_order(): void
    {
        $xml = '<r><IMG src="https://example.com/a.jpg"><s>[img]</s>https://example.com/a.jpg<e>[/img]</e></IMG>'
            . ' text <IMG alt="" src="https://example.com/b.png?x=1&amp;y=2" title=""/></r>';

        $this->assertSame(
            ['https://example.com/a.jpg', 'https://example.com/b.png?x=1&y=2'],
            ImageExtractor::extractFromXml($xml)
        );
    }

    #[Test]
    public function skips_excluded_and_data_uri_images(): void
    {
        $xml = '<r>'
            . '<IMG src="https://example.com/emoji/smile.png"/>'
            . '<IMG src="https://example.com/avatars/u1.png"/>'
            . '<IMG src="https://example.com/vector.svg"/>'
            . '<IMG src="DATA:image/png;base64,AAAA"/>'
            . '<IMG src="https://example.com/poster.jpg"/>'
            . '</r>';

        $this->assertSame(['https://example.com/poster.jpg'], ImageExtractor::extractFromXml($xml));
    }

    #[Test]
    public function does_not_match_longer_tag_names_or_prefixed_src_attributes(): void
    {
        $xml = '<r><IMGUR src="https://example.com/not-an-img.jpg"/>'
            . '<IMG data-src="https://example.com/lazy.jpg" src="https://example.com/real.jpg"/></r>';

        $this->assertSame(['https://example.com/real.jpg'], ImageExtractor::extractFromXml($xml));
    }

    #[Test]
    public function fast_path_applies_size_limits_to_tags_with_explicit_dimensions(): void
    {
        $xml = '<r>'
            . '<IMG src="https://example.com/tiny.jpg" width="20" height="20"/>'
            . '<IMG src="https://example.com/huge.jpg" width="9000"/>'
            . '<IMG src="https://example.com/ok.jpg" width="300" height="450"/>'
            . '<IMG src="https://example.com/unknown-size.jpg"/>'
            . '</r>';

        $this->assertSame(
            ['https://example.com/ok.jpg', 'https://example.com/unknown-size.jpg'],
            ImageExtractor::extractFromXml($xml, 10, 50, 5000)
        );

        // Without limits (the defaults) nothing is filtered by size.
        $this->assertCount(4, ImageExtractor::extractFromXml($xml));
    }

    #[Test]
    public function caps_the_number_of_images_and_ignores_duplicates_when_counting(): void
    {
        $xml = '<r>'
            . '<IMG src="https://example.com/1.jpg"/>'
            . '<IMG src="https://example.com/1.jpg"/>'
            . '<IMG src="https://example.com/2.jpg"/>'
            . '<IMG src="https://example.com/3.jpg"/>'
            . '</r>';

        $this->assertSame(
            ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            ImageExtractor::extractFromXml($xml, 2)
        );

        // 0 / negative is clamped to 1 (it used to return 1 here but 0 from extract()).
        $this->assertSame(['https://example.com/1.jpg'], ImageExtractor::extractFromXml($xml, 0));

        $many = '<r>' . implode('', array_map(fn ($i) => '<IMG src="https://example.com/' . $i . '.jpg"/>', range(1, 30))) . '</r>';
        $this->assertCount(20, ImageExtractor::extractFromXml($many, 100));
    }

    #[Test]
    public function extracts_image_uploads_from_fof_upload_tags_only(): void
    {
        $xml = '<r><UPL-IMAGE-PREVIEW url="https://example.com/assets/files/a.webp">[upl-image-preview url=...]</UPL-IMAGE-PREVIEW>'
            . '<UPL-FILE uuid="x" size="1" url="https://example.com/assets/files/manual.pdf">[upl-file]</UPL-FILE>'
            . '<UPL-IMAGE-PREVIEW url="https://example.com/assets/files/b.avif?v=2">x</UPL-IMAGE-PREVIEW></r>';

        $this->assertSame(
            ['https://example.com/assets/files/a.webp', 'https://example.com/assets/files/b.avif?v=2'],
            ImageExtractor::extractFromXml($xml)
        );
    }

    #[Test]
    public function plain_text_or_empty_xml_yields_nothing(): void
    {
        $this->assertSame([], ImageExtractor::extractFromXml(''));
        $this->assertSame([], ImageExtractor::extractFromXml('<t>Just &lt;IMG src="https://example.com/x.jpg"&gt; text</t>'));
    }

    #[Test]
    public function html_path_filters_by_attribute_and_style_dimensions(): void
    {
        $html = '<p>'
            . '<img src="https://example.com/small.jpg" width="16" height="16">'
            . '<img src="https://example.com/styled-small.jpg" style="width: 10px; height: 10px">'
            . '<img src="https://example.com/big.jpg" width="640" height="960">'
            . '<img src="data:image/gif;base64,R0lGOD">'
            . '<img src="https://example.com/big.jpg">'
            . '<img src="https://example.com/no-size.jpg">'
            . '</p>';

        $this->assertSame(
            ['https://example.com/big.jpg', 'https://example.com/no-size.jpg'],
            ImageExtractor::extract($html, 50, 5000, 50, 5000, 10)
        );
    }
}
