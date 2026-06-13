<?php

namespace TryHackX\ThumbSliders\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TryHackX\ThumbSliders\FallbackStorage;

class UploadFallbackImageController implements RequestHandlerInterface
{
    /** Maximum upload size in bytes (5 MB). */
    const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Allowed file extensions (lowercase, no dot). Raster only — SVG is
     * deliberately excluded (see the validation block in handle()).
     */
    const ALLOWED_EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'avif'];

    /** Allowed MIME types (raster image formats only). */
    const ALLOWED_MIMES = [
        'image/webp',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/avif',
    ];

    protected FilesystemFactory $filesystem;

    public function __construct(FilesystemFactory $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $file */
        $file = $files['file'] ?? null;

        if (!$file instanceof UploadedFileInterface) {
            return new JsonResponse(['error' => 'No file provided.'], 400);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['error' => 'Upload error code: ' . $file->getError()], 400);
        }

        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            return new JsonResponse(['error' => 'Empty file.'], 400);
        }
        if ($size > self::MAX_SIZE) {
            return new JsonResponse(['error' => 'File is too large (max 5 MB).'], 413);
        }

        $clientName = (string) $file->getClientFilename();
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return new JsonResponse(['error' => 'Unsupported file extension.'], 415);
        }

        $stream = $file->getStream();
        $stream->rewind();
        $contents = (string) $stream->getContents();

        // MIME validation
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($contents) ?: '';

        // Raster images ONLY. SVG is intentionally not accepted: it is an XML
        // document that can execute script through many vectors (onload= and
        // other event attributes, <use href="javascript:...">, <foreignObject>,
        // CSS expression(), …), and the fallback image is served from a public
        // URL — so a crafted SVG would be stored XSS reachable by every visitor.
        // The fallback only needs a raster placeholder, so we drop SVG entirely
        // rather than attempt to sanitise an attacker-controlled XML document.
        if (!in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            return new JsonResponse(['error' => 'Unsupported file type (MIME: ' . $detectedMime . ').'], 415);
        }
        // getimagesizefromstring() only recognises raster formats, so it is also
        // a backstop that rejects any SVG/XML that slipped past the extension and
        // MIME checks above.
        $info = @getimagesizefromstring($contents);
        if ($info === false) {
            return new JsonResponse(['error' => 'File is not a valid image.'], 415);
        }

        // Generate safe filename: {timestamp}-{rand}-{slug}.{ext}
        $baseName = pathinfo($clientName, PATHINFO_FILENAME);
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $baseName));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'image';
        }
        $slug = substr($slug, 0, 60);
        $filename = time() . '-' . bin2hex(random_bytes(4)) . '-' . $slug . '.' . $ext;

        $disk = $this->filesystem->disk('flarum-assets');
        $path = FallbackStorage::DIR . '/' . $filename;

        try {
            $disk->put($path, $contents);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Could not save file: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'filename' => $filename,
            'url' => $disk->url($path),
            'size' => $size,
        ], 201);
    }
}
