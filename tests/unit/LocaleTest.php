<?php

namespace TryHackX\ThumbSliders\Tests\unit;

use Flarum\Testing\unit\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

class LocaleTest extends TestCase
{
    protected static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, string> flattened dot-keys => string
     */
    protected static function flatten(array $tree, string $prefix = ''): array
    {
        $flat = [];
        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += self::flatten($value, $path);
            } else {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }

    protected static function locale(string $code): array
    {
        return self::flatten(Yaml::parseFile(self::root() . "/locale/$code.yml"));
    }

    #[Test]
    public function english_and_polish_define_the_same_keys(): void
    {
        $en = array_keys(self::locale('en'));
        $pl = array_keys(self::locale('pl'));
        sort($en);
        sort($pl);

        $this->assertSame([], array_values(array_diff($en, $pl)), 'Missing in pl.yml');
        $this->assertSame([], array_values(array_diff($pl, $en)), 'Missing in en.yml');
    }

    #[Test]
    public function every_literal_key_used_in_the_code_exists(): void
    {
        $en = self::locale('en');
        $used = [];

        // Frontend: app.translator.trans('literal.key')
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/js/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (preg_match_all("/translator\\.trans\\(\\s*'([a-z0-9_.-]+)'\\s*[,)]/i", file_get_contents($file->getPathname()), $m)) {
                array_push($used, ...$m[1]);
            }
        }

        // Backend: controller error keys + direct trans() calls.
        foreach (glob(self::root() . '/src/Api/Controller/*.php') as $file) {
            $php = file_get_contents($file);
            if (preg_match_all("/->error\\('([a-z_]+)'/", $php, $m)) {
                foreach ($m[1] as $key) {
                    $used[] = 'tryhackx-thumb-sliders.api.' . $key;
                }
            }
            if (preg_match_all("/->trans\\('([a-z0-9_.-]+)'\\)/i", $php, $m)) {
                array_push($used, ...$m[1]);
            }
        }

        // Dynamically built shared avatar keys.
        foreach (['show', 'with_image', 'always', 'hide'] as $mode) {
            $used[] = 'tryhackx-avatars.admin.mode_' . $mode;
        }
        foreach (['desktop', 'mobile'] as $device) {
            $used[] = 'tryhackx-avatars.admin.device_' . $device;
        }

        $this->assertNotEmpty($used);
        $missing = array_values(array_diff(array_unique($used), array_keys($en)));
        $this->assertSame([], $missing, 'Translation keys used in code but missing from en.yml');
    }
}
