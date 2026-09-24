<?php
declare(strict_types=1);

final class ConcernInsights
{
    public static function config(): array
    {
        static $config;
        return $config ??= require dirname(__DIR__) . '/config/features.php';
    }

    public static function recurrenceLevel(int $count): string
    {
        $level = 'Normal';
        foreach (self::config()['recurrence_thresholds'] as $name => $minimum) if ($count >= $minimum) $level = $name;
        return $level;
    }

    public static function workloadLabel(int $count): string
    {
        return $count >= 6 ? 'High Workload' : ($count >= 3 ? 'Moderate' : 'Available');
    }

    public static function relevantTeam(array $c): string
    {
        return match ($c['category']) {
            'Roads and Infrastructure', 'Street Lighting', 'Water', 'Public Facilities' => 'Maintenance crew',
            'Drainage/Flooding' => 'Barangay response team',
            'Waste Management', 'Sanitation' => 'Sanitation team',
            'Safety' => 'Peace and order committee',
            'Environmental Concern' => 'Environment committee',
            default => $c['team'] ?? '',
        };
    }

    public static function recommendPersonnel(array $workloads, array $c): ?array
    {
        $available = array_values(array_filter($workloads, fn($u) => (bool)$u['active']));
        $team = self::relevantTeam($c);
        usort($available, fn($a, $b) => [($a['team'] === $team ? 0 : 1), (int)$a['active_work'], $a['name'], $a['id']]
            <=> [($b['team'] === $team ? 0 : 1), (int)$b['active_work'], $b['name'], $b['id']]);
        return $available[0] ?? null;
    }

    // Scores reflect the real catalog, not free-text descriptions. Officials decide.
    public static function priority(array $c): array
    {
        $type = $c['concernType'] ?? '';
        $points = $c['keyPoints'] ?? [];
        $base = match ($type) {
            'Exposed wiring', 'Damaged pole' => [5, 'Electrical safety risk'],
            'Unsafe structure', 'Traffic hazard', 'Damaged drain cover', 'Fallen tree' => [3, 'Potential injury or structural hazard'],
            'Pothole', 'Damaged road', 'Flooding', 'Blocked drainage', 'No water supply', 'Water leak', 'Discolored water', 'Light not working', 'Blocked access' => [2, 'Service disruption or access affected'],
            default => match ($c['category'] ?? '') {
                'Safety', 'Drainage/Flooding', 'Water' => [2, 'Public safety or essential service concern'],
                default => [1, 'Reported maintenance concern'],
            },
        };
        $score = $base[0];
        $reasons = [$base[1] . ' (+' . $base[0] . ')'];
        $rules = [
            'Immediate danger' => [6, 'Immediate danger reported'],
            'Sparks visible' => [4, 'Visible electrical sparks'],
            'Exposed wires' => [4, 'Exposed electrical wiring'],
            'Near power lines' => [4, 'Work close to power lines'],
            'Dangerous to motorcycles' => [3, 'Risk to motorcycle riders'],
            'Sharp edges' => [3, 'Sharp edges may cause injury'],
            'Blocking access' => [3, 'Access blocked'],
            'Near school' => [2, 'Children and school traffic nearby'],
            'Near pedestrian crossing' => [2, 'Pedestrian exposure'],
            'High traffic area' => [2, 'High traffic exposure'],
            'Near intersection' => [1, 'Intersection affected'],
            'Causing traffic' => [1, 'Traffic disrupted'],
            'Deep' => [2, 'Deep road damage'], 'Wide' => [1, 'Wide road damage'],
            'Flooded when raining' => [2, 'Flooding during rain'],
            'Overflowing' => [2, 'Overflow reported'], 'Completely dark' => [2, 'Visibility reduced'],
            'Affecting several homes' => [2, 'Several households affected'],
            'Continuous leak' => [1, 'Ongoing water loss'], 'Unusable' => [2, 'Facility cannot be used'],
            'Attracting pests' => [1, 'Pest exposure'], 'Bad odor' => [1, 'Sanitation impact'],
            'Recurring issue' => [1, 'Reporter indicates repeat occurrence'],
        ];
        foreach (array_unique($points) as $point) if (isset($rules[$point])) {
            [$weight, $reason] = $rules[$point];
            $score += $weight;
            $reasons[] = $reason . ' (+' . $weight . ')';
        }
        $priority = $score >= 8 ? 'Urgent' : ($score >= 5 ? 'High' : ($score >= 2 ? 'Medium' : 'Low'));
        if (in_array('Immediate danger', $points, true) || in_array('Sparks visible', $points, true)) $priority = 'Urgent';
        return ['priority' => $priority, 'score' => $score, 'reasons' => $reasons, 'ruleVersion' => 1];
    }

    public static function evidenceStage(array $event): string
    {
        if (isset($event['evidenceType'])) return $event['evidenceType'];
        return match ($event['title']) {
            'Concern submitted', 'Complaint submitted' => 'Initial Evidence',
            'Resolution recorded' => 'Completion Evidence',
            'Additional information submitted' => 'Inspection Evidence',
            default => in_array($event['workStatus'] ?? '', ['Arrived at location', 'Inspection completed'], true) ? 'Inspection Evidence' : 'Progress Evidence',
        };
    }

    public static function evidence(array $c): array
    {
        $entries = [];
        foreach ($c['timeline'] as $event) if (!empty($event['photo']) || !empty($event['evidenceId'])) {
            $event['evidenceType'] = self::evidenceStage($event);
            $entries[] = $event;
        }
        // Compatibility for older records whose photos predate timeline uploads.
        foreach (['Initial Evidence' => ['photo' => $c['photo'], 'date' => $c['createdAt'], 'actor' => $c['resident']],
                  'Completion Evidence' => ['photo' => $c['resolution']['photo'] ?? '', 'date' => $c['resolution']['date'] ?? '', 'actor' => $c['resolution']['team'] ?? '']] as $stage => $legacy) {
            if ($legacy['photo'] && !in_array($stage, array_column($entries, 'evidenceType'), true)) $entries[] = $legacy + ['evidenceType' => $stage, 'note' => 'Earlier evidence'];
        }
        usort($entries, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
        return $entries;
    }
}
