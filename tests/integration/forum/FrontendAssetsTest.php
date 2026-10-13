<?php

namespace TryHackX\ThumbSliders\Tests\integration\forum;

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Renders the forum and admin frontends so their LESS (incl. this extension's
 * less/forum.less and less/admin.less) is actually compiled.
 */
class FrontendAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('tryhackx-thumb-sliders');
    }

    protected function compiledCss(string $frontend): string
    {
        $css = '';
        foreach (glob($this->tmpDir() . "/public/assets/$frontend*.css") ?: [] as $file) {
            $css .= file_get_contents($file);
        }

        return $css;
    }

    #[Test]
    public function forum_and_admin_styles_compile(): void
    {
        $forum = $this->send($this->request('GET', '/'));
        $this->assertEquals(200, $forum->getStatusCode());

        $admin = $this->send($this->request('GET', '/admin', ['authenticatedAs' => 1]));
        $this->assertEquals(200, $admin->getStatusCode());

        $forumCss = $this->compiledCss('forum');
        $this->assertStringContainsString('.ThumbSlider--probing', $forumCss);
        $this->assertStringContainsString('prefers-reduced-motion', $forumCss);

        $adminCss = $this->compiledCss('admin');
        $this->assertStringContainsString('.ThumbSlidersFallbackManager__item', $adminCss);
        $this->assertStringContainsString('.AvatarSettings', $adminCss);
    }
}
