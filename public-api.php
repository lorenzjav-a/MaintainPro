<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); throw new DomainException('Use POST.'); }
    if (!hash_equals($_SESSION['br_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) { http_response_code(403); throw new DomainException('Refresh the page before trying again.'); }
    $raw = file_get_contents('php://input', false, null, 0, 1600001);
    if (strlen($raw) > 1600000) throw new DomainException('Use a photo no larger than 1 MB.');
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || !is_string($input['action'] ?? null) || !is_array($input['data'] ?? null)) throw new DomainException('Invalid request.');
    $data = $input['data'];
    $client = $_SERVER['REMOTE_ADDR'] ?? 'local';
    $result = match ($input['action']) {
        'guidance' => ['residentGuidance' => br_store()->suggestions($data)],
        // Keep old open tabs compatible; these values are now resident guidance only.
        'suggestions' => ['suggestions' => br_store()->suggestions($data)],
        'submit' => ['receipt' => br_store()->submitGuest($data, $client)],
        'track' => ['concern' => br_store()->track($data['reference'] ?? '', $data['trackingCode'] ?? '', $client)],
        'followup' => ['concern' => br_store()->submitFollowup($data, $client)],
        default => throw new DomainException('Unknown public action.'),
    };
    echo json_encode(['ok' => true] + $result, JSON_THROW_ON_ERROR);
} catch (DomainException | JsonException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to complete this request. Please try again.']);
}
