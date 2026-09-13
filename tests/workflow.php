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
$state = ['nextId' => 1, 'cases' => []];
$official = ['id' => 'official-1', 'name' => 'Test Official', 'role' => 'official', 'team' => ''];
$resident = ['id' => 'resident-1', 'name' => 'Test Resident', 'role' => 'resident', 'team' => ''];
$sanitation = ['id' => 'personnel-1', 'name' => 'Test Sanitation', 'role' => 'personnel', 'team' => 'Sanitation team'];
$maintenance = ['id' => 'personnel-2', 'name' => 'Test Maintenance', 'role' => 'personnel', 'team' => 'Maintenance crew'];
check(ComplaintWorkflow::visible($state, $resident) === [], 'workspace starts empty');
$payload = ['title' => 'Blocked drain test', 'category' => 'Drainage and flooding', 'description' => 'Blocked drain beside the store.', 'location' => 'Test Street, Purok 3', 'suggestion' => 'Please inspect and clear it.'];
$id = ComplaintWorkflow::submit($state, $resident, $payload);
check($id === 'BR-1', 'unique reference');
check(record($state, $id)['status'] === 'Submitted', 'submission status');
check(record($state, $id)['suggestion'] === $payload['suggestion'], 'resident suggestion preserved');
blocked(function () use (&$state, $official, $payload) { ComplaintWorkflow::submit($state, $official, $payload); }, 'official cannot submit as resident');
blocked(function () use (&$state, $resident, $payload) { ComplaintWorkflow::submit($state, $resident, array_replace($payload, ['title' => '   '])); }, 'whitespace title');
blocked(function () use (&$state, $resident, $payload) { ComplaintWorkflow::submit($state, $resident, array_replace($payload, ['category' => 'invented'])); }, 'unknown category');
blocked(function () use (&$state, $official, $id) { ComplaintWorkflow::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']); }, 'assignment needs assessment');
$assessment = ['category' => 'Drainage and flooding', 'priority' => 'High', 'recommendation' => 'Inspect, clear the blockage, and check drainage flow.', 'assessment' => 'Site inspection recommended.'];
blocked(function () use (&$state, $resident, $id, $assessment) { ComplaintWorkflow::apply($state, $resident, $id, 'assess', $assessment); }, 'resident cannot make official recommendation');
$before = $state;
blocked(function () use (&$state, $official, $id, $assessment) { ComplaintWorkflow::apply($state, $official, $id, 'assess', array_replace($assessment, ['priority' => 'invalid'])); }, 'invalid priority');
check($state === $before, 'failed action is atomic');
ComplaintWorkflow::apply($state, $official, $id, 'assess', $assessment);
check(record($state, $id)['status'] === 'Under Review', 'assessment advances status');
check(record($state, $id)['suggestion'] === $payload['suggestion'], 'recommendation does not overwrite suggestion');
ComplaintWorkflow::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']);
check(record($state, $id)['status'] === 'Assigned', 'assignment recorded');
blocked(function () use (&$state, $maintenance, $id) { ComplaintWorkflow::apply($state, $maintenance, $id, 'start', []); }, 'wrong team cannot start');
blocked(function () use (&$state, $official, $id) { ComplaintWorkflow::apply($state, $official, $id, 'start', []); }, 'official cannot bypass personnel');
blocked(function () use (&$state, $sanitation, $id) { ComplaintWorkflow::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Done']); }, 'cannot resolve before starting');
ComplaintWorkflow::apply($state, $sanitation, $id, 'start', []);
ComplaintWorkflow::apply($state, $sanitation, $id, 'note', ['notes' => 'Team arrived and inspected the outlet.']);
check(record($state, $id)['status'] === 'In Progress', 'note does not change status');
blocked(function () use (&$state, $sanitation, $id) { ComplaintWorkflow::apply($state, $sanitation, $id, 'resolve', ['notes' => '   ']); }, 'resolution needs notes');
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6n8AAAAASUVORK5CYII=';
ComplaintWorkflow::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Cleared the drain and verified free water flow.', 'photo' => $png]);
check(record($state, $id)['status'] === 'Resolved', 'resolution awaits verification');
check(record($state, $id)['resolution']['photo'] === $png, 'completion evidence retained');
blocked(function () use (&$state, $official, $id) { ComplaintWorkflow::apply($state, $official, $id, 'verify', []); }, 'official cannot verify');
blocked(function () use (&$state, $resident, $id) { ComplaintWorkflow::apply($state, $resident, $id, 'reopen', ['feedback' => ' ']); }, 'reopening needs feedback');
ComplaintWorkflow::apply($state, $resident, $id, 'reopen', ['feedback' => 'Water is still pooling after rain.']);
check(record($state, $id)['status'] === 'Reopened', 'resident can reopen');
check(record($state, $id)['reopenCount'] === 1, 'reopen counter');
check(record($state, $id)['resolution']['photo'] === $png, 'prior resolution retained');
blocked(function () use (&$state, $sanitation, $id) { ComplaintWorkflow::apply($state, $sanitation, $id, 'start', []); }, 'reopened needs reassessment');
ComplaintWorkflow::apply($state, $official, $id, 'assess', $assessment);
ComplaintWorkflow::apply($state, $official, $id, 'assign', ['team' => 'Sanitation team']);
ComplaintWorkflow::apply($state, $sanitation, $id, 'start', []);
ComplaintWorkflow::apply($state, $sanitation, $id, 'resolve', ['notes' => 'Inspected and cleared the downstream outlet.']);
ComplaintWorkflow::apply($state, $resident, $id, 'verify', ['feedback' => 'Water now drains correctly.']);
check(record($state, $id)['status'] === 'Verified', 'full reassessment and verification');
check(count(record($state, $id)['timeline']) === 12, 'complete audit trail');
check(count(array_filter(record($state, $id)['timeline'], fn($t) => ($t['photo'] ?? '') === $png)) === 1, 'prior evidence retained in timeline');
blocked(function () use (&$state, $resident, $id) { ComplaintWorkflow::apply($state, $resident, $id, 'verify', []); }, 'duplicate verification');
$id2 = ComplaintWorkflow::submit($state, $resident, $payload);
ComplaintWorkflow::apply($state, $official, $id2, 'exception', ['status' => 'Returned for Information', 'notes' => 'Please add a landmark.']);
ComplaintWorkflow::apply($state, $resident, $id2, 'information', ['notes' => 'Beside the yellow corner store.']);
check(record($state, $id2)['status'] === 'Submitted', 'information returns to queue');
check(str_contains(record($state, $id2)['description'], 'yellow corner store'), 'information retained');
blocked(function () use (&$state, $official, $id2) { ComplaintWorkflow::apply($state, $official, $id2, 'exception', ['status' => 'Referred to Another Office', 'notes' => 'Outside barangay scope.']); }, 'referral needs office');
ComplaintWorkflow::apply($state, $official, $id2, 'exception', ['status' => 'Referred to Another Office', 'notes' => 'For utility inspection.', 'office' => 'Water service provider']);
check(record($state, $id2)['status'] === 'Referred to Another Office', 'referral distinct from resolved');
$id3 = ComplaintWorkflow::submit($state, $resident, $payload);
ComplaintWorkflow::apply($state, $official, $id3, 'exception', ['status' => 'Rejected', 'notes' => 'Duplicate report.']);
check(record($state, $id3)['status'] === 'Rejected', 'rejection recorded');
blocked(function () use (&$state, $resident, $payload) { ComplaintWorkflow::submit($state, $resident, array_replace($payload, ['photo' => 'data:image/png;base64,aGVsbG8='])); }, 'fake image rejected');
blocked(function () use (&$state, $resident, $payload, $png) { ComplaintWorkflow::submit($state, $resident, array_replace($payload, ['photo' => str_replace('image/png', 'image/jpeg', $png)])); }, 'MIME mismatch rejected');
$otherResident = array_replace($resident, ['id' => 'resident-2']);
$otherId = ComplaintWorkflow::submit($state, $otherResident, $payload);
check(ComplaintWorkflow::visible($state, $otherResident) === [record($state, $otherId)], 'resident sees only own complaint');
blocked(function () use (&$state, $resident, $otherId) { ComplaintWorkflow::apply($state, $resident, $otherId, 'information', ['notes' => 'Unauthorized edit']); }, 'other resident inaccessible');
$persisted = unserialize(serialize($state));
check(record($persisted, $id)['status'] === 'Verified', 'session serialization');
echo "PASS: $checks workflow, role, validation, evidence, and persistence checks.\n";
