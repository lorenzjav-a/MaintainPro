<?php
declare(strict_types=1);
require_once __DIR__ . '/concern-catalog.php';
require_once __DIR__ . '/insights.php';

final class ComplaintWorkflow
{
    public const CATEGORIES = ['Road damage and potholes', 'Public infrastructure', 'Streetlights', 'Drainage and flooding', 'Garbage collection', 'Improper waste disposal', 'Water concerns', 'Noise complaints', 'Stray animals', 'Illegal parking', 'Road obstruction', 'Traffic concerns', 'Public facilities', 'Trees and vegetation', 'Electrical hazards', 'Unsafe structures', 'Sanitation and cleanliness', 'Neighbor concerns', 'Community disputes', 'Business concerns', 'Other concerns'];
    public const TEAMS = ['Maintenance crew', 'Sanitation team', 'Barangay response team', 'Peace and order committee', 'Environment committee'];
    public const STATUSES = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Verified', 'Returned for Information', 'Linked to Primary', 'Reopened', 'Rejected', 'Referred to Another Office'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

    public static function canSee(array $c, array $actor): bool
    {
        return $actor['role'] === 'official'
            || ($actor['role'] === 'personnel' && !empty($c['assignedUserId']) && $actor['id'] === $c['assignedUserId']);
    }

    public static function visible(array $state, array $actor): array
    {
        return array_values(array_filter($state['cases'], fn($c) => self::canSee($c, $actor)));
    }

