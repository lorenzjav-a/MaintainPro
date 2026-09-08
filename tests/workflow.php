<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/domain.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function blocked(callable $operation, string $message): void
{
    try { $operation(); } catch (DomainException $e) { check(true, $message); return; }
    throw new RuntimeException('FAIL: expected validation error: ' . $message);
}
function record(array $state, string $id): array
{
    return $state['cases'][array_search($id, array_column($state['cases'], 'id'), true)];
}
$state = ComplaintDemo::seed();
$official = ComplaintDemo::actor('official');
$resident = ComplaintDemo::actor('resident');
$sanitation = ComplaintDemo::actor('personnel', 'Sanitation team');
$maintenance = ComplaintDemo::actor('personnel', 'Maintenance crew');
check(count($state['cases']) === 14, '14 sample complaints');
check(count(ComplaintDemo::visible($state, $resident)) === 8, 'resident sees own 8 complaints');
check(count(ComplaintDemo::visible($state, $sanitation)) === 5, 'personnel sees only own team');
foreach (ComplaintDemo::visible($state, $resident) as $c) check($c['residentId'] === $resident['id'], 'resident scope');
blocked(fn() => ComplaintDemo::actor('superuser'), 'unknown role');
$payload = ['title' => 'Blocked drain test', 'category' => 'Drainage and flooding', 'description' => 'Blocked drain beside the store.', 'location' => 'Test Street, Purok 3', 'suggestion' => 'Please inspect and clear it.'];
$id = ComplaintDemo::submit($state, $resident, $payload);
check($id === 'BR-115', 'unique reference');
check(record($state, $id)['status'] === 'Submitted', 'submission status');
check(record($state, $id)['suggestion'] === $payload['suggestion'], 'resident suggestion preserved');
blocked(function () use (&$state, $official, $payload) { ComplaintDemo::submit($state, $official, $payload); }, 'official cannot submit as resident');
blocked(function () use (&$state, $resident, $payload) { ComplaintDemo::submit($state, $resident, array_replace($payload, ['title' => '   '])); }, 'whitespace title');
blocked(function () use (&$state, $resident, $payload) { ComplaintDemo::submit($state, $resident, array_replace($payload, ['category' => 'invented'])); }, 'unknown category');
blocked(function () use (&$state, $official, $id) { ComplaintDemo::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']); }, 'assignment needs assessment');
$assessment = ['category' => 'Drainage and flooding', 'priority' => 'High', 'recommendation' => 'Inspect, clear the blockage, and check drainage flow.', 'assessment' => 'Site inspection recommended.'];
blocked(function () use (&$state, $resident, $id, $assessment) { ComplaintDemo::apply($state, $resident, $id, 'assess', $assessment); }, 'resident cannot make official recommendation');
$before = $state;
blocked(function () use (&$state, $official, $id, $assessment) { ComplaintDemo::apply($state, $official, $id, 'assess', array_replace($assessment, ['priority' => 'invalid'])); }, 'invalid priority');
check($state === $before, 'failed action is atomic');
ComplaintDemo::apply($state, $official, $id, 'assess', $assessment);
check(record($state, $id)['status'] === 'Under Review', 'assessment advances status');
check(record($state, $id)['suggestion'] === $payload['suggestion'], 'recommendation does not overwrite suggestion');
ComplaintDemo::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']);
check(record($state, $id)['status'] === 'Assigned', 'assignment recorded');
blocked(function () use (&$state, $maintenance, $id) { ComplaintDemo::apply($state, $maintenance, $id, 'start', []); }, 'wrong team cannot start');
blocked(function () use (&$state, $official, $id) { ComplaintDemo::apply($state, $official, $id, 'start', []); }, 'official cannot bypass personnel');
blocked(function () use (&$state, $sanitation, $id) { ComplaintDemo::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Done']); }, 'cannot resolve before starting');
ComplaintDemo::apply($state, $sanitation, $id, 'start', []);
ComplaintDemo::apply($state, $sanitation, $id, 'note', ['notes' => 'Team arrived and inspected the outlet.']);
check(record($state, $id)['status'] === 'In Progress', 'note does not change status');
blocked(function () use (&$state, $sanitation, $id) { ComplaintDemo::apply($state, $sanitation, $id, 'resolve', ['notes' => '   ']); }, 'resolution needs notes');
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6n8AAAAASUVORK5CYII=';
ComplaintDemo::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Cleared the drain and verified free water flow.', 'photo' => $png]);
check(record($state, $id)['status'] === 'Resolved', 'resolution awaits verification');
check(record($state, $id)['resolution']['photo'] === $png, 'completion evidence retained');
blocked(function () use (&$state, $official, $id) { ComplaintDemo::apply($state, $official, $id, 'verify', []); }, 'official cannot verify');
blocked(function () use (&$state, $resident, $id) { ComplaintDemo::apply($state, $resident, $id, 'reopen', ['feedback' => ' ']); }, 'reopening needs feedback');
ComplaintDemo::apply($state, $resident, $id, 'reopen', ['feedback' => 'Water is still pooling after rain.']);
check(record($state, $id)['status'] === 'Reopened', 'resident can reopen');
check(record($state, $id)['reopenCount'] === 1, 'reopen counter');
check(record($state, $id)['resolution']['photo'] === $png, 'prior resolution retained');
blocked(function () use (&$state, $sanitation, $id) { ComplaintDemo::apply($state, $sanitation, $id, 'start', []); }, 'reopened needs reassessment');
ComplaintDemo::apply($state, $official, $id, 'assess', $assessment);
ComplaintDemo::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']);
ComplaintDemo::apply($state, $sanitation, $id, 'start', []);
ComplaintDemo::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Inspected and cleared the downstream outlet.']);
ComplaintDemo::apply($state, $resident, $id, 'verify', ['feedback' => 'Water now drains correctly.']);
check(record($state, $id)['status'] === 'Verified', 'full reassessment and verification');
check(count(record($state, $id)['timeline']) === 12, 'complete audit trail');
check(count(array_filter(record($state, $id)['timeline'], fn($t) => ($t['photo'] ?? '') === $png)) === 1, 'prior evidence retained in timeline');
blocked(function () use (&$state, $resident, $id) { ComplaintDemo::apply($state, $resident, $id, 'verify', []); }, 'duplicate verification');
$id2 = ComplaintDemo::submit($state, $resident, $payload);
ComplaintDemo::apply($state, $official, $id2, 'exception', ['status' => 'Returned for Information', 'notes' => 'Please add a landmark.']);
ComplaintDemo::apply($state, $resident, $id2, 'information', ['notes' => 'Beside the yellow corner store.']);
check(record($state, $id2)['status'] === 'Submitted', 'information returns to queue');
check(str_contains(record($state, $id2)['description'], 'yellow corner store'), 'information retained');
blocked(function () use (&$state, $official, $id2) { ComplaintDemo::apply($state, $official, $id2, 'exception', ['status' => 'Referred to Another Office', 'notes' => 'Outside barangay scope.']); }, 'referral needs office');
ComplaintDemo::apply($state, $official, $id2, 'exception', ['status' => 'Referred to Another Office', 'notes' => 'For utility inspection.', 'office' => 'Water service provider']);
check(record($state, $id2)['status'] === 'Referred to Another Office', 'referral distinct from resolved');
$id3 = ComplaintDemo::submit($state, $resident, $payload);
ComplaintDemo::apply($state, $official, $id3, 'exception', ['status' => 'Rejected', 'notes' => 'Duplicate report.']);
check(record($state, $id3)['status'] === 'Rejected', 'rejection recorded');
blocked(function () use (&$state, $resident, $payload) { ComplaintDemo::submit($state, $resident, array_replace($payload, ['photo' => 'data:image/png;base64,aGVsbG8='])); }, 'fake image rejected');
blocked(function () use (&$state, $resident, $payload, $png) { ComplaintDemo::submit($state, $resident, array_replace($payload, ['photo' => str_replace('image/png', 'image/jpeg', $png)])); }, 'MIME mismatch rejected');
blocked(function () use (&$state, $resident) { ComplaintDemo::apply($state, $resident, 'BR-104', 'information', ['notes' => 'Unauthorized edit']); }, 'other resident inaccessible');
$persisted = unserialize(serialize($state));
check(record($persisted, $id)['status'] === 'Verified', 'session serialization');
echo "PASS: $checks workflow, role, validation, evidence, and persistence checks.\n";
