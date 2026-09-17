<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function br_query(string $name, string $default = ''): string
{
    return is_string($_GET[$name] ?? null) ? trim($_GET[$name]) : $default;
}

function br_url(string $page, array $query = []): string
{
    return $page . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function br_role(string $role): string
{
    return ['official' => 'Barangay official', 'resident' => 'Resident', 'personnel' => 'Barangay personnel'][$role] ?? $role;
}

function br_date(string $value, bool $full = false): string
{
    return date($full ? 'M j, Y · g:i A' : 'M j, Y', strtotime($value));
}

function br_initials(string $name): string
{
    return mb_strtoupper(implode('', array_map(fn($part) => mb_substr($part, 0, 1), array_slice(preg_split('/\s+/u', trim($name)), 0, 2))));
}

function br_icon(string $name): string
{
    static $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'inbox' => '<path d="M4 4h16l2 12v4H2v-4L4 4Z"/><path d="M2 16h6l2 3h4l2-3h6"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'checkCircle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'arrow' => '<path d="M4 12h15m-5-5 5 5-5 5"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'pin' => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'chart' => '<path d="M4 3v17h17M9 15v-4M14 15V6M19 15V9"/>',
        'book' => '<path d="M12 5v16M3 3c4 0 7 0 9 2 2-2 5-2 9-2v16c-4 0-7 0-9 2-2-2-5-2-9-2V3Z"/>',
        'users' => '<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M16 4a3 3 0 0 1 0 6M19 14a5 5 0 0 1 3 5v2"/>',
        'shield' => '<path d="m12 2 8 3v7c0 5-8 10-8 10S4 17 4 12V5l8-3Z"/><path d="m8 11 3 3 5-6"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 8a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16v.2"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'building' => '<path d="m3 9 9-6 9 6H3Zm2 1v10m7-10v10m7-10v10M2 21h20"/>',
        'flag' => '<path d="M5 22V3m0 1c5-5 9 5 15 0v10c-6 5-10-5-15 0"/>',
        'tool' => '<path d="M14 6a5 5 0 0 0-6 6l-5 5a2 2 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-3 3-4-4 3-3Z"/>',
        'refresh' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 7a8 8 0 0 1 13 0L20 12M4 12l1.5 5a8 8 0 0 0 13 0"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['inbox']) . '</svg>';
}

function br_status(array $case): string
{
    return '<span class="status status-' . h(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $case['status']))) . '">' . h($case['status']) . '</span>';
}

function br_priority(array $case): string
{
    return '<span class="priority priority-' . h(strtolower($case['priority'])) . '"><span class="priority-dot"></span>' . h($case['priority']) . '</span>';
}

function br_options(array $values, string $selected = '', string $placeholder = ''): void
{
    if ($placeholder !== '') echo '<option value="">', h($placeholder), '</option>';
    foreach ($values as $value) echo '<option value="', h($value), '"', $selected === $value ? ' selected' : '', '>', h($value), '</option>';
}

function br_active(array $case): bool
{
    return !in_array($case['status'], ['Verified', 'Rejected', 'Referred to Another Office'], true);
}

function br_review(array $case): bool
{
    return in_array($case['status'], ['Submitted', 'Under Review', 'Reopened'], true);
}

function br_metrics(array $cases): array
{
    $count = fn(callable $test) => count(array_filter($cases, $test));
    $completed = array_filter($cases, fn($c) => in_array($c['status'], ['Resolved', 'Verified'], true) && $c['resolution']);
    return [
        'total' => count($cases), 'assessment' => $count('br_review'), 'pending' => $count('br_active'),
        'progress' => $count(fn($c) => $c['status'] === 'In Progress'), 'resolved' => $count(fn($c) => $c['status'] === 'Resolved'),
        'verified' => $count(fn($c) => $c['status'] === 'Verified'), 'assigned' => $count(fn($c) => $c['status'] === 'Assigned'),
        'submitted' => $count(fn($c) => $c['status'] === 'Submitted'), 'reopened_now' => $count(fn($c) => $c['status'] === 'Reopened'),
        'urgent' => $count(fn($c) => $c['priority'] === 'Urgent' && br_active($c)), 'reopened' => $count(fn($c) => $c['reopenCount'] > 0),
        'average' => $completed ? number_format(array_sum(array_map(fn($c) => max(0, strtotime($c['resolution']['date']) - strtotime($c['createdAt'])) / 86400, $completed)) / count($completed), 1) : '—',
    ];
}

