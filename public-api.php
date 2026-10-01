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
    if ($input['action'] === 'submit' && isset($_SESSION['br_user_id'])) {
        $actor = br_actor();
        if (!$actor) throw new DomainException('Sign in again before submitting a concern.');
        $id = br_store()->submitAccount($actor['id'], $data);
        echo json_encode(['ok' => true, 'redirect' => 'complaint.php?id=' . rawurlencode($id) . '&saved=submit'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = match ($input['action']) {
        'guidance' => ['residentGuidance' => br_store()->suggestions($data)],
        // Keep old open tabs compatible; these values are now resident guidance only.
        'suggestions' => ['suggestions' => br_store()->suggestions($data)],
        'submit' => ['receipt' => br_store()->submitGuest($data, $client)],
        'track' => ['concern' => br_store()->track($data['reference'] ?? '', $data['trackingCode'] ?? '', $client)],
        'followup' => ['concern' => br_store()->submitFollowup($data, $client)],
        'conversation' => ['conversation' => br_store()->guestConversation($data['reference'] ?? null,$data['trackingCode'] ?? null,$client,filter_var($data['after'] ?? 0,FILTER_VALIDATE_INT) ?: 0)],
        'chat_status' => ['status' => br_store()->guestChatStatus($data['reference'] ?? null,$data['trackingCode'] ?? null,$client)],
        'older_messages' => ['conversation' => br_store()->olderGuestConversation($data['reference'] ?? null,$data['trackingCode'] ?? null,$client,filter_var($data['before'] ?? null,FILTER_VALIDATE_INT) ?: 0)],
        'send_message' => ['message' => br_store()->sendGuestMessage($data,$client)],
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
