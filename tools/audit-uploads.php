<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/database/database.php';
require dirname(__DIR__) . '/includes/evidence-storage.php';
require dirname(__DIR__) . '/includes/profile-photo-storage.php';

$details = in_array('--details', array_slice($argv, 1), true);
$unknown = array_values(array_diff(array_slice($argv, 1), ['--details']));
if ($unknown) {
    fwrite(STDERR, "Usage: php tools/audit-uploads.php [--details]\n");
    exit(2);
}

$root = dirname(__DIR__);
$database = new MaintainProDatabase(br_database());
$evidenceReferences = array_values(array_map('strval', $database->evidenceFiles()));
$profileReferences = array_values(array_map('strval', $database->profilePhotoFiles()));

$storedFiles = static function (string $relativeDirectory) use ($root): array {
    $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    if (!is_dir($directory)) return [];
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink() || $file->getFilename() === '.gitignore') continue;
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }
    sort($files, SORT_STRING);
    return $files;
};

$evidenceFiles = $storedFiles('uploads/evidence');
$profileFiles = $storedFiles('uploads/profiles');
$missingEvidence = array_values(array_diff(array_unique($evidenceReferences), $evidenceFiles));
$missingProfiles = array_values(array_diff(array_unique($profileReferences), $profileFiles));
$orphanEvidence = array_values(array_diff($evidenceFiles, array_unique($evidenceReferences)));
$orphanProfiles = array_values(array_diff($profileFiles, array_unique($profileReferences)));
$duplicates = static fn(array $paths): array => array_keys(array_filter(array_count_values($paths), static fn(int $count): bool => $count > 1));
$duplicateEvidence = $duplicates($evidenceReferences);
$duplicateProfiles = $duplicates($profileReferences);
$invalidEvidence = array_values(array_filter($evidenceFiles, static fn(string $path): bool => EvidenceStorage::storedFile($path) === null));
$invalidProfiles = array_values(array_filter($profileFiles, static fn(string $path): bool => ProfilePhotoStorage::storedFile($path) === null));
$testArtifacts = array_values(array_filter(array_merge($evidenceFiles, $profileFiles), static fn(string $path): bool =>
    str_contains($path, '/maintainpro_test_') || str_ends_with($path, '.tmp')));

$findings = [
    'database evidence references' => count($evidenceReferences),
    'stored evidence files' => count($evidenceFiles),
    'database evidence records with missing files' => count($missingEvidence),
    'evidence files without database records' => count($orphanEvidence),
    'duplicate evidence paths in database' => count($duplicateEvidence),
    'invalid evidence files' => count($invalidEvidence),
    'database profile references' => count($profileReferences),
    'stored profile files' => count($profileFiles),
    'database profile records with missing files' => count($missingProfiles),
    'profile files without database records' => count($orphanProfiles),
    'duplicate profile paths in database' => count($duplicateProfiles),
    'invalid profile files' => count($invalidProfiles),
    'development/test upload artifacts' => count($testArtifacts),
];
foreach ($findings as $label => $count) echo $label . ': ' . $count . PHP_EOL;

if ($details) {
    foreach ([
        'Missing evidence' => $missingEvidence, 'Orphan evidence' => $orphanEvidence,
        'Duplicate evidence' => $duplicateEvidence, 'Invalid evidence' => $invalidEvidence,
        'Missing profiles' => $missingProfiles, 'Orphan profiles' => $orphanProfiles,
        'Duplicate profiles' => $duplicateProfiles, 'Invalid profiles' => $invalidProfiles,
        'Test artifacts' => $testArtifacts,
    ] as $label => $paths) foreach ($paths as $path) echo $label . ': ' . $path . PHP_EOL;
}

$problemCount = count($missingEvidence) + count($orphanEvidence) + count($duplicateEvidence) + count($invalidEvidence)
    + count($missingProfiles) + count($orphanProfiles) + count($duplicateProfiles) + count($invalidProfiles) + count($testArtifacts);
exit($problemCount === 0 ? 0 : 1);
