<?php
declare(strict_types=1);

// Local, free rules. Changing these values does not require a schema migration.
return [
    'recurrence_days' => 90,
    'recurrence_thresholds' => ['Repeated' => 2, 'Recurring' => 3, 'High Recurrence' => 5],
    'due_soon_hours' => 24,
];
