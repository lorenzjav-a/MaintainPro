<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/view.php';

// Every workspace entry point calls this before reading records or writing HTML.
function br_page(string $page, array $roles = []): array
{
    $actor = br_actor();
    if (!$actor) {
        header('Location: login.php');
        exit;
    }
    if ($actor['must_change_password']) {
        header('Location: login.php?view=change-password');
        exit;
    }
    $titles = [
        'overview' => match ($actor['role']) {'official' => 'Official dashboard', 'resident' => 'My dashboard', default => 'My work dashboard'},
        'complaints' => match ($actor['role']) {'official' => 'All concerns', 'resident' => 'My concerns', default => 'Work queue'},
        'history' => 'Resolution history', 'reports' => 'Reports & insights', 'solutions' => 'Solution library',
        'admin' => 'Administration', 'official-solutions' => 'Official action library', 'action-plans' => 'Weekly action plans',
        'my-action-plans' => 'My action plans',
        'messages' => 'Staff messages',
        'users' => 'User management', 'profile' => 'My profile', 'complaint' => 'Concern details',
        'notifications' => 'Notifications', 'user-guide' => 'User Guide',
        'settings' => 'Workspace settings', 'audit' => 'Audit history', 'blocked' => 'Blocked work',
        'new-complaint' => 'Report a community concern', 'user-create' => 'Create a workspace account', 'user-edit' => 'Manage account',
    ];
    $context = ['actor' => $actor, 'page' => $page, 'pageTitle' => $titles[$page], 'titles' => $titles];
    if ($roles && !in_array($actor['role'], $roles, true)) br_page_error($context, 403, 'Access denied', 'Your account does not have access to this page.');
    if (in_array($page, ['admin','users','user-create','user-edit','settings','audit'], true) && !$actor['is_system_admin']) br_page_error($context, 403, 'Access denied', 'System administrator access is required.');
    // Apply the same individual-assignment rules as the API.
    $lightweight = $page !== 'solutions';
    $context['cases'] = $lightweight ? [] : br_store()->visibleConcerns($actor['id']);
    $context['scope'] = br_query('scope') === 'mine' || $actor['role'] === 'resident' ? 'mine' : 'work';
    if ($page==='overview') $context['cases']=br_store()->pagedConcerns($actor['id'],['scope'=>$context['scope']],1,20)['items'];
    if (in_array($page, ['overview', 'complaints', 'history'], true)) {
        $context['cases'] = array_values(array_filter($context['cases'], fn($c) => $context['scope'] === 'mine'
            ? $c['isOwn'] : ($actor['role'] === 'official' || $c['canWork'])));
        if ($context['scope'] === 'mine' && $page === 'complaints') $context['pageTitle'] = 'My reported concerns';
    }
    if (br_query('week') !== '' && $page!=='action-plans') {
        if ($actor['role'] !== 'official' || $page !== 'complaints') br_page_error($context, 403, 'Access denied', 'Weekly analytics are available to barangay officials.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', br_query('week'), new DateTimeZone('Asia/Manila'));
        if (!$date || $date->format('Y-m-d') !== br_query('week') || $date->format('N') !== '1') br_page_error($context, 422, 'Invalid week', 'Choose a week from the dashboard.');
        $context['weeklyPeriod'] = ConcernInsights::week($date);
        $context['cases'] = array_values(array_filter($context['cases'], fn($c) => strtotime($c['createdAt']) >= strtotime($context['weeklyPeriod']['start']) && strtotime($c['createdAt']) < strtotime($context['weeklyPeriod']['end']) && ($c['concernType'] ?? '') === br_query('type')));
    }
    $context['metrics'] = br_metrics($context['cases']);
    if ($lightweight) $context['metrics'] = br_store()->metrics($actor['id'],$context['scope']==='mine');
    $context['messageCounts'] = br_store()->messageCounts($actor['id']);
    return $context;
}

function br_page_error(array $context, int $status, string $title, string $message): never
{
    http_response_code($status);
    extract($context);
    $pageTitle = $title;
    $cases = $cases ?? [];
    $metrics = $metrics ?? br_metrics([]);
    require __DIR__ . '/layout/header.php';
    br_heading($title, $message, '<a class="btn btn-primary" href="index.php">Return to dashboard</a>');
    require __DIR__ . '/layout/footer.php';
    exit;
}

function br_find_case(array $context, string $id): array
{
    $case = br_store()->concernForActor($context['actor']['id'], $id);
    if ($case !== null) return $case;
    br_page_error($context, 404, 'Concern unavailable', 'This concern was not found or is not available to your account.');
}
