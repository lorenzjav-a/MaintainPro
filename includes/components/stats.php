<div class="stats-grid">
<?php
$cards = match ($actor['role']) {
    'resident' => [
        ['My active reports', 'pending', 'Your concerns still being handled', 'inbox', '', 'pending'],
        ['Awaiting my verification', 'resolved', 'Review the completed work', 'checkCircle', 'amber', 'resolved'],
        ['Verified reports', 'verified', 'Resolutions you have confirmed', 'checkCircle', 'green', 'verified'],
        ['All my reports', 'total', 'Your complete reporting history', 'clock', 'blue', 'all'],
    ],
    'personnel' => [
        ['New assignments', 'assigned', 'Accept work assigned to your team', 'clipboard', 'amber', 'assigned'],
        ['In progress', 'progress', 'Continue work and add updates', 'tool', 'blue', 'progress'],
        ['Awaiting verification', 'resolved', 'Work completed; resident review pending', 'checkCircle', '', 'resolved'],
        ['Verified work', 'verified', 'Confirmed by the reporting resident', 'checkCircle', 'green', 'verified'],
    ],
    default => [
        ['New reports', 'submitted', 'Newly submitted resident concerns', 'inbox', '', 'submitted'],
        ['Needs assessment', 'assessment', 'Review, recommend, and assign', 'clipboard', 'amber', 'assessment'],
        ['Urgent concerns', 'urgent', 'Open reports marked urgent', 'flag', 'blue', 'urgent'],
        ['Reopened concerns', 'reopened_now', 'Review the resident feedback', 'refresh', 'green', 'reopened_now'],
    ],
};
foreach ($cards as [$label, $key, $caption, $icon, $color, $tab]) br_stat($label, $metrics[$key], $caption, $icon, $color, $tab);
?>
</div>
