<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
$test = new TestDatabase();
$checks = 0;
function adminCheck(bool $condition, string $label): void { global $checks; if (!$condition) throw new RuntimeException('FAIL: ' . $label); $checks++; }
function adminDenied(callable $operation, string $label): void { try { $operation(); } catch (DomainException) { adminCheck(true, $label); return; } throw new RuntimeException('FAIL: ' . $label); }
try {
    $store = new ComplaintStore($test->connect());
    $first = $store->setup(['name' => 'First Administrator', 'email' => 'first-admin@example.test', 'password' => 'Admin-password-42']);
    adminCheck($first['is_system_admin'], 'first official is administrator');
    $second = $store->createUser($first['id'], ['name' => 'Standard Official', 'email' => 'standard-official@example.test', 'role' => 'official']);
    $second = activateInvitedUser($store,$second,'Admin-password-42');
    adminCheck(!$second['is_system_admin'], 'new official receives operational role without administrator access');
    adminDenied(fn() => $store->users($second['id']), 'standard official cannot list accounts');
    adminDenied(fn() => $store->auditLogs($second['id']), 'standard official cannot read audit history');
    adminDenied(fn() => $store->generateBackup($second['id']), 'standard official cannot create backup');
    adminDenied(fn() => $store->createUser($second['id'], ['name' => 'Third Official', 'email' => 'third@example.test', 'role' => 'official']), 'standard official cannot create accounts');
    adminDenied(fn() => $store->updateUser($first['id'], $first['id'], ['role' => 'official', 'active' => '1', 'isAdmin' => '0']), 'administrator cannot remove own permission');
    $store->updateUser($first['id'], $second['id'], ['role' => 'official', 'active' => '1', 'isAdmin' => '1']);
    adminCheck($store->actor($second['id'])['is_system_admin'], 'administrator grant takes effect');
    $store->updateUser($second['id'], $first['id'], ['role' => 'official', 'active' => '0']);
    adminCheck($store->actor($first['id']) === null && count($store->users($second['id'])) === 2, 'second administrator preserves access after first deactivation');
    adminDenied(fn() => $store->updateUser($second['id'], $second['id'], ['role' => 'official', 'active' => '1', 'isAdmin' => '0']), 'last administrator cannot self-demote');
    echo "PASS: $checks administrator separation, grant and continuity checks.\n";
} finally { $test->drop(); }
