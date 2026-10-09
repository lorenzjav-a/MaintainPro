<?php
declare(strict_types=1);

/** One filter contract for the report page, its SQL queries and PDF download. */
final class ConcernReportFilters
{
    public const PRESETS = ['' => 'All dates', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month', '30days' => 'Last 30 days', 'custom' => 'Custom range'];

    public static function validate(array $input, array $options, ?DateTimeImmutable $now = null): array
    {
        $filters = [];
        foreach (['period','start','end','category','type','status','priority','team','personnel','location','search','keypoint'] as $key) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || mb_strlen($value) > ($key === 'search' ? 120 : 180) || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new DomainException('Invalid '. $key .' filter.');
            $filters[$key] = trim($value);
        }
        if (!array_key_exists($filters['period'], self::PRESETS)) throw new DomainException('Choose a supported reporting period.');
        $tz = new DateTimeZone('Asia/Manila');
        $today = ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz)->setTime(0, 0);
        foreach (['start','end'] as $key) {
            $value = $filters[$key];
            $date = $value !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz) : false;
            if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '9998-12-31')) throw new DomainException('Use a valid '. $key .' date (YYYY-MM-DD).');
        }
        if ($filters['period'] !== '' && $filters['period'] !== 'custom') {
            $start = match ($filters['period']) {
                'week' => $today->modify('-'.((int)$today->format('N')-1).' days'),
                'month' => $today->modify('first day of this month'),
                '30days' => $today->modify('-29 days'), default => $today,
            };
            $end = match ($filters['period']) {'week' => $start->modify('+6 days'), 'month' => $today->modify('last day of this month'), default => $today};
            $filters['start'] = $start->format('Y-m-d');
            $filters['end'] = $end->format('Y-m-d');
        } elseif ($filters['start'] !== '' || $filters['end'] !== '') $filters['period'] = 'custom';
        if ($filters['start'] !== '' && $filters['end'] !== '' && $filters['start'] > $filters['end']) throw new DomainException('The start date must be on or before the end date.');
        foreach (['category'=>'categories','status'=>'statuses','priority'=>'priorities','team'=>'teams','keypoint'=>'keypoints'] as $field => $list) {
            if ($field === 'team' && $filters[$field] === 'unassigned') continue;
            if ($filters[$field] !== '' && !in_array($filters[$field], $options[$list], true)) throw new DomainException('Choose an available '. $field .'.');
        }
        $types = $filters['category'] === '' ? array_merge(...array_values(ConcernCatalog::TYPES)) : (ConcernCatalog::TYPES[$filters['category']] ?? []);
        if ($filters['type'] !== '' && !in_array($filters['type'], $types, true)) throw new DomainException('Choose a concern type belonging to the selected category.');
        if ($filters['keypoint'] !== '' && $filters['category'] !== '' && !in_array($filters['keypoint'], ConcernCatalog::POINTS[$filters['category']] ?? [], true)) throw new DomainException('Choose a key point belonging to the selected category.');
        foreach (['personnel','location'] as $field) {
            $valid = array_map('strval', array_column($options[$field], 'id'));
            if ($filters[$field] !== '' && $filters[$field] !== 'unassigned' && !in_array($filters[$field], $valid, true)) throw new DomainException('Choose an available '. $field .'.');
        }
        if ($filters['location'] === 'unassigned') throw new DomainException('Choose an available location.');
        return $filters;
    }

    public static function query(array $filters): array
    {
        // Freeze a preset's resolved dates in the download URL, even across midnight.
        if ($filters['start'] !== '' || $filters['end'] !== '') $filters['period'] = 'custom';
        return array_filter($filters, static fn($value) => $value !== '');
    }

    public static function labels(array $filters, array $options): array
    {
        $labels = [];
        if ($filters['start'] !== '' || $filters['end'] !== '') $labels['Submission dates'] = ($filters['start'] ?: 'Beginning of records').' to '.($filters['end'] ?: 'Present').' (Asia/Manila; inclusive)';
        foreach (['category'=>'Category','type'=>'Concern type','status'=>'Status','priority'=>'Priority','team'=>'Team','keypoint'=>'Key point','search'=>'Search'] as $field => $label) {
            if ($filters[$field] !== '') $labels[$label] = $field === 'status' ? br_status_label($filters[$field]) : ($filters[$field] === 'unassigned' ? 'Unassigned' : $filters[$field]);
        }
        foreach (['personnel'=>'Assigned personnel','location'=>'Location'] as $field => $label) {
            if ($filters[$field] === '') continue;
            if ($filters[$field] === 'unassigned') { $labels[$label] = 'Unassigned'; continue; }
            foreach ($options[$field] as $row) if ((string)$row['id'] === $filters[$field]) $labels[$label] = $row['name'];
        }
        return $labels ?: ['Scope' => 'All Authorized Concerns'];
    }
}

