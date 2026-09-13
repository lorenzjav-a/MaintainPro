<?php
declare(strict_types=1);

final class ComplaintWorkflow
{
    public const CATEGORIES = ['Road damage and potholes', 'Public infrastructure', 'Streetlights', 'Drainage and flooding', 'Garbage collection', 'Improper waste disposal', 'Water concerns', 'Noise complaints', 'Stray animals', 'Illegal parking', 'Road obstruction', 'Traffic concerns', 'Public facilities', 'Trees and vegetation', 'Electrical hazards', 'Unsafe structures', 'Sanitation and cleanliness', 'Neighbor concerns', 'Community disputes', 'Business concerns', 'Other concerns'];
    public const TEAMS = ['Maintenance crew', 'Sanitation team', 'Barangay response team', 'Peace and order committee', 'Environment committee'];
    public const STATUSES = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Verified', 'Returned for Information', 'Reopened', 'Rejected', 'Referred to Another Office'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

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

}
