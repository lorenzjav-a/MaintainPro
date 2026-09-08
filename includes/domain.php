<?php
declare(strict_types=1);

final class ComplaintDemo
{
    public const CATEGORIES = ['Road damage and potholes', 'Public infrastructure', 'Streetlights', 'Drainage and flooding', 'Garbage collection', 'Improper waste disposal', 'Water concerns', 'Noise complaints', 'Stray animals', 'Illegal parking', 'Road obstruction', 'Traffic concerns', 'Public facilities', 'Trees and vegetation', 'Electrical hazards', 'Unsafe structures', 'Sanitation and cleanliness', 'Neighbor concerns', 'Community disputes', 'Business concerns', 'Other concerns'];
    public const TEAMS = ['Maintenance crew', 'Sanitation team', 'Barangay response team', 'Peace and order committee', 'Environment committee'];
    public const STATUSES = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Verified', 'Returned for Information', 'Reopened', 'Rejected', 'Referred to Another Office'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

    public static function actor(string $role, string $team = 'Sanitation team'): array
    {
        return match ($role) {
            'resident' => ['role' => 'resident', 'id' => 'resident-1', 'name' => 'Alex Santos', 'team' => ''],
            'personnel' => ['role' => 'personnel', 'id' => 'personnel-1', 'name' => $team, 'team' => self::choice($team, self::TEAMS, 'team')],
            'official' => ['role' => 'official', 'id' => 'official-1', 'name' => 'Maria Dela Cruz', 'team' => ''],
            default => throw new DomainException('Choose a valid demo role.'),
        };
    }

    public static function canSee(array $c, array $actor): bool
    {
        return $actor['role'] === 'official'
            || ($actor['role'] === 'resident' && $actor['id'] === $c['residentId'])
            || ($actor['role'] === 'personnel' && $actor['team'] === $c['team']);
    }

    public static function visible(array $state, array $actor): array
    {
        return array_values(array_filter($state['cases'], fn($c) => self::canSee($c, $actor)));
    }

    private static function text(mixed $value, string $label, int $limit = 4000, bool $required = true): string
    {
        if (!is_string($value)) {
            throw new DomainException($label . ' must be text.');
        }
        $value = trim($value);
        if ($required && $value === '') {
            throw new DomainException($label . ' is required.');
        }
        if (mb_strlen($value) > $limit) {
            throw new DomainException($label . ' is too long (maximum ' . $limit . ' characters).');
        }
        return $value;
    }

    private static function choice(mixed $value, array $options, string $label): string
    {
        if (!is_string($value) || !in_array($value, $options, true)) {
            throw new DomainException('Choose a valid ' . $label . '.');
        }
        return $value;
    }

