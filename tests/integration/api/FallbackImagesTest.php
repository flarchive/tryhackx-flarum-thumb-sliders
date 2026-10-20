<?php

namespace TryHackX\ThumbSliders\Tests\integration\api;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use TryHackX\ThumbSliders\FallbackStorage;

class FallbackImagesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** A valid 1x1 transparent PNG. */
    const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('tryhackx-thumb-sliders');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Uploads land in the shared test install's public assets dir.
        foreach (glob($this->fallbackDir() . '/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    protected function fallbackDir(): string
    {
        return $this->tmpDir() . '/public/assets/' . FallbackStorage::DIR;
    }

    protected function upload(string $contents, string $clientName, ?int $actorId = 1)
    {
        return $this->send($this->uploadRequest($contents, $clientName, $actorId));
    }

    protected function uploadRequest(string $contents, string $clientName, ?int $actorId = 1)
    {
        $file = new UploadedFile(
            (new StreamFactory())->createStream($contents),
            strlen($contents),
            UPLOAD_ERR_OK,
            $clientName,
            'application/octet-stream'
        );

        return $this->request('POST', '/api/thumb-sliders/uploads', $actorId ? ['authenticatedAs' => $actorId] : [])
            ->withUploadedFiles(['file' => $file]);
    }

    protected function json($response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    #[Test]
    public function only_admins_can_use_the_endpoints(): void
    {
        $png = base64_decode(self::PNG);

        // assertAdmin() answers guests with 403 (permission denied), like core.
        $this->assertEquals(403, $this->send($this->requestWithCsrfToken($this->uploadRequest($png, 'a.png', null)))->getStatusCode());
        $this->assertEquals(403, $this->upload($png, 'a.png', 2)->getStatusCode());

        $this->assertEquals(403, $this->send(
            $this->request('GET', '/api/thumb-sliders/uploads', ['authenticatedAs' => 2])
        )->getStatusCode());

        $this->assertEquals(403, $this->send(
            $this->request('DELETE', '/api/thumb-sliders/uploads/x.png', ['authenticatedAs' => 2])
        )->getStatusCode());
    }

    #[Test]
    public function admin_can_upload_list_select_and_delete_a_raster_image(): void
    {
        $response = $this->upload(base64_decode(self::PNG), 'My Poster!.png');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $filename = $this->json($response)['filename'];
        $this->assertMatchesRegularExpression('/^\d+-[0-9a-f]{8}-my-poster\.png$/', $filename);
        $this->assertFileExists($this->fallbackDir() . '/' . $filename);

        $list = $this->json($this->send($this->request('GET', '/api/thumb-sliders/uploads', ['authenticatedAs' => 1])));
        $this->assertSame([$filename], array_column($list['data'], 'filename'));

        // Select it, then check the forum payload exposes its URL.
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
        $settings->set('tryhackx-thumb-sliders.fallback_image', $filename);

        $forum = $this->json($this->send($this->request('GET', '/api')));
        $this->assertStringEndsWith('/' . FallbackStorage::DIR . '/' . $filename, $forum['data']['attributes']['thumbSlidersFallbackImageUrl']);

        // Deleting the active image also clears the selection.
        $delete = $this->send($this->request('DELETE', '/api/thumb-sliders/uploads/' . $filename, ['authenticatedAs' => 1]));
        $this->assertEquals(204, $delete->getStatusCode());
        $this->assertFileDoesNotExist($this->fallbackDir() . '/' . $filename);
        $this->assertSame('', $settings->get('tryhackx-thumb-sliders.fallback_image'));
    }

    #[Test]
    public function svg_and_disguised_files_are_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>';

        $bySvgExtension = $this->upload($svg, 'x.svg');
        $this->assertEquals(415, $bySvgExtension->getStatusCode());

        $disguised = $this->upload($svg, 'x.png');
        $this->assertEquals(415, $disguised->getStatusCode());

        // A (translated) message is returned for the admin UI to show.
        // flarum/testing registers extension locale files too late for the
        // translator to resolve them, so key coverage is checked in
        // tests/unit/LocaleTest.php instead of comparing the English text here.
        $this->assertNotEmpty($this->json($disguised)['error']);

        $this->assertSame([], glob($this->fallbackDir() . '/*') ?: []);
    }

    #[Test]
    public function delete_rejects_unsafe_or_unknown_filenames(): void
    {
        $this->assertEquals(400, $this->send(
            $this->request('DELETE', '/api/thumb-sliders/uploads/..', ['authenticatedAs' => 1])
        )->getStatusCode());

        $this->assertEquals(400, $this->send(
            $this->request('DELETE', '/api/thumb-sliders/uploads/a..png', ['authenticatedAs' => 1])
        )->getStatusCode());

        $missing = $this->send($this->request('DELETE', '/api/thumb-sliders/uploads/missing.png', ['authenticatedAs' => 1]));
        $this->assertEquals(404, $missing->getStatusCode());
        $this->assertNotEmpty($this->json($missing)['error']);
    }
}
