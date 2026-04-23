<?php

namespace TryHackX\ThumbSliders\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteFallbackImageController implements RequestHandlerInterface
{
    const STORAGE_DIR = 'extensions/tryhackx-thumb-sliders/fallback';
    const SETTING_ACTIVE = 'tryhackx-thumb-sliders.fallback_image';

    protected FilesystemFactory $filesystem;
    protected SettingsRepositoryInterface $settings;

    public function __construct(FilesystemFactory $filesystem, SettingsRepositoryInterface $settings)
    {
        $this->filesystem = $filesystem;
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $filename = (string) ($request->getAttribute('routeParameters')['filename'] ?? '');

        // Strict sanitization: only [a-zA-Z0-9._-], no path separators, no traversal
        if ($filename === ''
            || strpos($filename, '/') !== false
            || strpos($filename, '\\') !== false
            || strpos($filename, '..') !== false
            || !preg_match('/^[a-zA-Z0-9._-]+$/', $filename)
        ) {
            return new JsonResponse(['error' => 'Invalid filename.'], 400);
        }

        $disk = $this->filesystem->disk('flarum-assets');
        $path = self::STORAGE_DIR . '/' . $filename;

        if (!$disk->exists($path)) {
            return new JsonResponse(['error' => 'File not found.'], 404);
        }

        try {
            $disk->delete($path);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Could not delete file: ' . $e->getMessage()], 500);
        }

        // If the deleted file was the active fallback, clear the setting
        if ($this->settings->get(self::SETTING_ACTIVE) === $filename) {
            $this->settings->set(self::SETTING_ACTIVE, '');
        }

        return new EmptyResponse(204);
    }
}
