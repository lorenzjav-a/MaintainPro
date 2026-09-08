<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
function payload(): array
{
    $actor = br_actor();
    if (!$actor) throw new DomainException('Sign in to continue.');
    return [
        'mode' => br_is_demo() ? 'demo' : 'account', 'actor' => $actor,
        'cases' => ComplaintDemo::visible(br_state(), $actor),
        'users' => !br_is_demo() && $actor['role'] === 'official' ? br_store()->users($actor['id']) : [],
        'categories' => ComplaintDemo::CATEGORIES, 'teams' => ComplaintDemo::TEAMS,
        'statuses' => ComplaintDemo::STATUSES, 'priorities' => ComplaintDemo::PRIORITIES,
    ];
}
header('Content-Type: application/json; charset=utf-8');
try {
    $actor = br_actor();
    if (!$actor) {
        http_response_code(401);
        throw new DomainException('Your session has ended or your account is inactive. Sign in to continue.');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        if (($_GET['export'] ?? '') === 'csv') {
            if ($actor['role'] !== 'official') {
                http_response_code(403);
                throw new DomainException('Only a barangay official can export reports.');
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="barangayresolve-complaints.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Complaint ID', 'Title', 'Category', 'Location', 'Status', 'Priority', 'Assigned team', 'Submitted', 'Resident suggestion', 'Official recommendation', 'Resolution', 'Resident feedback', 'Reopen count']);
            foreach (br_state()['cases'] as $c) {
                $row = [$c['id'], $c['title'], $c['category'], $c['location'], $c['status'], $c['priority'], $c['team'], $c['createdAt'], $c['suggestion'], $c['recommendation'], $c['resolution']['notes'] ?? '', $c['feedback'], (string)$c['reopenCount']];
                fputcsv($out, array_map(fn($v) => preg_match('/^[\s]*[=+\-@\t\r\n]/u', $v) ? "'" . $v : $v, $row));
            }
            fclose($out);
            exit;
        }
        echo json_encode(payload(), JSON_THROW_ON_ERROR);
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        throw new DomainException('Use GET or POST.');
    }
    if (!hash_equals($_SESSION['br_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        throw new DomainException('Your page has expired. Refresh it and try again.');
    }
    $raw = file_get_contents('php://input', false, null, 0, 1600001);
    if (strlen($raw) > 1600000) throw new DomainException('This request is too large. Use a photo smaller than 1 MB.');
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new DomainException('Invalid request.');
    $action = $input['action'] ?? '';
    $data = $input['data'] ?? [];
    if (!is_string($action) || !is_array($data)) throw new DomainException('Invalid request.');
    $id = is_string($input['id'] ?? null) ? $input['id'] : '';
    if (in_array($action, ['switch_role', 'reset'], true)) {
        if (!br_is_demo()) {
            http_response_code(403);
            throw new DomainException('Role switching and sample resets are only available in the separate demo.');
        }
        if ($action === 'reset') $_SESSION['br_state'] = ComplaintDemo::seed();
        else {
            $nextActor = ComplaintDemo::actor(is_string($data['role'] ?? null) ? $data['role'] : '', is_string($data['team'] ?? null) ? $data['team'] : ($_SESSION['br_team'] ?? 'Sanitation team'));
            $_SESSION['br_role'] = $nextActor['role'];
            if ($nextActor['team']) $_SESSION['br_team'] = $nextActor['team'];
        }
    } elseif (in_array($action, ['create_user', 'update_user', 'profile'], true)) {
        if (br_is_demo()) throw new DomainException('Sign in to the saved workspace to manage real accounts.');
        if ($action === 'create_user') br_store()->createUser($actor['id'], $data);
        if ($action === 'update_user') br_store()->updateUser($actor['id'], $id, $data);
        if ($action === 'profile') {
            br_store()->updateProfile($actor['id'], $data);
            $_SESSION['br_auth_version'] = (int)br_store()->user($actor['id'])['auth_version'];
            session_regenerate_id(true);
        }
    } elseif (br_is_demo()) {
        $candidate = br_state();
        if ($action === 'submit') $id = ComplaintDemo::submit($candidate, $actor, $data);
        else ComplaintDemo::apply($candidate, $actor, $id, $action, $data);
        if (strlen(serialize($candidate)) > 12000000) throw new DomainException('This demo session is full. Reset the sample data to start again.');
        $_SESSION['br_state'] = $candidate;
    } else {
        $id = br_store()->mutate($actor['id'], $action, $id, $data, $input['version'] ?? null);
    }
    echo json_encode(['ok' => true, 'id' => $id, 'state' => payload()], JSON_THROW_ON_ERROR);
} catch (ConflictException $e) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'state' => payload()], JSON_THROW_ON_ERROR);
} catch (DomainException | JsonException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'The request could not be completed. Please try again.']);
}
