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
        'overview' => match ($actor['role']) {'official' => 'Administrative dashboard', 'resident' => 'Resident dashboard', default => 'Personnel dashboard'},
        'complaints' => match ($actor['role']) {'official' => 'All complaints', 'resident' => 'My complaints', default => 'Work queue'},
        'history' => 'Resolution history', 'reports' => 'Reports & insights', 'solutions' => 'Solution library',
        'users' => 'User management', 'profile' => 'My profile', 'complaint' => 'Complaint details',
        'new-complaint' => 'Report a community concern', 'user-create' => 'Create a workspace account', 'user-edit' => 'Manage account',
    ];
    $context = ['actor' => $actor, 'page' => $page, 'pageTitle' => $titles[$page], 'titles' => $titles];
    if ($roles && !in_array($actor['role'], $roles, true)) br_page_error($context, 403, 'Access denied', 'Your account does not have access to this page.');
    // Use the same visibility rules as the API, including resident ownership and team assignment.
    $context['cases'] = ComplaintWorkflow::visible(br_state(), $actor);
    $context['metrics'] = br_metrics($context['cases']);
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
    foreach ($context['cases'] as $case) if ($case['id'] === $id) return $case;
    br_page_error($context, 404, 'Complaint unavailable', 'This complaint was not found or is not available to your account.');
}