    public static function text(mixed $value, string $label, int $limit = 4000, bool $required = true): string
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
        if (!$info || strlen($bytes) > 1048576 || $info['mime'] !== 'image/' . $match[1] || $info[0] * $info[1] > 20000000) {
            throw new DomainException('The attached image is invalid or exceeds 20 megapixels.');
        }
        return $value;
    }

    private static function event(array &$c, array $actor, string $title, string $note = '', ?string $date = null, string $photo = ''): void
    {
        $date ??= date(DATE_ATOM);
        $c['timeline'][] = ['title' => $title, 'note' => $note, 'actor' => $actor['name'], 'actorId' => $actor['id'] ?? null, 'date' => $date, 'photo' => $photo,
            'evidenceId' => $photo ? bin2hex(random_bytes(16)) : null];
        $c['updatedAt'] = $date;
    }

    public static function submit(array &$state, array $actor, array $data): string
    {
        if ($actor['role'] !== 'guest') throw new DomainException('Use the public concern form.');
        [$category, $type, $points] = ConcernCatalog::selections($data);
        $managedLocation = $data['_location'] ?? null;
        $location = [
            'purokId' => is_array($managedLocation) ? (int)$managedLocation['id'] : null,
            'purok' => is_array($managedLocation)
                ? self::text($managedLocation['name'] ?? '', 'Purok / Sitio', 120)
                : self::text($data['purok'] ?? '', 'Purok / Sitio', 120),
        ];
        foreach (['street', 'exactArea', 'landmark'] as $field) $location[$field] = self::text($data[$field] ?? '', ucfirst($field), 120, $field !== 'landmark');
        $c = [
            'id' => 'CON-' . date('Y') . '-' . str_pad((string)$state['nextId'], 6, '0', STR_PAD_LEFT),
            'title' => $type . ' Concern', 'concernType' => $type, 'keyPoints' => $points,
            'category' => $category, 'locationDetails' => $location,
            'description' => self::text($data['description'] ?? '', 'Additional details', 4000, false),
            'location' => implode(', ', array_filter([$location['purok'], $location['street'], $location['exactArea'], $location['landmark']])),
            'suggestion' => '', // Retained for legacy exports; new reports never propose a staff action.
            'residentGuidance' => $data['_residentGuidance'] ?? ConcernCatalog::suggestions($category, $type, $points),
            'assignedUserId' => null, 'assignedName' => '',
            'photo' => self::photo($data['photo'] ?? ''),
            'status' => 'Submitted', 'priority' => 'Medium', 'team' => '',
            'residentId' => null, 'resident' => 'Anonymous resident',
            'recommendation' => '', 'assessment' => '', 'resolution' => null, 'feedback' => '',
            'reopenCount' => 0, 'createdAt' => date(DATE_ATOM), 'updatedAt' => date(DATE_ATOM), 'timeline' => [],
        ];
        self::event($c, $actor, 'Concern submitted', $c['description'], null, $c['photo']);
        $c['timeline'][0]['evidenceType'] = 'Initial Evidence';
        $c['priorityRecommendation'] = ConcernInsights::priority($c);
        $state['nextId']++;
        array_unshift($state['cases'], $c);
        return $c['id'];
    }

    public static function apply(array &$state, array $actor, string $id, string $action, array $data): void
    {
        $index = array_search($id, array_column($state['cases'], 'id'), true);
        if ($index === false || !self::canSee($state['cases'][$index], $actor)) {
            throw new DomainException('This concern is not available to your account.');
        }
        // Work on a copy: failed validation cannot partially mutate a record.
        $c = $state['cases'][$index];
        $assessment = in_array($c['status'], ['Submitted', 'Under Review', 'Reopened'], true);
        $official = $actor['role'] === 'official';
        $personnel = $actor['role'] === 'personnel' && self::canSee($c, $actor);
        switch ($action) {
            case 'assess':
                self::guard($official && $assessment, 'This concern cannot be assessed at this stage.');
                $c['recommendation'] = self::text($data['recommendation'] ?? '', 'Barangay recommended action');
                // Category/type are edited together in the concern information form.
                $c['priority'] = self::choice($data['priority'] ?? '', self::PRIORITIES, 'priority');
                $c['assessment'] = self::text($data['assessment'] ?? '', 'Assessment notes', 3000, false);
                $c['status'] = 'Under Review';
                self::event($c, $actor, 'Assessment recorded', $c['recommendation'] . ($c['assessment'] ? "\nAssessment notes: " . $c['assessment'] : ''));
                break;
            case 'assign':
                self::guard($official && in_array($c['status'], ['Under Review', 'Assigned', 'In Progress'], true) && $c['recommendation'] !== '', 'Save the official assessment and recommended action before assigning.');
                $assigned = $data['_assignee'] ?? null;
                self::guard(is_array($assigned) && $assigned['role'] === 'personnel' && (bool)$assigned['active'], 'Choose an active personnel account.');
                $c['team'] = self::choice($assigned['team'], self::TEAMS, 'team');
                $c['assignedUserId'] = $assigned['id'];
                $c['assignedName'] = $assigned['name'];
                // A blank deadline is allowed; supplied values use the workspace timezone.
                $deadline = self::text($data['dueAt'] ?? '', 'Target completion', 16, false);
                $due = $deadline !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $deadline) : false;
                self::guard($deadline === '' || ($due && $due->format('Y-m-d\TH:i') === $deadline), 'Choose a valid target completion date and time.');
                $c['dueAt'] = $due ? $due->getTimestamp() : null;
                $c['status'] = 'Assigned';
                self::event($c, $actor, 'Personnel assignment updated', $assigned['name'] . ' / ' . $c['team']);
                $c['timeline'][array_key_last($c['timeline'])]['dueAt'] = $c['dueAt'];
                break;
            case 'start':
            case 'note':
            case 'resolve':
                self::guard(($personnel || $official) && ($action === 'start' ? $c['status'] === 'Assigned' : $c['status'] === 'In Progress'), 'This work action is not available at the current stage.');
                $photo = self::photo($data['photo'] ?? '');
                self::guard($photo !== '', 'Upload image evidence before recording work or completion.');
                $workStatus = self::choice($data['workStatus'] ?? '', ConcernCatalog::WORK_STATUSES, 'work status');
                if ($action === 'resolve') self::guard($workStatus === 'Fully repaired', 'Completion requires the Fully repaired work status.');
                $actions = ConcernCatalog::multiple($data['actions'] ?? [], ConcernCatalog::ACTIONS, 'action taken', true);
                $notes = self::text($data['notes'] ?? '', 'Additional notes', 4000, in_array('Other', $actions, true));
                $note = $workStatus . ': ' . implode(', ', $actions) . ($notes ? "\n" . $notes : '');
                $c['status'] = $action === 'resolve' ? 'Resolved' : 'In Progress';
                if ($action === 'resolve') $c['resolution'] = ['notes' => $note, 'photo' => $photo, 'date' => date(DATE_ATOM), 'team' => $c['team'], 'uploadedBy' => $actor['id']];
                self::event($c, $actor, match ($action) {'start' => 'Work started', 'note' => 'Progress update', default => 'Resolution recorded'}, $note, null, $photo);
                $last = array_key_last($c['timeline']);
                $c['timeline'][$last]['workStatus'] = $workStatus;
                $c['timeline'][$last]['actions'] = $actions;
                $c['timeline'][$last]['evidenceType'] = $action === 'resolve' ? 'Completion Evidence' : (in_array($workStatus, ['Arrived at location', 'Inspection completed'], true) ? 'Inspection Evidence' : 'Progress Evidence');
                break;
            case 'verify':
            case 'reopen':
                self::guard($official && in_array($c['status'], $action === 'verify' ? ['Resolved'] : ['Resolved', 'Verified', 'Rejected', 'Referred to Another Office'], true), 'Only an official can review this outcome.');
                $c['feedback'] = self::text($data['feedback'] ?? '', 'Feedback', 3000, $action === 'reopen');
                $c['status'] = $action === 'verify' ? 'Verified' : 'Reopened';
                if ($action === 'reopen') {
                    $c['reopenCount']++;
                }
                if ($action === 'reopen') { $c['assignedUserId'] = null; $c['assignedName'] = ''; $c['team'] = ''; }
                self::event($c, $actor, $action === 'verify' ? 'Official closed concern' : 'Concern reopened', $c['feedback'] ?: 'The official reviewed the evidence and closed this concern.');
                break;
            case 'request_information':
                self::guard($official && $assessment, 'More information can only be requested while assessing a concern.');
                $note = self::text($data['notes'] ?? '', 'Information request', 3000);
                $c['status'] = 'Returned for Information';
                self::event($c, $actor, 'More information requested', $note);
                $c['timeline'][array_key_last($c['timeline'])]['publicInformationRequest'] = true;
                break;
            case 'exception':
                self::guard($official && $assessment, 'This action is only available during assessment.');
                $c['status'] = self::choice($data['status'] ?? '', ['Rejected', 'Referred to Another Office'], 'action');
                $note = self::text($data['notes'] ?? '', 'Reason', 3000);
                $office = $c['status'] === 'Referred to Another Office' ? self::text($data['office'] ?? '', 'Receiving office', 200) : '';
                if ($office) {
                    $c['referral'] = $office;
                }
                self::event($c, $actor, $c['status'], ($office ? 'Receiving office: ' . $office . "\n" : '') . $note);
                break;
            case 'edit':
                self::guard($official, 'Only an official can edit concern information.');
                $before = array_intersect_key($c, array_flip(['priority', 'category', 'concernType', 'keyPoints', 'location', 'description', 'recommendation']));
                $oldPriority = $c['priority'];
                $c['priority'] = self::choice($data['priority'] ?? '', self::PRIORITIES, 'priority');
                $c['recommendation'] = self::text($data['recommendation'] ?? '', 'Official recommendation', 4000, false);
                $c['description'] = self::text($data['description'] ?? '', 'Additional details', 4000, false);
                if (!empty($c['concernType'])) {
                    [$category, $type, $points] = ConcernCatalog::selections($data);
                    // Keep the guidance shown at submission and any legacy suggestion as historical snapshots.
                    [$c['category'], $c['concernType'], $c['keyPoints']] = [$category, $type, $points];
                    $c['title'] = $c['concernType'] . ' Concern';
                    $managedLocation = $data['_location'] ?? null;
                    if (is_array($managedLocation)) {
                        $c['locationDetails']['purokId'] = (int)$managedLocation['id'];
                        $c['locationDetails']['purok'] = self::text($managedLocation['name'] ?? '', 'Purok / Sitio', 120);
                    } else {
                        // Compatibility for direct domain tests and historical edits. Runtime forms resolve a managed location first.
                        $c['locationDetails']['purokId'] = $c['locationDetails']['purokId'] ?? null;
                        $c['locationDetails']['purok'] = self::text($data['purok'] ?? ($c['locationDetails']['purok'] ?? ''), 'Purok / Sitio', 120);
                    }
                    foreach (['street', 'exactArea', 'landmark'] as $field) $c['locationDetails'][$field] = self::text($data[$field] ?? '', ucfirst($field), 120, $field !== 'landmark');
                    $c['location'] = implode(', ', array_filter([
                        $c['locationDetails']['purok'], $c['locationDetails']['street'],
                        $c['locationDetails']['exactArea'], $c['locationDetails']['landmark'],
                    ]));
                } else {
                    $c['category'] = self::choice($data['category'] ?? $c['category'], self::CATEGORIES, 'category');
                    $c['location'] = self::text($data['location'] ?? '', 'Location', 500);
                }
                self::event($c, $actor, 'Concern information updated', 'Priority: ' . $oldPriority . ' → ' . $c['priority'] . '. Information and official recommendation reviewed.');
                $c['timeline'][array_key_last($c['timeline'])]['changes'] = ['before' => $before, 'after' => array_intersect_key($c, $before)];
                break;
            case 'link_concern':
                self::guard($official && !in_array($c['status'], ['Linked to Primary', 'Verified'], true), 'This concern cannot be linked at its current stage.');
                $primary = $data['_primary'] ?? null;
                self::guard(is_array($primary) && !empty($primary['id']) && $primary['id'] !== $c['id'], 'Choose a valid primary concern.');
                $note = self::text($data['notes'] ?? '', 'Link note', 1000, false);
                $c['linkedPrimaryId'] = $primary['id'];
                $c['assignedUserId'] = null;
                $c['assignedName'] = '';
                $c['team'] = '';
                $c['dueAt'] = null;
                $c['blocked'] = null;
                $c['status'] = 'Linked to Primary';
                self::event($c, $actor, 'Linked to primary concern', $primary['id'] . ($note !== '' ? "\n" . $note : ''));
                break;
            case 'block':
                self::guard(($personnel || $official) && in_array($c['status'], ['Assigned', 'In Progress'], true), 'This work cannot be marked blocked at its current stage.');
                $reason = self::choice($data['blockReason'] ?? '', ConcernCatalog::BLOCK_REASONS, 'block reason');
                $notes = self::text($data['blockNotes'] ?? '', 'Block notes', 2000, false);
                $recommended = self::text($data['recommendedAction'] ?? '', 'Recommended action', 1500);
                $expected = self::text($data['expectedAt'] ?? '', 'Expected availability', 10, false);
                $expectedDate = $expected !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $expected) : false;
                self::guard($expected === '' || ($expectedDate && $expectedDate->format('Y-m-d') === $expected), 'Choose a valid expected availability date.');
                $photo = self::photo($data['photo'] ?? '');
                $blockedAt = date(DATE_ATOM);
                $c['status'] = 'In Progress';
                $c['blocked'] = ['active' => true, 'reason' => $reason, 'notes' => $notes, 'recommendedAction' => $recommended,
                    'expectedAt' => $expected ?: null, 'blockedAt' => $blockedAt, 'reportedBy' => $actor['name'], 'reportedById' => $actor['id']];
                self::event($c, $actor, 'Work blocked / delayed', $reason . "\nRecommended action: " . $recommended . ($notes !== '' ? "\n" . $notes : ''), $blockedAt, $photo);
                $last = array_key_last($c['timeline']);
                $c['timeline'][$last]['block'] = $c['blocked'];
                if ($photo !== '') $c['timeline'][$last]['evidenceType'] = 'Progress Evidence';
                break;
            case 'manage_block':
                self::guard($official && !empty($c['blocked']['active']), 'This concern is not currently blocked.');
                $decision = self::choice($data['decision'] ?? '', ['approve', 'instructions', 'resume'], 'blocked-work decision');
                $instructions = self::text($data['instructions'] ?? '', 'Official instructions', 2000, $decision !== 'resume');
                $deadline = self::text($data['dueAt'] ?? '', 'Target completion', 16, false);
                $due = $deadline !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $deadline) : false;
                self::guard($deadline === '' || ($due && $due->format('Y-m-d\TH:i') === $deadline), 'Choose a valid target completion date and time.');
                if ($deadline !== '') $c['dueAt'] = $due->getTimestamp();
                $title = match ($decision) {'approve' => 'Requested action approved', 'instructions' => 'Blocked-work instructions updated', default => 'Work cleared to resume'};
                $note = $instructions !== '' ? $instructions : 'The official cleared this concern to resume work.';
                if ($decision === 'resume') $c['blocked'] = null;
                else {
                    $c['blocked']['officialDecision'] = $decision;
                    $c['blocked']['officialInstructions'] = $instructions;
                    $c['blocked']['reviewedAt'] = date(DATE_ATOM);
                    $c['blocked']['reviewedBy'] = $actor['name'];
                }
                self::event($c, $actor, $title, $note);
                if ($deadline !== '') $c['timeline'][array_key_last($c['timeline'])]['dueAt'] = $c['dueAt'];
                break;
            default:
                throw new DomainException('Unknown action.');
        }
        if (in_array($action, ['assess', 'edit'], true)) {
            $c['priorityRecommendation'] = ConcernInsights::priority($c);
            $c['priorityDecision'] = ['priority' => $c['priority'], 'recommended' => $c['priorityRecommendation']['priority'],
                'overridden' => $c['priority'] !== $c['priorityRecommendation']['priority'], 'actorId' => $actor['id'], 'date' => date(DATE_ATOM)];
            $c['timeline'][array_key_last($c['timeline'])]['priorityDecision'] = $c['priorityDecision'];
        }
        $state['cases'][$index] = $c;
    }

    public static function reporterFollowup(array &$c, array $data): void
    {
        self::guard($c['status'] === 'Returned for Information', 'This concern is not waiting for more information.');
        $description = self::text($data['description'] ?? '', 'Additional information', 4000);
        $photo = self::photo($data['photo'] ?? '');
        $actor = ['id' => null, 'name' => 'Anonymous reporter', 'role' => 'guest'];
        $c['status'] = 'Submitted';
        self::event($c, $actor, 'Reporter information submitted', $description, null, $photo);
        $last = array_key_last($c['timeline']);
        $c['timeline'][$last]['publicReporterFollowup'] = true;
        if ($photo !== '') $c['timeline'][$last]['evidenceType'] = 'Resident Follow-up';
    }

    private static function guard(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new DomainException($message);
        }
    }

}
