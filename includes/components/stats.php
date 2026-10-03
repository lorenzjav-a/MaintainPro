<div class="stats-grid">
<?php
$cards = match ($actor['role']) {
    'resident' => [
        ['My reports', 'total', 'Concerns submitted from your account', 'inbox', '', 'all'],
        ['In progress', 'progress', 'Work being carried out', 'tool', 'blue', 'progress'],
        ['For official review', 'resolved', 'Completed work awaiting review', 'clock', '', 'resolved'],
        ['Closed concerns', 'verified', 'Reviewed by the barangay', 'checkCircle', 'green', 'verified'],
    ],
    'personnel' => [
        ['New assignments', 'assigned', 'Accept work assigned to you', 'clipboard', 'amber', 'assigned'],
        ['In progress', 'progress', 'Continue work and add updates', 'tool', 'blue', 'progress'],
        ['Blocked', 'blocked', 'Work waiting for assistance', 'flag', 'amber', 'blocked'],
        ['Due soon', 'due_soon', 'Due within the next three days', 'clock', '', 'due_soon'],
        ['Awaiting official review', 'resolved', 'Work completed; official review pending', 'checkCircle', '', 'resolved'],
    ],
    default => [
        ['New concerns', 'submitted', 'Recently submitted concerns', 'inbox', '', 'submitted'],
        ['Under review', 'assessment', 'Assess, recommend, and assign', 'clipboard', 'amber', 'assessment'],
        ['Assigned', 'assigned', 'Personnel have been selected', 'users', '', 'assigned'],
        ['In progress', 'progress', 'Personnel are recording work', 'tool', 'blue', 'progress'],
        ['Overdue', 'overdue', 'Past the target completion time', 'flag', 'amber', 'overdue'],
        ['Ready for review', 'resolved', 'Review evidence and close', 'checkCircle', 'green', 'resolved'],
    ],
};
foreach ($cards as [$label, $key, $caption, $icon, $color, $tab]) br_stat($label, $metrics[$key], $caption, $icon, $color, $tab);
?>
</div>
