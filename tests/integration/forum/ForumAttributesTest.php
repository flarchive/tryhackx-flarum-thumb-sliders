<?php

namespace TryHackX\ThumbSliders\Tests\integration\forum;

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ForumAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('tryhackx-thumb-sliders');
    }

    protected function forumAttributes(): array
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data']['attributes'];
    }

    #[Test]
    public function defaults_are_serialized(): void
    {
        $attributes = $this->forumAttributes();

        $this->assertSame(150, $attributes['thumbSlidersSliderWidth']);
        $this->assertSame(1200, $attributes['thumbSlidersAutoplaySpeed']);
        $this->assertTrue($attributes['thumbSlidersEnabled']);
        $this->assertSame('none', $attributes['thumbSlidersFallbackMode']);
        $this->assertSame('', $attributes['thumbSlidersFallbackImageUrl']);
        $this->assertSame('show', $attributes['tryhackxAvatarModeDesktop']);
        $this->assertSame('show', $attributes['tryhackxAvatarModeMobile']);
    }

    #[Test]
    public function out_of_range_and_invalid_values_are_normalised(): void
    {
        $this->setting('tryhackx-thumb-sliders.slider_width', '99999');
        $this->setting('tryhackx-thumb-sliders.autoplay_speed', '50');
        $this->setting('tryhackx-thumb-sliders.enabled', '0');
        $this->setting('tryhackx-thumb-sliders.fallback_mode', 'bogus');
        $this->setting('tryhackx-thumb-sliders.fallback_image', '../../avatars/x.png');
        $this->setting('tryhackx-avatars.mode_desktop', 'hide');
        $this->setting('tryhackx-avatars.mode_mobile', 'nonsense');

        $attributes = $this->forumAttributes();

        $this->assertSame(400, $attributes['thumbSlidersSliderWidth']);
        $this->assertSame(500, $attributes['thumbSlidersAutoplaySpeed']);
        $this->assertFalse($attributes['thumbSlidersEnabled']);
        $this->assertSame('none', $attributes['thumbSlidersFallbackMode']);
        $this->assertSame('', $attributes['thumbSlidersFallbackImageUrl']);
        $this->assertSame('hide', $attributes['tryhackxAvatarModeDesktop']);
        $this->assertSame('show', $attributes['tryhackxAvatarModeMobile']);
    }

    #[Test]
    public function a_missing_or_non_raster_fallback_file_is_not_exposed(): void
    {
        $this->setting('tryhackx-thumb-sliders.fallback_mode', 'custom');
        $this->setting('tryhackx-thumb-sliders.fallback_image', 'does-not-exist.png');

        $this->assertSame('', $this->forumAttributes()['thumbSlidersFallbackImageUrl']);
    }

    #[Test]
    public function zero_width_falls_back_to_the_default(): void
    {
        $this->setting('tryhackx-thumb-sliders.slider_width', '0');

        $this->assertSame(150, $this->forumAttributes()['thumbSlidersSliderWidth']);
    }
}
