<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/concern-catalog.php';
require dirname(__DIR__) . '/includes/insights.php';

$checks = 0;
function solutionCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}

$keypointTotal = 0;
foreach (ConcernCatalog::POINTS as $category => $points) {
    solutionCheck(isset(ConcernCatalog::OFFICIAL_SOLUTIONS[$category]), $category . ' has an official solution map');
    solutionCheck(array_keys(ConcernCatalog::OFFICIAL_SOLUTIONS[$category]) === $points, $category . ' map matches the existing keypoint order');
    foreach ($points as $point) {
        $actions = ConcernCatalog::OFFICIAL_SOLUTIONS[$category][$point] ?? [];
        solutionCheck(count($actions) === 3, $category . ' / ' . $point . ' has exactly three solutions');
        solutionCheck(count(array_unique(array_map('mb_strtolower', $actions))) === 3, $category . ' / ' . $point . ' solutions are distinct');
        solutionCheck(count(array_filter($actions, fn($action) => is_string($action) && trim($action) !== '')) === 3, $category . ' / ' . $point . ' solutions are usable text');
        $keypointTotal++;
    }
}
solutionCheck($keypointTotal === 54, 'all 54 existing category/keypoint pairs are covered');
solutionCheck(count(ConcernCatalog::OFFICIAL_SOLUTIONS) === count(ConcernCatalog::POINTS), 'no duplicate category catalog introduced');

foreach (['Bad odor', 'Near school', 'Blocking access', 'Recurring issue'] as $sharedPoint) {
    $contextual = [];
    foreach (ConcernCatalog::OFFICIAL_SOLUTIONS as $category => $points) if (isset($points[$sharedPoint])) $contextual[] = json_encode($points[$sharedPoint]);
    solutionCheck(count($contextual) === count(array_unique($contextual)), $sharedPoint . ' changes with its parent category');
}

$base = [
    'id' => 'CON-TEST', 'category' => 'Street Lighting', 'concernType' => 'Light not working',
    'keyPoints' => ['Completely dark', 'Near school'], 'priority' => 'Low',
    'locationDetails' => ['purok' => 'Purok 1'],
];
$weeklyCases = [];
for ($i = 0; $i < 5; $i++) $weeklyCases[] = array_replace($base, ['id' => 'DARK-' . $i]);
$weeklyCases[] = array_replace($base, ['id' => 'WIRE', 'keyPoints' => ['Exposed wires']]);
$before = serialize($weeklyCases);
$weekly = ConcernInsights::weekly($weeklyCases, [
    ['concern_type' => 'Light not working', 'area' => 'Purok 1'],
]);
$lighting = $weekly[0];
solutionCheck(serialize($weeklyCases) === $before, 'weekly suggestions do not alter concern records');
solutionCheck(count($lighting['actions']) === 3 && count(array_unique($lighting['actions'])) === 3, 'multiple keypoints produce exactly three distinct solutions');
solutionCheck($lighting['keyPoints'][0] === 'Exposed wires', 'dangerous keypoint outranks more frequent lower-risk keypoints');
solutionCheck(str_contains(implode(' ', $lighting['actions']), 'qualified electrical personnel'), 'electrical danger requires qualified personnel');
solutionCheck(str_contains(implode(' ', $lighting['actions']), 'preventive') || str_contains(implode(' ', $lighting['actions']), 'Monitor'), 'recurrence history contributes prevention');

$waste = ConcernCatalog::officialSuggestions('Waste Management', 'Uncollected garbage', ['Bad odor' => 3, 'Recurring issue' => 2]);
solutionCheck(count($waste['actions']) === 3 && count(array_unique($waste['actions'])) === 3, 'weekly selector deduplicates to three actions');
solutionCheck(str_contains($waste['actions'][2], 'preventive'), 'recurring keypoint places prevention after immediate actions');
$crossing = ConcernCatalog::officialSuggestions('Street Lighting', 'Light not working', ['Near pedestrian crossing' => 2], true);
solutionCheck(str_contains($crossing['actions'][1], 'qualified personnel') && str_contains($crossing['actions'][2], 'Monitor'), 'recurrence history keeps correction before prevention');
$other = ConcernCatalog::officialSuggestions('Other', 'Other community concern', []);
solutionCheck(count($other['actions']) === 3 && str_contains($other['actions'][0], 'other community concern'), 'Other fallback uses the specific concern when keypoints are absent');
solutionCheck(ConcernInsights::priority(['category' => 'Street Lighting', 'concernType' => 'Light not working', 'keyPoints' => ['Exposed wires']])['priority'] === 'High', 'existing automatic priority recommendation remains active');

echo "PASS: $checks category-aware keypoint solution and weekly selection checks across $keypointTotal keypoints.\n";
