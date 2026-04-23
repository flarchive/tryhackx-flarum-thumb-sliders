<?php

namespace TryHackX\ThumbSliders\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListFallbackImagesController implements RequestHandlerInterface
{
    const STORAGE_DIR = 'extensions/tryhackx-thumb-sliders/fallback';

    protected FilesystemFactory $filesystem;

    public function __construct(FilesystemFactory $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $disk = $this->filesystem->disk('flarum-assets');

        $items = [];
        try {
            if ($disk->exists(self::STORAGE_DIR)) {
                $files = $disk->files(self::STORAGE_DIR);
                foreach ($files as $path) {
                    $filename = basename($path);
                    // Skip hidden / unsafe entries
                    if ($filename === '' || $filename[0] === '.') {
                        continue;
                    }
                    $items[] = [
                        'filename' => $filename,
                        'url' => $disk->url($path),
                        'size' => $disk->size($path),
                        'modified' => $disk->lastModified($path),
                    ];
                }
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Could not list files: ' . $e->getMessage()], 500);
        }

        // Sort newest first
        usort($items, function ($a, $b) {
            return ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0);
        });

        return new JsonResponse(['data' => $items]);
    }
}
