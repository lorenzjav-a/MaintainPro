<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
$test = new TestDatabase();
$checks = 0;
function check(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $label); $checks++; }
function denied(callable $call, string $label): void { try { $call(); } catch (DomainException) { check(true, $label); return; } throw new RuntimeException('FAIL: expected denial: ' . $label); }
try {
    $store = new ComplaintStore($test->connect());
    $db = new MaintainProDatabase($test->connect());
    $fixtures = new DatabaseTestFixtures($test->connect());
    $admin = $store->setup(['name' => 'Storage Official', 'email' => 'official@example.test', 'password' => 'Storage-password-42']);
    $makeStaff = function (string $name) use ($store, $admin): array {
        $user = $store->createUser($admin['id'], ['name' => $name, 'email' => strtolower($name) . '@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew']);
        $store->changeTemporaryPassword($user['id'], ['current_password' => $user['temporary_password'], 'password' => 'Storage-password-42', 'confirm_password' => 'Storage-password-42']);
        return $store->user($user['id']);
    };
    $staff = $makeStaff('Alpha'); $other = $makeStaff('Beta');
    $report = ['category' => 'Roads and Infrastructure', 'concernType' => 'Pothole', 'keyPoints' => ['Deep'], 'purok' => 'Original area', 'street' => 'Private street', 'exactArea' => 'Private gate'];
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
    $find = fn(string $id) => $store->concernForActor($admin['id'], $id);
    $mutate = function (array $actor, string $id, string $action, array $data = []) use ($store, $find): array {
        $store->mutate($actor['id'], $action, $id, $data, $find($id)['version']);
        return $find($id);
    };
    check(!$store->hasLocations(), 'existing installations begin with free-text locations');
    $receipt = $store->submitGuest($report + ['photo' => $png, 'photoName' => '../../unsafe.php.png'], 'storage-report');
    $id = $receipt['reference']; $c = $find($id);
    $evidenceId = $c['initialEvidenceId'];
    $evidence = $store->evidenceRecord($admin['id'], $evidenceId);
    check($c['photo'] === '' && end($c['timeline'])['photo'] === '', 'new evidence stored outside complaint JSON');
    check($evidence['complaint_id'] === $id && $evidence['uploaded_by'] === null && $evidence['evidence_type'] === 'resident_report', 'initial evidence belongs to anonymous report');
    check($evidence['original_filename'] === 'unsafe.php.png' && str_ends_with($evidence['file_path'], $evidenceId . '.png'), 'original filename cannot control saved path');
    check(EvidenceStorage::storedFile($evidence['file_path'])['mime_type'] === 'image/png', 'stored image revalidated');
    check(EvidenceStorage::storedFile('../config/mail.local.php') === null, 'stored file path traversal rejected');
    check($store->evidenceRecord($staff['id'], $evidenceId) === null, 'unassigned personnel cannot read evidence');
    $mutate($admin, $id, 'request_information', ['notes' => 'Which side of the gate?']);
    $tracked = $store->track($id, $receipt['trackingCode'], 'track-one');
    check($tracked['canFollowUp'] && $tracked['informationRequest']['message'] === 'Which side of the gate?', 'information request available only through tracking');
    denied(fn() => $store->submitFollowup(array_replace($receipt, ['description' => 'Response', 'trackingCode' => 'bad']), 'followup-bad'), 'invalid followup token');
    // array_replace is intentional: the forged token must replace the receipt value.
    denied(fn() => $store->submitFollowup(array_replace($receipt, ['description' => 'Response', 'trackingCode' => str_repeat('0', 48)]), 'followup-wrong'), 'wrong well-formed token');
    $result = $store->submitFollowup($receipt + ['description' => 'The eastern side.', 'photo' => $png], 'followup-valid');
    check($result['status'] === 'Submitted' && !$result['canFollowUp'] && $result['followUps'][0]['description'] === 'The eastern side.', 'reporter response returns to assessment');
    $followup = end($find($id)['timeline']);
    check($followup['publicReporterFollowup'] && $store->evidenceRecord($admin['id'], $followup['evidenceId'])['evidence_type'] === 'resident_followup', 'followup image tied to event');
    check(!str_contains(json_encode($result), 'Private') && !str_contains(json_encode($result), 'evidenceId'), 'followup tracking excludes private address and photos');
    denied(fn() => $store->submitFollowup($receipt + ['description' => 'Repeated'], 'followup-repeat'), 'response cannot replay after assessment resumes');
    $mutate($admin, $id, 'assess', ['priority' => 'High', 'recommendation' => 'Inspect and repair']);
    $mutate($admin, $id, 'assign', ['personnelId' => $staff['id']]);
    check($store->evidenceRecord($staff['id'], $evidenceId) !== null && $store->evidenceRecord($other['id'], $evidenceId) === null, 'evidence access follows individual assignment');
    $work = ['workStatus' => 'Inspection completed', 'actions' => ['Inspection'], 'photo' => $png];
    $version = $find($id)['version'];
    $files = glob(dirname(__DIR__) . '/uploads/evidence/' . $test->name . '/*');
    $fixtures->failNotifications(true);
    try { $mutate($staff, $id, 'start', $work); throw new RuntimeException('Expected transaction failure'); }
    catch (PDOException $error) { check(str_contains($error->getMessage(), 'Isolated transaction failure'), 'forced failure after file and evidence insertion'); }
    finally { $fixtures->failNotifications(false); }
    check($find($id)['version'] === $version && $find($id)['status'] === 'Assigned', 'failed action rolls back concern and version');
    check(glob(dirname(__DIR__) . '/uploads/evidence/' . $test->name . '/*') === $files, 'failed transaction removes newly written evidence file');
    $c = $mutate($staff, $id, 'start', $work);
    $event = end($c['timeline']); $metadata = $store->evidenceRecord($staff['id'], $event['evidenceId']);
    check($metadata['uploaded_by'] === $staff['id'] && (int)$metadata['created_at'] > 0 && $metadata['evidence_type'] === 'before', 'work evidence retains actor, time, and stage');
    $block = ['blockReason' => 'Waiting for Materials', 'recommendedAction' => 'Supply patching material', 'expectedAt' => date('Y-m-d', time() + 86400)];
    denied(fn() => $mutate($other, $id, 'block', $block), 'another personnel cannot block work');
    denied(fn() => $mutate($staff, $id, 'block', array_replace($block, ['expectedAt' => '2026-02-31'])), 'invalid availability date');
    $c = $mutate($staff, $id, 'block', $block);
    check($c['blocked']['active'] && $store->blockedConcerns($admin['id'])['total'] === 1, 'blocked report appears in management queue');
    denied(fn() => $store->blockedConcerns($staff['id']), 'blocked management queue restricted');
    denied(fn() => $mutate($staff, $id, 'manage_block', ['decision' => 'resume']), 'personnel cannot approve own block');
    denied(fn() => $mutate($admin, $id, 'manage_block', ['decision' => 'approve']), 'approval requires instructions');
    foreach (['approve', 'instructions'] as $decision) {
        $c = $mutate($admin, $id, 'manage_block', ['decision' => $decision, 'instructions' => 'Materials arrive tomorrow', 'dueAt' => date('Y-m-d\TH:i', time() + 172800)]);
        check($c['blocked']['officialDecision'] === $decision && $c['dueAt'] > time(), 'official decision and deadline: ' . $decision);
    }
    $mutate($admin, $id, 'manage_block', ['decision' => 'resume']);
    check($store->blockedConcerns($admin['id'])['total'] === 0, 'official can clear block without extra typing');
    $mutate($staff, $id, 'block', $block);
    $mutate($staff, $id, 'note', $work);
    check(empty($find($id)['blocked']), 'recording work resumes a blocked concern');
    $mutate($staff, $id, 'block', $block);
    $mutate($admin, $id, 'assign', ['personnelId' => $other['id']]);
    check(empty($find($id)['blocked']) && $store->evidenceRecord($staff['id'], $evidenceId) === null, 'reassignment clears delay and revokes old access');
    $duplicate = $store->submitGuest($report, 'duplicate');
    $duplicateId = $duplicate['reference'];
    denied(fn() => $mutate($admin, $id, 'link_concern', ['primaryConcernId' => $id]), 'cannot self-link');
    $mutate($admin, $duplicateId, 'link_concern', ['primaryConcernId' => $id]);
    check($find($duplicateId)['status'] === 'Linked to Primary' && $store->concernLinks($admin['id'], $id)['linkedConcerns'][0]['id'] === $duplicateId, 'links persist and primary lists original reports');
    denied(fn() => $mutate($admin, $id, 'link_concern', ['primaryConcernId' => $duplicateId]), 'cycles prevented');
    denied(fn() => $mutate($admin, $duplicateId, 'assess', ['priority' => 'High', 'recommendation' => 'Duplicate work']), 'linked report cannot create duplicate work');
    denied(fn() => $store->concernLinks($other['id'], $id), 'linked report identities private to official');
    $mutate($other, $id, 'start', $work);
    $mutate($other, $id, 'resolve', array_replace($work, ['workStatus' => 'Fully repaired', 'actions' => ['Repair']]));
    $mutate($admin, $id, 'verify');
    $tracking = $store->track($duplicateId, $duplicate['trackingCode'], 'linked-track');
    check($tracking['reference'] === $duplicateId && $tracking['linked'] && $tracking['status'] === 'Closed', 'original tracking follows primary resolution');
    check(!str_contains(json_encode($tracking), $id) && !str_contains(json_encode($tracking), 'Private') && !str_contains(json_encode($tracking), $other['email']), 'linked tracking exposes no primary ID, address, or staff email');
    check($store->metrics($admin['id'])['pending'] === 0, 'linked reports not counted as separate pending work');
    check($store->pagedConcerns($admin['id'], ['tab' => 'pending'])['total'] === 0, 'paged pending agrees with dashboard');
    check($store->pagedConcerns($other['id'], ['status' => 'Verified'])['total'] === 1 && $store->pagedConcerns($staff['id'], [])['total'] === 0, 'paged reads enforce assignment');
    check($store->pagedConcerns($admin['id'], ['search' => $duplicateId], 1, 1)['items'][0]['id'] === $duplicateId, 'search and pagination');
    $store->createLocation($admin['id'], ['name' => 'Purok 5', 'sortOrder' => 2]);
    $locationId = (int)$store->locations()[0]['id'];
    check($store->hasLocations(), 'managed-location mode enabled by configuration');
    denied(fn() => $store->createLocation($staff['id'], ['name' => 'Forged']), 'only official manages locations');
    denied(fn() => $store->createLocation($admin['id'], ['name' => 'purok 5']), 'duplicate location names rejected');
    denied(fn() => $store->submitGuest($report, 'unknown-area'), 'unknown typed location rejected once registry configured');
    denied(fn() => $store->submitGuest($report + ['locationId' => []], 'forged-area'), 'malformed location ID rejected');
    $managed = $store->submitGuest($report + ['locationId' => (string)$locationId], 'managed');
    check($find($managed['reference'])['locationDetails']['purokId'] === $locationId && $find($managed['reference'])['locationDetails']['purok'] === 'Purok 5', 'server uses configured ID and name, ignores forged typed name');
    $store->updateLocation($admin['id'], $locationId, ['name' => 'Purok Five', 'sortOrder' => 1]);
    check($find($managed['reference'])['locationDetails']['purok'] === 'Purok 5', 'renaming location retains historical snapshot');
    $store->updateLocation($admin['id'], $locationId, [], true);
    check($store->locations() === [] && count($store->locations($admin['id'])) === 1 && $store->hasLocations(), 'inactive list private; no fallback when registry inactive');
    denied(fn() => $store->submitGuest($report + ['locationId' => $locationId], 'inactive-area'), 'inactive location cannot receive new report');
    $c = $mutate($admin, $id, 'edit', $report + ['priority' => 'High', 'description' => '', 'recommendation' => 'Preserved plan']);
    check($c['locationDetails']['purok'] === 'Original area', 'historical free-text location can be retained during editing');
    $c = $mutate($admin, $managed['reference'], 'edit', $report + ['locationId' => $locationId, 'priority' => 'Low', 'recommendation' => 'Inspect']);
    check($c['locationDetails']['purok'] === 'Purok Five', 'official can edit historical inactive location');
    $audit = $store->auditLogs($admin['id']);
    check($audit['total'] > 10 && !str_contains(json_encode($audit), 'Storage-password') && !str_contains(json_encode($audit), 'password_hash'), 'audit saved without credentials');
    check($store->auditLogs($admin['id'], ['action' => 'location_deactivated'])['total'] === 1, 'audit action filter');
    check($store->auditLogs($admin['id'], ['user' => $admin['id'], 'date' => date('Y-m-d')], 2, 2)['page'] === 2, 'audit date/account filters and pagination');
    denied(fn() => $store->auditLogs($staff['id']), 'audit official only');
    denied(fn() => $store->generateBackup($staff['id']), 'backup official only');
    $backup = $store->generateBackup($admin['id']);
    $restoreName = 'maintainpro_test_' . bin2hex(random_bytes(8));
    DatabaseMaintenance::create($restoreName);
    try {
        $connection = br_database($restoreName);
        (new DatabaseTestFixtures($connection))->restoreBackup($backup['content']);
        $restored = new ComplaintStore($connection);
        check(count($restored->state()['cases']) === count($store->state()['cases']) && $restored->login('official@example.test', 'Storage-password-42', 'restore')['id'] === $admin['id'], 'SQL backup restores records and authentication');
        check($restored->evidenceRecord($admin['id'], $evidenceId)['file_path'] === $evidence['file_path'], 'backup retains protected evidence references');
        check($restored->concernLinks($admin['id'], $id)['linkedConcerns'][0]['id'] === $duplicateId, 'backup restores links and generated index columns');
        DatabaseMaintenance::initialize($connection);
        check(count($restored->locations($admin['id'])) === 1, 'restored migrations remain idempotent');
    } finally { DatabaseMaintenance::dropTestDatabase($restoreName); }
    check($store->auditLogs($admin['id'], ['action' => 'database_backup_generated'])['total'] === 1, 'backup generation audited');
    echo "PASS: $checks storage, rollback, follow-up, blocked-work, linking, location, audit and backup checks.\n";
} finally { $test->drop(); }
