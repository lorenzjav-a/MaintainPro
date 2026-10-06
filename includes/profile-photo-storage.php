<?php
declare(strict_types=1);

/** Validates account photos and keeps a protected file copy outside the public asset tree. */
final class ProfilePhotoStorage
{
    private const MAX_BYTES = 1048576;
    private const MAX_PIXELS = 20000000;
    private const PATH_PREFIX = 'uploads/profiles/';
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @return array{file_path:string,mime_type:string,data:string} */
    public static function storeDataUri(mixed $dataUri): array
    {
        [$bytes, $mime] = self::validatedDataUri($dataUri);
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
            throw new RuntimeException('The profile photo could not be saved.');
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('The profile photo could not be finalized.');
        }
        return ['file_path' => self::prefix() . $filename, 'mime_type' => $mime, 'data' => $bytes];
    }

    /** @return array{data:string,mime_type:string,file_size:int,extension:string}|null */
    public static function databasePhoto(mixed $bytes, mixed $declaredMime): ?array
    {
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES || !is_string($declaredMime)) return null;
        $image = @getimagesizefromstring($bytes);
        $mime = is_array($image) ? ($image['mime'] ?? '') : '';
        $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
        if ($mime !== $declaredMime || !isset(self::MIME_EXTENSIONS[$mime])
            || $width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) return null;
        return ['data'=>$bytes, 'mime_type'=>$mime, 'file_size'=>strlen($bytes), 'extension'=>self::MIME_EXTENSIONS[$mime]];
    }

    /** @return array{path:string,mime_type:string,file_size:int,extension:string}|null */
    public static function storedFile(string $filePath): ?array
    {
        $resolved = self::resolvedPath($filePath, $match);
        if ($resolved === null) return null;
        $size = filesize($resolved);
        $image = $size !== false && $size <= self::MAX_BYTES ? @getimagesize($resolved) : false;
        $mime = is_array($image) ? ($image['mime'] ?? '') : '';
        $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
        if (!isset(self::MIME_EXTENSIONS[$mime]) || self::MIME_EXTENSIONS[$mime] !== $match[1]
            || $width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) return null;
        return ['path' => $resolved, 'mime_type' => $mime, 'file_size' => (int)$size, 'extension' => $match[1]];
    }

    public static function delete(string $filePath): bool
    {
        $resolved = self::resolvedPath($filePath);
        return $resolved !== null && @unlink($resolved);
    }

    /** @return array{0:string,1:string} */
    private static function validatedDataUri(mixed $dataUri): array
    {
        if (!is_string($dataUri) || strlen($dataUri) > 1400000
            || !preg_match('~\Adata:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)\z~D', $dataUri, $match)) {
            throw new DomainException('Choose a JPG, PNG, or WebP profile photo smaller than 1 MB.');
        }
        $bytes = base64_decode($match[2], true);
        $image = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        $mime = is_array($image) ? ($image['mime'] ?? '') : '';
        $width = is_array($image) ? (int)($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int)($image[1] ?? 0) : 0;
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || $mime !== 'image/' . $match[1]
            || !isset(self::MIME_EXTENSIONS[$mime]) || $width < 1 || $height < 1
            || $width * $height > self::MAX_PIXELS) {
            throw new DomainException('The profile photo is invalid or exceeds 20 megapixels.');
        }
        return [$bytes, $mime];
    }

    private static function ensureDirectory(): void
    {
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('The profile photo storage directory could not be created.');
        }
    }

    private static function resolvedPath(string $filePath, ?array &$match = null): ?string
    {
        if (!preg_match('~\A' . preg_quote(self::prefix(), '~') . '[a-f0-9]{32}\.(jpg|png|webp)\z~D', $filePath, $found)) return null;
        $root = realpath(self::directory());
        if ($root === false || !is_dir($root)) return null;
        $resolved = realpath(self::directory() . DIRECTORY_SEPARATOR . basename($filePath));
        if ($resolved === false || !is_file($resolved) || dirname($resolved) !== $root) return null;
        $match = $found;
        return $resolved;
    }

    private static function directory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim(self::prefix(), '/'));
    }

    private static function prefix(): string
    {
        $test = getenv('BR_EVIDENCE_TEST_DATABASE');
        if ($test !== false && preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $test)) return self::PATH_PREFIX . $test . '/';
        return self::PATH_PREFIX;
    }
}
