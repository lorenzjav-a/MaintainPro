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
        ['New assignments', 'assigned', 'Accept work assigned to you', 'clipboard', 'amber', 'assigned'],
        ['In progress', 'progress', 'Continue work and add updates', 'tool', 'blue', 'progress'],
        ['Awaiting official review', 'resolved', 'Work completed; official review pending', 'checkCircle', '', 'resolved'],
        ['Closed work', 'verified', 'Reviewed by a barangay official', 'checkCircle', 'green', 'verified'],
    ],
    default => [
        ['Total Concerns', 'total', 'All saved community reports', 'inbox', '', 'all'],
        ['New reports', 'submitted', 'Newly submitted resident concerns', 'inbox', '', 'submitted'],
        ['Needs assessment', 'assessment', 'Review, recommend, and assign', 'clipboard', 'amber', 'assessment'],
        ['Assigned', 'assigned', 'Personnel have been selected', 'users', '', 'assigned'],
        ['Work in progress', 'progress', 'Active work and evidence', 'tool', 'blue', 'progress'],
        ['Resolved', 'resolved', 'Ready for official review', 'checkCircle', 'green', 'resolved'],
        ['Urgent concerns', 'urgent', 'Open reports marked urgent', 'flag', 'blue', 'urgent'],
        ['Reopened concerns', 'reopened_now', 'Review the reason for reopening', 'refresh', 'green', 'reopened_now'],
    ],
};
foreach ($cards as [$label, $key, $caption, $icon, $color, $tab]) br_stat($label, $metrics[$key], $caption, $icon, $color, $tab);
?>
</div>