/** Fail before allocating an unsafe report; capacity follows the hosting budget. */
final class ConcernReportCapacity
{
    public static function check(int $records, int $htmlBytes = 0): void
    {
        $limit = trim((string)ini_get('memory_limit'));
        $bytes = (int)$limit;
        if ($bytes > 0) $bytes *= match(strtolower(substr($limit,-1))) {'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        // An unlimited PHP setting is not permission to exhaust a shared host.
        if ($bytes <= 0) $bytes = 512*1048576;
        $estimate = 24*1048576 + $records*180000 + $htmlBytes*35;
        if ($estimate > ($bytes-memory_get_usage(true))*.7) throw new DomainException('This PDF is too large for the available server memory. Narrow the date range or filters and export a complete smaller report.');
    }
}

/** Incremental insights keep full concern payloads/evidence out of report memory. */
final class ConcernReportSummary
{
    private array $groups = [];
    private array $counts = ['total'=>0,'assessment'=>0,'pending'=>0,'progress'=>0,'resolved'=>0,'verified'=>0,'assigned'=>0,'submitted'=>0,'reopened_now'=>0,'urgent'=>0,'reopened'=>0,'highUrgent'=>0];
    private float $resolutionDays = 0;
    private int $completed = 0;
    private array $decisions = [];
    private int $decisionCount = 0;
    private int $overrides = 0;

    public function add(array $c): void
    {
        $this->counts['total']++;
        $this->counts['pending'] += (int)br_active($c);
        $this->counts['assessment'] += (int)br_review($c);
        foreach (['progress'=>'In Progress','resolved'=>'Resolved','verified'=>'Verified','assigned'=>'Assigned','submitted'=>'Submitted','reopened_now'=>'Reopened'] as $key=>$status) $this->counts[$key] += (int)($c['status'] === $status);
        $this->counts['reopened'] += (int)($c['reopenCount'] > 0);
        $this->counts['urgent'] += (int)($c['priority'] === 'Urgent' && br_active($c));
        $this->counts['highUrgent'] += (int)in_array($c['priority'], ['High','Urgent'], true);
        if (in_array($c['status'], ['Resolved','Verified'], true) && !empty($c['resolution']['date'])) {
            $this->completed++;
            $this->resolutionDays += max(0, strtotime($c['resolution']['date']) - strtotime($c['createdAt'])) / 86400;
        }
        foreach (['category','status','priority'] as $field) $this->group($field, $c[$field]);
        $this->group('type', $c['concernType'] ?: 'Legacy / unspecified');
        $this->group('location', $c['locationDetails']['purok'] ?: 'Legacy / unspecified');
        $this->group('month', (new DateTimeImmutable($c['createdAt']))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m'));
        foreach ($c['keyPoints'] as $point) $this->group('keypoint', $point);
        if (in_array($c['status'], ['Assigned','In Progress'], true)) $this->group('workload', $c['assignedName'] ?: 'Unassigned / legacy team assignment');
        if (!empty($c['priorityDecision']) && in_array($c['priorityDecision']['recommended'] ?? '', ComplaintWorkflow::PRIORITIES, true) && in_array($c['priorityDecision']['priority'] ?? '', ComplaintWorkflow::PRIORITIES, true)) {
            $decision = $c['priorityDecision'];
            $this->decisionCount++;
            $this->overrides += (int)($decision['overridden'] ?? false);
            $this->decisions[$decision['recommended']][$decision['priority']] = ($this->decisions[$decision['recommended']][$decision['priority']] ?? 0) + 1;
        }
    }

    private function group(string $group, string $label): void { $this->groups[$group][$label] = ($this->groups[$group][$label] ?? 0) + 1; }

    public function result(): array
    {
        $groups = [];
        foreach ($this->groups as $key => $counts) {
            $rows = [];
            foreach ($counts as $label => $count) $rows[] = ['label'=>(string)$label, 'count'=>$count];
            usort($rows, static fn($a,$b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'],$b['label']));
            if ($key === 'month') usort($rows, static fn($a,$b) => strcmp($a['label'],$b['label']));
            $groups[$key] = $rows;
        }
        foreach (['category','status','priority','type','location','month','keypoint','workload'] as $key) $groups[$key] ??= [];
        return ['metrics'=>$this->counts + ['average'=>$this->completed ? number_format($this->resolutionDays / $this->completed, 1) : '—'], 'groups'=>$groups, 'decisions'=>$this->decisions, 'decisionCount'=>$this->decisionCount, 'overrides'=>$this->overrides];
    }
}
