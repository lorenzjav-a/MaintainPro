<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        throw new DomainException('Use POST for account actions.');
    }
    if (!hash_equals($_SESSION['br_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        throw new DomainException('Your page has expired. Refresh it and try again.');
    }
    $raw = file_get_contents('php://input', false, null, 0, 16001);
    if (strlen($raw) > 16000) throw new DomainException('The request is too large.');
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input) || !is_string($input['action'] ?? null) || !is_array($input['data'] ?? [])) throw new DomainException('Invalid request.');
    $action = $input['action'];
    $data = $input['data'] ?? [];
    if (in_array($action, ['register', 'setup'], true) && ($data['password'] ?? '') !== ($data['confirm_password'] ?? null)) throw new DomainException('The passwords do not match.');
    switch ($action) {
        case 'login':
            br_enter_account(br_store()->login($data['email'] ?? null, $data['password'] ?? null, $_SERVER['REMOTE_ADDR'] ?? 'local'));
            break;
        case 'register':
            br_enter_account(br_store()->register($data));
            break;
        case 'setup':
            br_enter_account(br_store()->setup($data));
            break;
        case 'demo':
            unset($_SESSION['br_user_id']);
            session_regenerate_id(true);
            $_SESSION['br_mode'] = 'demo';
            $_SESSION['br_role'] = 'official';
            $_SESSION['br_team'] = 'Sanitation team';
            $_SESSION['br_state'] ??= ComplaintDemo::seed();
            $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
            break;
        case 'logout':
            unset($_SESSION['br_user_id']);
            session_regenerate_id(true);
            $_SESSION['br_mode'] = 'account';
            $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
            echo json_encode(['ok' => true, 'redirect' => 'login.php']);
            exit;
        default:
            throw new DomainException('Unknown account action.');
    }
    echo json_encode(['ok' => true, 'redirect' => 'index.php']);
} catch (DomainException | JsonException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'The account request could not be completed. Please try again.']);
}
