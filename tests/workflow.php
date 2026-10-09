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
denied(function () use (&$state, $official, $report) { ComplaintWorkflow::submit($state, array_replace($official, ['role' => 'forged']), $report); }, 'unknown role cannot submit');
check(!ComplaintWorkflow::canSee($c, $staff), 'unassigned location inaccessible');
ComplaintWorkflow::apply($state, $official, $id, 'assess', ['priority' => 'High', 'recommendation' => 'Inspect and repair.']);
ComplaintWorkflow::apply($state, $official, $id, 'assign', ['team' => $staff['team']]);
check(!ComplaintWorkflow::canSee($state['cases'][0], $staff), 'team offer does not grant accepted-work access');
ComplaintWorkflow::acceptTeamWork($state['cases'][0],$staff);
check(ComplaintWorkflow::canSee($state['cases'][0], $staff) && !ComplaintWorkflow::canSee($state['cases'][0], $other), 'accepted personnel receives exclusive work access');
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
ComplaintWorkflow::apply($state, $official, $id, 'request_information', ['notes' => 'Please clarify the exact area']);
check($state['cases'][0]['status'] === 'Reopened' && ComplaintWorkflow::pendingInformationRequest($state['cases'][0]) !== null, 'information request does not replace operational status');
ComplaintWorkflow::apply($state, $official, $id, 'assess', ['priority' => 'High', 'recommendation' => 'Inspect the clarified area']);
ComplaintWorkflow::apply($state, $official, $id, 'assign', ['team' => $staff['team']]);
ComplaintWorkflow::reporterFollowup($state['cases'][0], ['description' => 'Beside the covered court']);
check($state['cases'][0]['status'] === 'Assigned' && ComplaintWorkflow::pendingInformationRequest($state['cases'][0]) === null, 'reporter follow-up preserves continued assignment');
$officialWork = ['nextId' => 1, 'cases' => []];
$officialWorkId = ComplaintWorkflow::submit($officialWork, $guest, $report);
ComplaintWorkflow::apply($officialWork, $official, $officialWorkId, 'assess', ['priority' => 'High', 'recommendation' => 'Complete this directly.']);
ComplaintWorkflow::apply($officialWork, $official, $officialWorkId, 'assign', ['team' => $staff['team']]);
ComplaintWorkflow::apply($officialWork, $official, $officialWorkId, 'start', $work);
ComplaintWorkflow::apply($officialWork, $official, $officialWorkId, 'note', $work);
ComplaintWorkflow::apply($officialWork, $official, $officialWorkId, 'resolve', array_replace($work, ['workStatus' => 'Fully repaired', 'actions' => ['Repair']]));
$officialWorkEvent = end($officialWork['cases'][0]['timeline']);
check($officialWork['cases'][0]['status'] === 'Resolved' && $officialWorkEvent['actorId'] === $official['id'], 'official can complete personnel workflow actions when needed');
$exceptionState = ['nextId' => 1, 'cases' => []];
$exceptionId = ComplaintWorkflow::submit($exceptionState, $guest, $report);
ComplaintWorkflow::apply($exceptionState, $official, $exceptionId, 'assess', ['priority' => 'Low', 'recommendation' => 'Review the report.']);
denied(function () use (&$exceptionState, $official, $exceptionId) { ComplaintWorkflow::apply($exceptionState, $official, $exceptionId, 'exception', ['status' => 'Referred to Another Office', 'office' => 'External office', 'notes' => 'Forward this report.']); }, 'new referral outcome removed');
check($exceptionState['cases'][0]['status'] === 'Under Review', 'rejected referral request leaves concern unchanged');
ComplaintWorkflow::apply($exceptionState, $official, $exceptionId, 'exception', ['status' => 'Rejected', 'notes' => 'Outside the supported concern criteria.']);
check($exceptionState['cases'][0]['status'] === 'Rejected' && end($exceptionState['cases'][0]['timeline'])['title'] === 'Rejected', 'official can still reject with a recorded reason');
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
