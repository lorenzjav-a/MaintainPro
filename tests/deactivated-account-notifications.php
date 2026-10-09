<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';

$test = new TestDatabase();
$checks = 0;
function deactivationCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}

try {
    $store = new ComplaintStore($test->connect());
    $fixtures = new DatabaseTestFixtures($test->connect());
    $first = $store->setup(['name'=>'First Administrator','email'=>'first-deactivation-admin@example.test','password'=>'Admin-password-42']);
    $second = $store->createUser($first['id'], ['name'=>'Second Administrator','email'=>'second-deactivation-admin@example.test','role'=>'official']);
    $second = activateInvitedUser($store,$second,'Admin-password-42');
    $store->updateUser($first['id'], $second['id'], ['role'=>'official','active'=>'1','isAdmin'=>'1']);
    $standard = $store->createUser($first['id'], ['name'=>'Standard Official','email'=>'standard-deactivation-official@example.test','role'=>'official']);
    $standard = activateInvitedUser($store,$standard,'Admin-password-42');
    $target = $store->createUser($first['id'], ['name'=>'Deactivated Resident','email'=>'deactivated-resident@example.test','role'=>'resident']);
    $target = activateInvitedUser($store,$target,'Resident-password-42');

    $store->updateUser($first['id'], $target['id'], ['role'=>'resident','active'=>'0']);
    $target = $store->user($target['id']);
    deactivationCheck($target !== null && !$target['active'] && is_int($target['deactivated_at']), 'deactivation records exact time');
    deactivationCheck($target['deactivated_by'] === $first['id'] && $target['deactivation_sequence'] === 1, 'deactivation records administrator and period');
    $targetUrl = 'user-edit.php?id=' . rawurlencode($target['id']);
    $store->sweepDeactivatedAccounts($target['deactivated_at'] + 7 * 86400 - 1);
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $targetUrl) === 0, 'notification waits for seven complete days');

    $store->sweepDeactivatedAccounts($target['deactivated_at'] + 7 * 86400);
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $targetUrl) === 1 && $fixtures->notificationCountForTarget($second['id'], $targetUrl) === 1, 'all active system administrators notified at seven days');
    deactivationCheck($fixtures->notificationCountForTarget($standard['id'], $targetUrl) === 0, 'standard official does not receive administrator alert');
    $notice = array_values(array_filter($store->notifications($first['id'])['items'], fn(array $item): bool => $item['target_url'] === $targetUrl))[0] ?? null;
    deactivationCheck($notice !== null && $notice['title'] === 'Account Deactivated for 7 Days' && str_contains($notice['message'], 'Deactivated Resident') && str_contains($notice['message'], 'Resident') && str_contains($notice['message'], '7 full days'), 'notification identifies account, role and inactive duration');
    $store->sweepDeactivatedAccounts($target['deactivated_at'] + 10 * 86400);
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $targetUrl) === 1, 'repeated sweeps are idempotent');

    $store->updateUser($first['id'], $target['id'], ['role'=>'resident','active'=>'1']);
    $reactivated = $store->user($target['id']);
    deactivationCheck($reactivated['active'] && $reactivated['deactivated_at'] === null && $reactivated['deactivated_by'] === null, 'reactivation clears current deactivation state');
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $targetUrl) === 1, 'reactivation preserves historical notification');

    $store->updateUser($first['id'], $second['id'], ['role'=>'official','active'=>'0','isAdmin'=>'1']);
    $store->updateUser($first['id'], $target['id'], ['role'=>'resident','active'=>'0']);
    $secondPeriod = $store->user($target['id']);
    deactivationCheck($secondPeriod['deactivation_sequence'] === 2, 'new deactivation creates a distinct period');
    $store->sweepDeactivatedAccounts($secondPeriod['deactivated_at'] + 7 * 86400);
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $targetUrl) === 2, 'reactivation then deactivation creates one new alert');
    deactivationCheck($fixtures->notificationCountForTarget($second['id'], $targetUrl) === 1, 'inactive administrator receives no later alert');

    $legacy = $store->createUser($first['id'], ['name'=>'Legacy Inactive Resident','email'=>'legacy-inactive@example.test','role'=>'resident']);
    $legacyUrl = 'user-edit.php?id=' . rawurlencode($legacy['id']);
    $fixtures->makeLegacyInactive($legacy['id']);
    $legacy = $store->user($legacy['id']);
    deactivationCheck(!$legacy['active'] && $legacy['deactivated_at'] === null, 'legacy inactive account keeps unknown date');
    $store->sweepDeactivatedAccounts(time() + 30 * 86400);
    deactivationCheck($fixtures->notificationCountForTarget($first['id'], $legacyUrl) === 0, 'legacy unknown date is excluded from automatic alerts');

    echo "PASS: $checks deactivation timing, recipient, deduplication, lifecycle and legacy checks.\n";
} finally {
    $test->drop();
}
