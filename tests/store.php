<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
$checks = 0;
function verifyStore(bool $value, string $message): void
{
    global $checks;
    if (!$value) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function denyStore(callable $call, string $message, string $type = DomainException::class): void
{
    try { $call(); } catch (Throwable $e) {
        if (!$e instanceof $type) throw $e;
        verifyStore(true, $message);
        return;
    }
    throw new RuntimeException('FAIL: expected rejection: ' . $message);
}
function storedCase(ComplaintStore $store, string $id): array
{
    foreach ($store->state()['cases'] as $c) if ($c['id'] === $id) return $c;
    throw new RuntimeException('Test case missing');
}
$testDatabase = new TestDatabase();
$store = null;
$other = null;
$rawDb = null;
try {
    $store = new ComplaintStore($testDatabase->connect());
    $password = 'Test-password-47';
    $adminData = ['name' => 'Test Official', 'email' => 'official@example.test', 'password' => $password];
    $residentData = ['name' => 'Test Resident', 'email' => 'resident@example.test', 'password' => $password];
    verifyStore($store->needsSetup(), 'new database needs setup');
    denyStore(fn() => $store->register($residentData), 'registration before setup');
    $admin = $store->setup($adminData);
    verifyStore(!$store->needsSetup(), 'setup completed');
    verifyStore($admin['role'] === 'official', 'first account is official');
    verifyStore(!array_key_exists('password_hash', $admin), 'hash excluded from account response');
    denyStore(fn() => $store->setup($adminData), 'first official setup is one-time');
    $rawDb = $testDatabase->connect();
    $hash = (new MaintainProDatabase($rawDb))->passwordHash($admin['id']);
    verifyStore($hash !== $password && password_verify($password, $hash), 'password stored as verified hash');
    verifyStore($store->login('  OFFICIAL@example.test ', $password, 'test-client')['id'] === $admin['id'], 'normalized login');
    denyStore(fn() => $store->login($adminData['email'], 'incorrect', 'wrong-client'), 'wrong password');
    $resident = $store->register($residentData + ['role' => 'official', 'team' => 'Maintenance crew']);
    verifyStore($resident['role'] === 'resident' && $resident['team'] === '', 'registration cannot elevate role');
    $resident2 = $store->register(['name' => 'Other Resident', 'email' => 'other@example.test', 'password' => $password]);
    denyStore(fn() => $store->register($residentData), 'duplicate email');
    denyStore(fn() => $store->register(['name' => 'X', 'email' => 'invalid', 'password' => 'short']), 'invalid account fields');
    denyStore(fn() => $store->register(array_replace($residentData, ['email' => 'short@example.test', 'password' => 'short'])), 'short password');
    denyStore(fn() => $store->register(array_replace($residentData, ['email' => 'blank@example.test', 'password' => str_repeat(' ', 12)])), 'blank password');
    denyStore(fn() => $store->users($resident['id']), 'resident cannot list accounts');
    denyStore(fn() => $store->createUser($resident['id'], $adminData), 'resident cannot create staff');
    $staff = $store->createUser($admin['id'], ['name' => 'Sanitation Staff', 'email' => 'staff@example.test', 'password' => $password, 'role' => 'personnel', 'team' => 'Sanitation team']);
    $wrongStaff = $store->createUser($admin['id'], ['name' => 'Maintenance Staff', 'email' => 'maintenance@example.test', 'password' => $password, 'role' => 'personnel', 'team' => 'Maintenance crew']);
    verifyStore($staff['must_change_password'] && $staff['temporary_password'] !== $password, 'official-created account receives generated temporary password');
    verifyStore($store->login($staff['email'], $staff['temporary_password'], 'temporary-login')['must_change_password'], 'temporary login remains restricted');
    verifyStore(!str_contains(json_encode($store->users($admin['id'])), $staff['temporary_password']), 'temporary password absent from user listing');
    denyStore(fn() => $store->changeTemporaryPassword($staff['id'], ['current_password' => 'wrong', 'password' => $password, 'confirm_password' => $password]), 'temporary password change requires current credential');
    denyStore(fn() => $store->changeTemporaryPassword($staff['id'], ['current_password' => $staff['temporary_password'], 'password' => $staff['temporary_password'], 'confirm_password' => $staff['temporary_password']]), 'cannot reuse temporary password');
    denyStore(fn() => $store->updateProfile($staff['id'], []), 'profile cannot bypass initial password change');
    denyStore(fn() => $store->mutate($staff['id'], 'start', 'BR-1', [], 1), 'pending account cannot mutate complaints');
    foreach ([$staff, $wrongStaff] as $newStaff) {
        $completed = $store->changeTemporaryPassword($newStaff['id'], ['current_password' => $newStaff['temporary_password'], 'password' => $password, 'confirm_password' => $password]);
        verifyStore(!$completed['must_change_password'] && (int)$completed['auth_version'] === 2, 'own password activates account and revokes temporary sessions');
        denyStore(fn() => $store->login($newStaff['email'], $newStaff['temporary_password'], 'old-temporary'), 'temporary credential invalid after password change');
    }
    verifyStore($staff['team'] === 'Sanitation team', 'staff has team');
    verifyStore(count($store->users($admin['id'])) === 5, 'official can list users');
    denyStore(fn() => $store->updateUser($admin['id'], $admin['id'], ['role' => 'official', 'active' => '0']), 'cannot deactivate own official account');
    denyStore(fn() => $store->updateUser($admin['id'], $admin['id'], ['role' => 'resident', 'active' => '1']), 'cannot remove own official access');
    denyStore(fn() => $store->createUser($admin['id'], ['name' => 'Bad Team', 'email' => 'badteam@example.test', 'password' => $password, 'role' => 'personnel', 'team' => 'Unknown team']), 'invalid team');
    $report = ['title' => 'Saved drainage report', 'category' => 'Drainage and flooding', 'description' => 'Drain is blocked.', 'location' => 'Test Street, Purok 1', 'suggestion' => 'Please inspect it.'];
    $id = $store->mutate($resident['id'], 'submit', '', $report, null);
    verifyStore($id === 'BR-1', 'saved workspace has own ID sequence');
    verifyStore(storedCase($store, $id)['version'] === 1, 'first record version');
    $other = new ComplaintStore($testDatabase->connect());
    verifyStore(count($other->state()['cases']) === 1, 'second connection sees durable record');
    verifyStore(ComplaintWorkflow::visible($other->state(), $resident2) === [], 'other resident cannot read');
    denyStore(fn() => $other->mutate($resident2['id'], 'verify', $id, [], 1), 'other resident cannot update');
    $assessment = ['category' => 'Drainage and flooding', 'priority' => 'High', 'recommendation' => 'Inspect and clear the drain.', 'assessment' => 'Check outlet as well.'];
    $store->mutate($admin['id'], 'assess', $id, $assessment, 1);
    verifyStore(storedCase($other, $id)['version'] === 2, 'record version advanced');
    denyStore(fn() => $other->mutate($admin['id'], 'assess', $id, array_replace($assessment, ['priority' => 'Urgent']), 1), 'stale update rejected', ConflictException::class);
    verifyStore(storedCase($store, $id)['priority'] === 'High', 'stale update did not overwrite');
    $store->mutate($admin['id'], 'assign', $id, ['team' => 'Sanitation team'], 2);
    denyStore(fn() => $store->mutate($wrongStaff['id'], 'start', $id, [], 3), 'wrong team cannot start');
    $store->mutate($staff['id'], 'start', $id, [], 3);
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6n8AAAAASUVORK5CYII=';
    $store->mutate($staff['id'], 'resolve', $id, ['notes' => 'Drain cleared and water flow checked.', 'photo' => $png], 4);
    $store->mutate($resident['id'], 'verify', $id, ['feedback' => 'Fixed, thank you.'], 5);
    $c = storedCase($other, $id);
    verifyStore($c['status'] === 'Verified' && $c['version'] === 6, 'saved full workflow');
    verifyStore($c['resolution']['photo'] === $png, 'image persisted in database');
    verifyStore(count($c['timeline']) === 6, 'timeline persisted');
    verifyStore($c['suggestion'] === $report['suggestion'], 'resident suggestion remains separate');
    $id2 = $other->mutate($resident2['id'], 'submit', '', $report, null);
    verifyStore($id2 === 'BR-2', 'IDs remain unique across connections');
    $before = $store->state();
    denyStore(fn() => $store->mutate($resident['id'], 'submit', '', array_replace($report, ['title' => ' ']), null), 'invalid report rejected');
    verifyStore($before === $store->state(), 'invalid report rolled back without consuming ID');
    $store->updateUser($admin['id'], $staff['id'], ['role' => 'personnel', 'team' => 'Sanitation team', 'active' => '0']);
    verifyStore($store->actor($staff['id']) === null, 'inactive account loses access');
    denyStore(fn() => $store->login($staff['email'], $password, 'inactive-client'), 'inactive login rejected');
    denyStore(fn() => $store->mutate($staff['id'], 'start', $id, [], 6), 'inactive account cannot mutate');
    $store->updateUser($admin['id'], $staff['id'], ['role' => 'personnel', 'team' => 'Maintenance crew', 'active' => '1']);
    verifyStore($store->actor($staff['id'])['team'] === 'Maintenance crew', 'reactivation and team change');
    denyStore(fn() => $store->updateProfile($resident['id'], ['name' => 'New Name', 'email' => $resident['email'], 'current_password' => 'bad']), 'profile requires current password');
    $newPassword = 'Updated-test-pass-82';
    $store->updateProfile($resident['id'], ['name' => 'Updated Resident', 'email' => 'updated@example.test', 'current_password' => $password, 'new_password' => $newPassword, 'confirm_new_password' => $newPassword]);
    verifyStore($store->user($resident['id'])['name'] === 'Updated Resident', 'profile name saved');
    verifyStore((int)$store->user($resident['id'])['auth_version'] === 2, 'password change revokes earlier session versions');
    denyStore(fn() => $store->login('updated@example.test', $password, 'old-pass-client'), 'old password no longer works');
    verifyStore($store->login('updated@example.test', $newPassword, 'new-pass-client')['id'] === $resident['id'], 'new credentials work');
    denyStore(fn() => $store->updateProfile($resident2['id'], ['name' => 'Other Resident', 'email' => 'updated@example.test', 'current_password' => $password]), 'duplicate profile email');
    for ($i = 0; $i < 5; $i++) denyStore(fn() => $store->login('absent@example.test', 'wrong', 'rate-test'), 'failed attempt recorded');
    denyStore(fn() => $store->login('absent@example.test', 'wrong', 'rate-test'), 'attempt limit enforced');
    verifyStore($store->needsSetup() === false, 'no test action resets first setup');
    $newOfficial = $store->createUser($admin['id'], ['name' => 'Additional Official', 'email' => 'additional-official@example.test', 'role' => 'official']);
    denyStore(fn() => $store->users($newOfficial['id']), 'new official cannot list accounts until password change');
    denyStore(fn() => $store->createUser($newOfficial['id'], $residentData), 'new official cannot create accounts until password change');
    $store->changeTemporaryPassword($newOfficial['id'], ['current_password' => $newOfficial['temporary_password'], 'password' => $password, 'confirm_password' => $password]);
    verifyStore(count($store->users($newOfficial['id'])) === 6, 'official gets management access after password change');
    $createdResident = $store->createUser($admin['id'], ['name' => 'Issued Resident', 'email' => 'issued-resident@example.test', 'role' => 'resident']);
    verifyStore($createdResident['must_change_password'] && $createdResident['role'] === 'resident', 'official can also issue resident account');
    echo "PASS: $checks account, access, persistence, transaction, concurrency, and password checks.\n";
} finally {
    unset($rawDb, $other, $store);
    $testDatabase->drop();
}
