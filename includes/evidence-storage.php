<?php
declare(strict_types=1);

/**
 * Stores validated evidence images outside complaint JSON.
 *
 * Database callers persist the returned relative file_path. They remain
 * responsible for deleting the file if their database transaction fails.
 */
final class EvidenceStorage
{
    private const MAX_BYTES = 1048576;
    private const MAX_PIXELS = 20000000;
    private const PATH_PREFIX = 'uploads/evidence/';
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{id:string,file_path:string,original_filename:string,mime_type:string,file_size:int,width:int,height:int}
     */
    public static function storeDataUri(mixed $dataUri, mixed $originalFilename = ''): array
    {
        [$bytes, $mime, $width, $height] = self::validatedDataUri($dataUri);
        $extension = self::MIME_EXTENSIONS[$mime];
        self::ensureDirectory();
        $directory = self::directory();

        do {
            $id = bin2hex(random_bytes(16));
            $filename = $id . '.' . $extension;
            $target = $directory . DIRECTORY_SEPARATOR . $filename;
        } while (file_exists($target));

        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . $id . '-' . bin2hex(random_bytes(6)) . '.tmp';
        $written = @file_put_contents($temporary, $bytes, LOCK_EX);
        if ($written !== strlen($bytes)) {
            if (is_file($temporary)) @unlink($temporary);
            throw new RuntimeException('The evidence image could not be saved.');
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('The evidence image could not be finalized.');
        }

        return [
            'id' => $id,
            'file_path' => self::prefix() . $filename,
            'original_filename' => self::safeOriginalFilename($originalFilename, $extension),
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Resolves and revalidates a stored image before it is sent to a browser.
     *
     * @return array{path:string,mime_type:string,file_size:int,width:int,height:int,extension:string}|null
     */
    public static function storedFile(string $filePath): ?array
    {
        $resolved = self::resolvedPath($filePath, $pathMatch);
        if ($resolved === null) return null;

        $size = filesize($resolved);
        $image = $size !== false && $size <= self::MAX_BYTES ? @getimagesize($resolved) : false;
        $mime = is_array($image) ? ($image['mime'] ?? '') : '';
        $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
        if (!isset(self::MIME_EXTENSIONS[$mime]) || self::MIME_EXTENSIONS[$mime] !== $pathMatch[1]
            || $width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        return [
            'path' => $resolved,
            'mime_type' => $mime,
            'file_size' => (int)$size,
            'width' => $width,
            'height' => $height,
            'extension' => $pathMatch[1],
        ];
    }

    /** Deletes a stored evidence file after an authorized caller requests it. */
    public static function delete(string $filePath): bool
    {
        $resolved = self::resolvedPath($filePath);
        return $resolved !== null && @unlink($resolved);
    }

    /** @return array{0:string,1:string,2:int,3:int} */
    private static function validatedDataUri(mixed $dataUri): array
    {
        if (!is_string($dataUri) || strlen($dataUri) > 1400000
            || !preg_match('~\Adata:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)\z~D', $dataUri, $match)) {
            throw new DomainException('Use a JPG, PNG, or WebP image smaller than 1 MB.');
        }

        $bytes = base64_decode($match[2], true);
        $image = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        $declaredMime = 'image/' . $match[1];
        $mime = is_array($image) ? ($image['mime'] ?? '') : '';
        $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || $mime !== $declaredMime
            || !isset(self::MIME_EXTENSIONS[$mime]) || $width < 1 || $height < 1
            || $width * $height > self::MAX_PIXELS) {
            throw new DomainException('The attached image is invalid or exceeds 20 megapixels.');
        }

        return [$bytes, $mime, $width, $height];
    }

    private static function ensureDirectory(): void
    {
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('The evidence storage directory could not be created.');
        }
    }

    private static function resolvedPath(string $filePath, ?array &$pathMatch = null): ?string
    {
        if (!preg_match('~\A'.preg_quote(self::prefix(),'~').'[a-f0-9]{32}\.(jpg|png|webp)\z~D', $filePath, $match)) return null;
        $directory = self::directory();
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) return null;
        $resolved = realpath($directory . DIRECTORY_SEPARATOR . basename($filePath));
        if ($resolved === false || !is_file($resolved) || dirname($resolved) !== $root) return null;
        $pathMatch = $match;
        return $resolved;
    }

    private static function safeOriginalFilename(mixed $originalFilename, string $extension): string
    {
        if (!is_string($originalFilename)) return 'evidence.' . $extension;
        $filename = basename(str_replace('\\', '/', trim($originalFilename)));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?? '';
        $filename = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, ". \t\n\r\0\x0B");
        if ($filename === '') return 'evidence.' . $extension;
        return mb_substr($filename, 0, 180);
    }

    private static function directory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,rtrim(self::prefix(),'/'));
    }

    private static function prefix(): string
    {
        $test=getenv('BR_EVIDENCE_TEST_DATABASE');
        if ($test!==false && preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/',$test)) return self::PATH_PREFIX.$test.'/';
        return self::PATH_PREFIX;
    }
}
