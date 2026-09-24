<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';

/** @internal Emergency output must not depend on the database or shared layouts. */
function br_error_is_json_request(): bool
{
    $script = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (in_array($script, ['api.php', 'auth.php', 'public-api.php'], true)) return true;
    return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

/** @internal */
function br_error_log(Throwable|string $error, string $requestId): void
{
    $message = $error instanceof Throwable ? $error->__toString() : $error;
    error_log('[MaintainPro ' . $requestId . '] ' . $message);
}

/** @internal */
function br_error_clear_output(): void
{
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) break;
    }
}

/** @internal */
function br_error_respond(Throwable $error, string $requestId): never
{
    static $responding = false;
    if ($responding) exit(1);
    $responding = true;
    br_error_log($error, $requestId);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $error->__toString() . PHP_EOL);
        exit(1);
    }

    br_error_clear_output();
    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    $debug = (bool)br_app_config()['debug'];
    $friendly = 'Something went wrong. Please try again. If the problem continues, contact the administrator.';
    $details = $error->__toString();
    if (br_error_is_json_request()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        $body = ['ok' => false, 'error' => $debug ? $error->getMessage() : $friendly, 'request_id' => $requestId];
        if ($debug) $body['debug'] = $details;
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit(1);
    }

    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    $title = $debug ? 'MaintainPro development error' : 'Unable to complete this request';
    $message = $debug ? $error->getMessage() : $friendly;
    $assetVersion = (string)(@filemtime(dirname(__DIR__) . '/assets/css/app.css') ?: 1);
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $escape($title) . '</title><link rel="stylesheet" href="assets/vendor/bootstrap.min.css">'
        . '<link rel="stylesheet" href="assets/css/app.css?v=' . $escape($assetVersion) . '"></head><body>'
        . '<main class="container py-5"><section class="panel p-4 mx-auto" style="max-width:720px">'
        . '<h1 class="section-title">' . $escape($title) . '</h1><p>' . $escape($message) . '</p>'
        . '<p class="form-text">Reference: ' . $escape($requestId) . '</p>';
    if ($debug) echo '<pre class="mt-4 p-3 bg-light border rounded text-wrap">' . $escape($details) . '</pre>';
    echo '<a class="btn btn-primary" href="index.php">Return to MaintainPro</a></section></main></body></html>';
    exit(1);
}

function br_install_error_handler(): void
{
    static $installed = false;
    if ($installed) return;
    $installed = true;

    $config = br_app_config();
    if (!is_dir($config['log_directory'])) {
        @mkdir($config['log_directory'], 0700, true);
    }
    ini_set('log_errors', '1');
    if (is_dir($config['log_directory']) && is_writable($config['log_directory'])) {
        ini_set('error_log', $config['log_file']);
    }
    ini_set('display_errors', $config['debug'] ? '1' : '0');
    ini_set('display_startup_errors', $config['debug'] ? '1' : '0');

    // CLI tools retain PHP's normal detailed failure behavior.
    if (PHP_SAPI === 'cli') return;

    ob_start();
    $requestId = bin2hex(random_bytes(6));
    set_exception_handler(static function (Throwable $error) use ($requestId): never {
        br_error_respond($error, $requestId);
    });
    register_shutdown_function(static function () use ($requestId): void {
        $last = error_get_last();
        if (!$last || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
        br_error_respond(new ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line']), $requestId);
    });
}

br_install_error_handler();
