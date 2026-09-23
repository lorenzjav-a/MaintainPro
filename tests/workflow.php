<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/domain.php';
$checks = 0;
function check(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $label); $checks++; }
function denied(callable $call, string $label): void { try { $call(); } catch (DomainException $e) { check(true, $label); return; } throw new RuntimeException('FAIL: expected denial: ' . $label); }
$guest = ['id' => null, 'name' => 'Anonymous resident', 'role' => 'guest'];
$official = ['id' => 'official', 'name' => 'Official', 'role' => 'official', 'team' => ''];
$staff = ['id' => 'staff', 'name' => 'Personnel', 'role' => 'personnel', 'team' => 'Maintenance crew', 'active' => true];
$other = array_replace($staff, ['id' => 'other']);
$report = ['category' => 'Roads and Infrastructure', 'concernType' => 'Pothole', 'keyPoints' => ['Deep', 'Near intersection'], 'purok' => 'Purok 2', 'street' => 'Test Street', 'exactArea' => 'Near test court'];
$photo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
$state = ['nextId' => 1, 'cases' => []];
$report['_residentGuidance'] = ConcernCatalog::suggestions($report['category'], $report['concernType'], $report['keyPoints']);
$report['selectedSuggestion'] = '1'; // An older open form must not turn guidance into a staff proposal.
$id = ComplaintWorkflow::submit($state, $guest, $report);
check(str_starts_with($id, 'CON-' . date('Y') . '-000001'), 'reference format');
$c = $state['cases'][0];
check($c['title'] === 'Pothole Concern' && $c['description'] === '' && $c['residentId'] === null, 'anonymous generated title and optional details');
check($c['keyPoints'] === $report['keyPoints'] && count($c['residentGuidance']) === 3, 'structured selections and exactly three resident guidance steps');
check($c['suggestion'] === '' && !array_key_exists('selectedSuggestion', $c) && !array_key_exists('suggestions', $c) && $c['recommendation'] === '', 'old selection ignored and staff plan stays separate');
foreach (['purok', 'street', 'exactArea', 'category', 'concernType'] as $field) denied(function () use (&$state, $guest, $report, $field) { ComplaintWorkflow::submit($state, $guest, array_replace($report, [$field => ''])); }, 'required ' . $field);
denied(function () use (&$state, $guest, $report) { ComplaintWorkflow::submit($state, $guest, array_replace($report, ['keyPoints' => ['Forged']])); }, 'unknown points');
denied(function () use (&$state, $official, $report) { ComplaintWorkflow::submit($state, $official, $report); }, 'staff cannot impersonate guest action');
check(!ComplaintWorkflow::canSee($c, $staff), 'unassigned location inaccessible');
ComplaintWorkflow::apply($state, $official, $id, 'assess', ['priority' => 'High', 'recommendation' => 'Inspect and repair.']);
ComplaintWorkflow::apply($state, $official, $id, 'assign', ['_assignee' => $staff]);
check(ComplaintWorkflow::canSee($state['cases'][0], $staff) && !ComplaintWorkflow::canSee($state['cases'][0], $other), 'specific personnel only even on same team');
$work = ['workStatus' => 'Inspection completed', 'actions' => ['Inspection'], 'photo' => $photo];
foreach (['', 'data:image/png;base64,' . base64_encode('<?php echo 1; ?>'), 'data:image/svg+xml;base64,' . base64_encode('<svg/>')] as $bad) denied(function () use (&$state, $staff, $id, $work, $bad) { ComplaintWorkflow::apply($state, $staff, $id, 'start', array_replace($work, ['photo' => $bad])); }, 'required real image');
check($state['cases'][0]['status'] === 'Assigned', 'failed update atomic');
ComplaintWorkflow::apply($state, $staff, $id, 'start', $work);
denied(function () use (&$state, $other, $id, $work) { ComplaintWorkflow::apply($state, $other, $id, 'note', $work); }, 'same team cannot act');
denied(function () use (&$state, $staff, $id, $work) { ComplaintWorkflow::apply($state, $staff, $id, 'note', array_replace($work, ['actions' => []])); }, 'structured action required');
ComplaintWorkflow::apply($state, $staff, $id, 'note', $work);
denied(function () use (&$state, $staff, $id, $work) { ComplaintWorkflow::apply($state, $staff, $id, 'resolve', $work); }, 'cannot resolve inspection alone');
ComplaintWorkflow::apply($state, $staff, $id, 'resolve', array_replace($work, ['workStatus' => 'Fully repaired', 'actions' => ['Repair']]));
$event = end($state['cases'][0]['timeline']);
check($event['actorId'] === 'staff' && strlen($event['evidenceId']) === 32 && $event['photo'] === $photo && $event['actions'] === ['Repair'], 'evidence bound to actor and structured event');
denied(function () use (&$state, $staff, $id) { ComplaintWorkflow::apply($state, $staff, $id, 'verify', []); }, 'personnel cannot close own outcome');
ComplaintWorkflow::apply($state, $official, $id, 'verify', []);
check($state['cases'][0]['status'] === 'Verified', 'official closes');
ComplaintWorkflow::apply($state, $official, $id, 'reopen', ['feedback' => 'Issue returned']);
check($state['cases'][0]['reopenCount'] === 1 && !ComplaintWorkflow::canSee($state['cases'][0], $staff), 'reopen needs fresh assessment and assignment');
ComplaintWorkflow::apply($state, $official, $id, 'exception', ['status' => 'Returned for Information', 'notes' => 'Inspect exact area']);
ComplaintWorkflow::apply($state, $official, $id, 'information', ['notes' => 'Area checked']);
check($state['cases'][0]['status'] === 'Submitted', 'staff follow-up preserved');
foreach (ConcernCatalog::TYPES as $category => $types) foreach ($types as $type) {
    foreach ([[], ConcernCatalog::POINTS[$category]] as $points) {
        $steps = ConcernCatalog::suggestions($category, $type, $points);
        check(ConcernCatalog::validGuidance($steps) && count(array_unique($steps)) === 3, 'three distinct resident steps for ' . $type);
        check(!preg_match('/^(Inspect|Schedule|Arrange|Have qualified personnel|Consider temporary patching)/m', implode("\n", $steps)), 'no staff tasks for ' . $type);
    }
}
$legacy = [['category' => 'Street Lighting', 'concern_type' => 'Exposed wiring', 'actions' => json_encode(['Repair wires.', 'Climb pole.', 'Replace bulb.'])]];
$hazard = ConcernCatalog::suggestions('Street Lighting', 'Exposed wiring', [], $legacy);
check(str_contains($hazard[0], 'Stay well away') && str_contains($hazard[2], 'emergency services'), 'electrical type is protective without a checked hazard point');
$custom = [['category' => 'Waste Management', 'concern_type' => 'Illegal dumping', 'actions' => json_encode(['purpose' => ConcernCatalog::GUIDANCE_PURPOSE, 'steps' => ['Keep children away.', 'Cover your household bins.', 'Use a safe route.']])]];
check(ConcernCatalog::suggestions('Waste Management', 'Illegal dumping', [], $custom)[1] === 'Cover your household bins.', 'new resident-specific curated rules accepted');
$custom[0]['actions'] = json_encode(['Arrange cleanup.', 'Inspect waste.', 'Schedule collection.']);
check(str_starts_with(ConcernCatalog::suggestions('Waste Management', 'Illegal dumping', [], $custom)[0], 'Keep children'), 'legacy staff rules never presented as resident advice');
$custom[0]['actions'] = json_encode(['purpose' => ConcernCatalog::GUIDANCE_PURPOSE, 'steps' => ['Incomplete']]);
check(ConcernCatalog::validGuidance(ConcernCatalog::suggestions('Waste Management', 'Illegal dumping', [], $custom)), 'invalid override falls back to complete guidance');
echo "PASS: $checks workflow, structured validation, evidence and authorization checks.\n";