function br_group(array $cases, string|callable $key): array
{
    $groups = [];
    foreach ($cases as $case) {
        $label = is_callable($key) ? $key($case) : $case[$key];
        $groups[$label] = ($groups[$label] ?? 0) + 1;
    }
    $rows = [];
    foreach ($groups as $label => $count) $rows[] = ['label' => (string)$label, 'count' => $count];
    usort($rows, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']));
    return $rows;
}

function br_next_step(array $c, string $role): string
{
    $copy = [
        'resident' => ['Submitted' => 'Waiting for barangay review', 'Under Review' => 'Barangay is preparing the action', 'Assigned' => 'A response team is assigned', 'In Progress' => 'Work is underway', 'Resolved' => 'Review and verify the result', 'Verified' => 'Closed after your confirmation', 'Reopened' => 'Returned for another assessment', 'Returned for Information' => 'Add the requested information', 'Rejected' => 'Read the recorded reason', 'Referred to Another Office' => 'Follow the referral details'],
        'official' => ['Submitted' => 'Review and recommend an action', 'Under Review' => 'Assign the responsible team', 'Assigned' => 'Waiting for the team to accept', 'In Progress' => 'Monitor the team’s work', 'Resolved' => 'Waiting for resident verification', 'Verified' => 'Complete and recorded', 'Reopened' => 'Reassess and assign another action', 'Returned for Information' => 'Waiting for resident details', 'Rejected' => 'No further action', 'Referred to Another Office' => 'Monitor the referral separately'],
        'personnel' => ['Assigned' => 'Accept this assignment', 'In Progress' => 'Add an update or record resolution', 'Resolved' => 'Waiting for resident verification', 'Verified' => 'Complete and recorded', 'Reopened' => 'Waiting for reassignment'],
    ];
    return $copy[$role][$c['status']] ?? 'Open for details';
}

function br_heading(string $title, string $description, string $actions = ''): void
{ ?>
    <div class="page-heading"><div><h1><?= h($title) ?></h1><p><?= h($description) ?></p></div><div class="heading-actions"><?= $actions ?></div></div>
<?php }

function br_export(): string
{
    return '<a class="btn btn-light" href="api.php?export=csv" download>' . br_icon('download') . 'Export report</a>';
}

function br_primary(array $actor): string
{
    [$url, $icon, $label] = match ($actor['role']) {
        'resident' => ['new-complaint.php', 'plus', 'Report a concern'],
        'official' => ['complaints.php?tab=assessment', 'clipboard', 'Review complaints'],
        default => ['complaints.php?tab=work', 'clipboard', 'View assignments'],
    };
    return '<a class="btn btn-primary" href="' . h($url) . '">' . br_icon($icon) . $label . '</a>';
}

function br_stat(string $label, mixed $number, string $caption, string $icon, string $color, string $tab): void
{ ?>
    <a class="stat-card <?= h($color) ?>" href="<?= h(br_url('complaints.php', ['tab' => $tab])) ?>"><div class="stat-top"><span><?= h($label) ?></span><span class="stat-icon"><?= br_icon($icon) ?></span></div><div class="number"><?= h($number) ?></div><div class="stat-caption"><?= h($caption) ?></div></a>
<?php }

function br_chart(array $cases, bool $full = false): void
{
    $data = br_group($cases, 'category');
    $max = $data[0]['count'] ?? 1; ?>
    <div class="category-chart">
        <?php foreach ($full ? $data : array_slice($data, 0, 5) as $row): ?>
        <div class="chart-row"><div class="chart-label"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?></strong></div><div class="chart-track"><div class="chart-fill" style="width:<?= $row['count'] / $max * 100 ?>%"></div></div></div>
        <?php endforeach ?>
        <?php if (!$data): ?><p class="text-muted small">No category data yet.</p><?php endif ?>
    </div>
<?php }

function br_photo(string $photo, string $label): void
{ ?>
    <?php if ($photo !== ''): ?><img class="case-photo" src="<?= h($photo) ?>" alt="<?= h($label) ?>"><div class="photo-label"><?= h($label) ?></div>
    <?php else: ?><div class="photo-label">No <?= h(strtolower($label)) ?> attached.</div><?php endif ?>
<?php }

function br_upload(string $id, string $label): void
{ ?>
    <label class="form-label" for="<?= h($id) ?>"><?= h($label) ?> <span class="text-muted fw-normal">(optional)</span></label>
    <div class="upload-zone"><input class="form-control form-control-sm" type="file" id="<?= h($id) ?>" name="photoFile" accept="image/jpeg,image/png,image/webp" aria-describedby="<?= h($id) ?>-help"><p class="form-text" id="<?= h($id) ?>-help">JPG, PNG, or WebP · Up to 1 MB · Saved with the complaint record.</p><div data-preview="<?= h($id) ?>"></div></div>
<?php }