    private static function photo(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }
        if (!is_string($value) || strlen($value) > 1400000 || !preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$~D', $value, $match)) {
            throw new DomainException('Use a JPG, PNG, or WebP image smaller than 1 MB.');
        }
        $bytes = base64_decode($match[2], true);
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if (!$info || $info['mime'] !== 'image/' . $match[1] || $info[0] * $info[1] > 20000000) {
            throw new DomainException('The attached image is invalid or exceeds 20 megapixels.');
        }
        return $value;
    }

    private static function event(array &$c, array $actor, string $title, string $note = '', ?string $date = null, string $photo = ''): void
    {
        $date ??= date(DATE_ATOM);
        $c['timeline'][] = ['title' => $title, 'note' => $note, 'actor' => $actor['name'], 'date' => $date, 'photo' => $photo];
        $c['updatedAt'] = $date;
    }

    public static function submit(array &$state, array $actor, array $data): string
    {
        if ($actor['role'] !== 'resident') {
            throw new DomainException('Only residents can submit complaints.');
        }
        $c = [
            'id' => 'BR-' . $state['nextId'],
            'title' => self::text($data['title'] ?? '', 'Complaint title', 140),
            'category' => self::choice($data['category'] ?? '', self::CATEGORIES, 'category'),
            'description' => self::text($data['description'] ?? '', 'Description'),
            'location' => self::text($data['location'] ?? '', 'Location', 250),
            'suggestion' => self::text($data['suggestion'] ?? '', 'Suggested solution', 2000, false),
            'photo' => self::photo($data['photo'] ?? ''),
            'status' => 'Submitted', 'priority' => 'Medium', 'team' => '',
            'residentId' => $actor['id'], 'resident' => $actor['name'],
            'recommendation' => '', 'assessment' => '', 'resolution' => null, 'feedback' => '',
            'reopenCount' => 0, 'createdAt' => date(DATE_ATOM), 'updatedAt' => date(DATE_ATOM), 'timeline' => [],
        ];
        self::event($c, $actor, 'Complaint submitted', $c['description']);
        $state['nextId']++;
        array_unshift($state['cases'], $c);
        return $c['id'];
    }

    public static function apply(array &$state, array $actor, string $id, string $action, array $data): void
    {
        $index = array_search($id, array_column($state['cases'], 'id'), true);
        if ($index === false || !self::canSee($state['cases'][$index], $actor)) {
            throw new DomainException('This complaint is not available to your account.');
        }
        // Work on a copy: failed validation cannot partially mutate a record.
        $c = $state['cases'][$index];
        $assessment = in_array($c['status'], ['Submitted', 'Under Review', 'Reopened'], true);
        $official = $actor['role'] === 'official';
        $personnel = $actor['role'] === 'personnel' && $actor['team'] === $c['team'];
        $resident = $actor['role'] === 'resident' && $actor['id'] === $c['residentId'];
        switch ($action) {
            case 'assess':
                self::guard($official && $assessment, 'This complaint cannot be assessed at this stage.');
                $c['recommendation'] = self::text($data['recommendation'] ?? '', 'Barangay recommended action');
                $c['category'] = self::choice($data['category'] ?? '', self::CATEGORIES, 'category');
                $c['priority'] = self::choice($data['priority'] ?? '', self::PRIORITIES, 'priority');
                $c['assessment'] = self::text($data['assessment'] ?? '', 'Assessment notes', 3000, false);
                $c['status'] = 'Under Review';
                self::event($c, $actor, 'Assessment recorded', $c['recommendation'] . ($c['assessment'] ? "\nAssessment notes: " . $c['assessment'] : ''));
                break;
            case 'assign':
                self::guard($official && $c['status'] === 'Under Review' && $c['recommendation'] !== '', 'Save the official assessment and recommended action before assigning.');
                $c['team'] = self::choice($data['team'] ?? '', self::TEAMS, 'team');
                $c['status'] = 'Assigned';
                self::event($c, $actor, 'Assigned to ' . $c['team'], $c['recommendation']);
                break;
            case 'start':
                self::guard($personnel && $c['status'] === 'Assigned', 'Only the assigned team can start this work.');
                $c['status'] = 'In Progress';
                self::event($c, $actor, 'Work started', 'The team accepted the assignment and began work.');
                break;
            case 'note':
                self::guard($personnel && $c['status'] === 'In Progress', 'Progress notes are available for your work in progress.');
                self::event($c, $actor, 'Progress update', self::text($data['notes'] ?? '', 'Progress note'));
                break;
            case 'resolve':
                self::guard($personnel && $c['status'] === 'In Progress', 'Only the assigned team can record a resolution after starting work.');
                $c['resolution'] = ['notes' => self::text($data['notes'] ?? '', 'Work performed'), 'photo' => self::photo($data['photo'] ?? ''), 'date' => date(DATE_ATOM), 'team' => $c['team']];
                $c['status'] = 'Resolved';
                self::event($c, $actor, 'Resolution recorded', $c['resolution']['notes'], null, $c['resolution']['photo']);
                break;
            case 'verify':
            case 'reopen':
                self::guard($resident && $c['status'] === 'Resolved', 'Only the reporting resident can verify a resolved complaint.');
                $c['feedback'] = self::text($data['feedback'] ?? '', 'Feedback', 3000, $action === 'reopen');
                $c['status'] = $action === 'verify' ? 'Verified' : 'Reopened';
                if ($action === 'reopen') {
                    $c['reopenCount']++;
                }
                self::event($c, $actor, $action === 'verify' ? 'Resident verified resolution' : 'Complaint reopened', $c['feedback'] ?: 'The resident confirmed the concern was resolved.');
                break;
            case 'information':
                self::guard($resident && $c['status'] === 'Returned for Information', 'Additional information is not currently requested.');
                $note = self::text($data['notes'] ?? '', 'Additional information', 2000);
                $photo = self::photo($data['photo'] ?? '');
                if ($photo !== '') {
                    $c['photo'] = $photo;
                }
                $c['description'] .= "\n\nAdditional information: " . $note;
                $c['status'] = 'Submitted';
                self::event($c, $actor, 'Additional information submitted', $note);
                break;
            case 'exception':
                self::guard($official && $assessment, 'This action is only available during assessment.');
                $c['status'] = self::choice($data['status'] ?? '', ['Returned for Information', 'Rejected', 'Referred to Another Office'], 'action');
                $note = self::text($data['notes'] ?? '', 'Reason', 3000);
                $office = $c['status'] === 'Referred to Another Office' ? self::text($data['office'] ?? '', 'Receiving office', 200) : '';
                if ($office) {
                    $c['referral'] = $office;
                }
                self::event($c, $actor, $c['status'], ($office ? 'Receiving office: ' . $office . "\n" : '') . $note);
                break;
            default:
                throw new DomainException('Unknown action.');
        }
        $state['cases'][$index] = $c;
    }

    private static function guard(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new DomainException($message);
        }
    }

    public static function seed(): array
    {
        $rows = [
            ['Clogged drainage along Mabini Street', 'Drainage and flooding', 'Mabini Street, Purok 3', 'Submitted', 'High', '', 0, 'resident-1', 'Alex Santos', 'Water builds up after rainfall near the corner store. Leaves and litter appear to be blocking the drain.', 'Please inspect and clear the drainage before the next rainfall.'],
            ['Streetlight out near the basketball court', 'Streetlights', 'Rizal Street, Purok 2', 'Under Review', 'High', '', -1, 'resident-1', 'Alex Santos', 'The streetlight beside the court has not turned on for three nights. The walkway is difficult to see.', 'Have the maintenance team check the light.'],
            ['Missed garbage collection in Purok 4', 'Garbage collection', 'Sampaguita Lane, Purok 4', 'Assigned', 'Medium', 'Sanitation team', -2, 'resident-1', 'Alex Santos', 'Bagged household waste remains at the collection point after the regular collection day.', 'Schedule a collection for the missed area.'],
            ['Exposed cable beside the public walkway', 'Electrical hazards', 'Municipal Road, Purok 1', 'Submitted', 'Urgent', '', 0, 'resident-2', 'Bea Ramos', 'A damaged cable hangs beside the public walkway. Please send qualified personnel to assess it.', 'Keep pedestrians away and contact the responsible utility.'],
            ['Waste buildup beside the creek', 'Improper waste disposal', 'Creekside, Purok 3', 'In Progress', 'High', 'Sanitation team', -3, 'resident-3', 'Carlo Mendoza', 'Waste has accumulated at the footbridge and is beginning to obstruct the creek.', 'Organize collection and monitor the area.'],
            ['Leaking faucet at the health center', 'Public facilities', 'Barangay Health Center, Purok 1', 'Resolved', 'Medium', 'Maintenance crew', -5, 'resident-1', 'Alex Santos', 'The outside faucet leaks continuously even when turned off.', 'Inspect and repair the faucet.'],
            ['Loud evening activity near residential homes', 'Noise complaints', 'Narra Street, Purok 5', 'Under Review', 'Medium', '', -2, 'resident-4', 'Dina Flores', 'Repeated loud activity late in the evening affects nearby homes.', 'Ask the barangay to speak with the people involved.'],
            ['Blocked footpath beside the market', 'Road obstruction', 'Public Market, Purok 2', 'Reopened', 'High', 'Barangay response team', -7, 'resident-1', 'Alex Santos', 'Items placed along the footpath make it difficult for pedestrians to pass.', 'Coordinate with stallholders to clear the footpath.'],
            ['Drainage clearing on Ilang-Ilang Street', 'Drainage and flooding', 'Ilang-Ilang Street, Purok 3', 'Verified', 'High', 'Sanitation team', -15, 'resident-5', 'Erika Cruz', 'Accumulated litter prevented rainwater from draining.', 'Clear the drainage and check the outlet.'],
            ['Streetlight repair on Acacia Street', 'Streetlights', 'Acacia Street, Purok 2', 'Verified', 'Medium', 'Maintenance crew', -12, 'resident-1', 'Alex Santos', 'The light beside the waiting shed stopped working.', 'Check the fixture and replace faulty parts.'],
            ['Collection point cleanup', 'Garbage collection', 'Sampaguita Lane, Purok 4', 'Verified', 'Medium', 'Sanitation team', -20, 'resident-3', 'Carlo Mendoza', 'Loose waste remained after collection.', 'Clean the area and coordinate a better collection time.'],
            ['Drain outlet clearing near the footbridge', 'Drainage and flooding', 'Creekside, Purok 3', 'Verified', 'High', 'Sanitation team', -25, 'resident-1', 'Alex Santos', 'Water was pooling near the footbridge after rain.', 'Inspect and clear the blocked drain outlet.'],
            ['Location needed for stray animal report', 'Stray animals', 'Purok 5 — exact location pending', 'Returned for Information', 'Medium', '', -1, 'resident-1', 'Alex Santos', 'Several unattended animals have been seen in the area.', 'Request assistance from the responsible team.'],
            ['Water supply concern on the service line', 'Water concerns', 'Rosal Street, Purok 4', 'Referred to Another Office', 'Medium', '', -4, 'resident-6', 'Felix Garcia', 'Several homes experienced interruptions in the water supply.', 'Coordinate with the water service provider.'],
        ];
        $recommendations = [
            'Drainage and flooding' => 'Inspect the affected drain and outlet, identify the blockage, schedule clearing with the sanitation team, and monitor water flow afterward.',
            'Streetlights' => 'Arrange inspection by qualified maintenance personnel and replace the defective fixture if necessary. Confirm operation after dark.',
            'Garbage collection' => 'Confirm the missed collection area, coordinate a pickup with the sanitation team, and inform residents of the collection schedule.',
            'Improper waste disposal' => 'Inspect the area, arrange safe waste collection, and coordinate follow-up monitoring with the environment committee.',
            'Public facilities' => 'Inspect the leaking fixture, repair or replace the damaged component, and check for leaks after repair.',
            'Road obstruction' => 'Inspect the footpath, coordinate removal of the obstruction with the responsible parties, and recheck pedestrian access.',
        ];
        $date = fn(int $offset): string => (new DateTimeImmutable('today 09:00'))->modify("$offset days")->format(DATE_ATOM);
        $cases = [];
        foreach ($rows as $i => $r) {
            $c = ['id' => 'BR-' . (101 + $i), 'title' => $r[0], 'category' => $r[1], 'location' => $r[2], 'status' => $r[3], 'priority' => $r[4], 'team' => $r[5], 'createdAt' => $date($r[6]), 'updatedAt' => $date($r[6]), 'residentId' => $r[7], 'resident' => $r[8], 'description' => $r[9], 'suggestion' => $r[10], 'recommendation' => '', 'assessment' => '', 'photo' => '', 'resolution' => null, 'feedback' => '', 'reopenCount' => 0, 'timeline' => []];
            self::event($c, ['name' => $c['resident']], 'Complaint submitted', $c['description'], $c['createdAt']);
            if (in_array($c['status'], ['Under Review', 'Assigned', 'In Progress', 'Resolved', 'Verified', 'Reopened'], true)) {
                $c['recommendation'] = $recommendations[$c['category']] ?? 'Review the concern with the appropriate barangay committee and coordinate the next action with the people involved.';
                $c['assessment'] = 'Reviewed the available information and set the category and priority for barangay follow-up.';
                self::event($c, ['name' => 'Maria Dela Cruz'], 'Assessment recorded', $c['recommendation'], $date($r[6]));
            }
            if ($c['team']) {
                self::event($c, ['name' => 'Maria Dela Cruz'], 'Assigned to ' . $c['team'], 'Recommended action shared with the responsible team.', $date($r[6] + 1));
            }
            if (in_array($c['status'], ['In Progress', 'Resolved', 'Verified', 'Reopened'], true)) {
                self::event($c, ['name' => $c['team']], 'Work started', 'The assigned team began the recommended action.', $date($r[6] + 2));
            }
            if (in_array($c['status'], ['Resolved', 'Verified', 'Reopened'], true)) {
                $work = match ($c['category']) {
                    'Drainage and flooding' => 'Inspected the drain and outlet, removed accumulated debris, and checked that water could pass freely.',
                    'Streetlights' => 'Inspected the fixture, replaced the defective light, and confirmed operation after dark.',
                    'Garbage collection' => 'Collected remaining waste and cleaned the collection point. Coordinated the next collection schedule.',
                    'Road obstruction' => 'Coordinated with stallholders and cleared the pedestrian footpath.',
                    default => 'Replaced the damaged faucet component and checked the fixture. No leak was observed after the repair.',
                };
                $c['resolution'] = ['notes' => $work, 'photo' => '', 'date' => $date($r[6] + 3), 'team' => $c['team']];
                self::event($c, ['name' => $c['team']], 'Resolution recorded', $work, $c['resolution']['date']);
            }
            if ($c['status'] === 'Verified') {
                $c['feedback'] = 'I checked the location and confirm that the concern has been resolved.';
                self::event($c, ['name' => $c['resident']], 'Resident verified resolution', $c['feedback'], $date($r[6] + 4));
            }
            if ($c['status'] === 'Reopened') {
                $c['feedback'] = 'The items were moved back onto the footpath the following day.';
                $c['reopenCount'] = 1;
                self::event($c, ['name' => $c['resident']], 'Complaint reopened', $c['feedback'], $date(-1));
            }
            if ($c['status'] === 'Returned for Information') {
                self::event($c, ['name' => 'Maria Dela Cruz'], 'More information requested', 'Please provide a street name or nearby landmark so the team can locate the concern.', $date(0));
            }
            if ($c['status'] === 'Referred to Another Office') {
                $c['referral'] = 'Water service provider';
                self::event($c, ['name' => 'Maria Dela Cruz'], 'Referred to another office', 'Referred to the water service provider for service-line assessment. This is not a verified resolution.', $date(-2));
            }
            $cases[] = $c;
        }
        return ['version' => 1, 'nextId' => 115, 'cases' => $cases];
    }
}
