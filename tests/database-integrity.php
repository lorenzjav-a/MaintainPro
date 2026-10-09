<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';

$test=new TestDatabase();
$checks=0;
function integrityCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function integrityStops(callable $operation, string $fragment): void
{
    try { $operation(); }
    catch (RuntimeException $error) {
        integrityCheck(str_contains($error->getMessage(),$fragment),'preflight reports '.$fragment);
        return;
    }
    throw new RuntimeException('FAIL: repair accepted '.$fragment);
}

try {
    $db=$test->connect();
    $fixture=new DatabaseTestFixtures($db);
    $store=new ComplaintStore($db);
    $official=$store->setup(['name'=>'Integrity Official','email'=>'integrity-official@example.test','password'=>'Integrity-pass-42']);
    $resident=$store->register(['name'=>'Integrity Resident','email'=>'integrity-resident@example.test','password'=>'Integrity-pass-42']);
    $report=['category'=>'Street Lighting','concernType'=>'Exposed wiring','keyPoints'=>['Sparks visible'],'purok'=>'Purok One','street'=>'Test Road','exactArea'=>'Test corner'];
    $own=$store->submitAccount($resident['id'],$report);
    $guest=$store->submitGuest($report,'integrity-guest');
    integrityCheck($fixture->integrityCounts()===[40,0],'fresh schema has all foreign keys and standard collations');
    integrityCheck($store->concernForActor($resident['id'],$own)!==null && $store->track($guest['reference'],$guest['trackingCode'],'integrity')['reference']===$guest['reference'],'account and guest concerns work');

    // A dump can mark a migration complete while leaving constraints absent.
    $fixture->dropIntegrityForeignKey('notifications','user_id');
    $fixture->orphanNotification();
    integrityStops(fn()=>DatabaseMaintenance::initialize($db),'orphan reference');
    $fixture->removeOrphanNotification();
    DatabaseMaintenance::initialize($db);
    integrityCheck($fixture->integrityCounts()[0]===40,'rerun restores a missing notification foreign key');

    $fixture->invalidSolutionJson();
    integrityStops(fn()=>DatabaseMaintenance::initialize($db),'invalid JSON');
    $fixture->removeInvalidSolutionJson();
    DatabaseMaintenance::initialize($db);
    integrityCheck($fixture->integrityCounts()[0]===40,'rerun restores a missing JSON check without deleting data');

    $before=$fixture->dataChecksums();
    $fixture->useLegacyRuleCollations();
    integrityCheck($fixture->integrityCounts()[1]>0,'legacy general_ci columns reproduced');
    DatabaseMaintenance::initialize($db);
    integrityCheck($fixture->integrityCounts()===[40,0],'mixed collations and missing rule foreign keys repaired');
    integrityCheck($fixture->dataChecksums()===$before,'account, concern, evidence, notification and rule data preserved');
    DatabaseMaintenance::initialize($db);
    integrityCheck($fixture->integrityCounts()===[40,0],'completed repair is repeatable');
} finally {
    $test->drop();
}

// A partial legacy import may contain only a general_ci users table. Bootstrap
// must normalize it before creating dependent tables with foreign keys.
$legacyName='maintainpro_test_'.bin2hex(random_bytes(8));
DatabaseMaintenance::create($legacyName);
try {
    $legacy=br_database($legacyName);
    $legacyFixture=new DatabaseTestFixtures($legacy);
    $legacyFixture->partialLegacyUsers();
    DatabaseMaintenance::initialize($legacy);
    integrityCheck($legacyFixture->integrityCounts()===[40,0],'partial legacy import completes with standard collation and foreign keys');
    integrityCheck($legacyFixture->legacyUserName()==='Legacy User','partial legacy import preserves account data');
} finally {
    DatabaseMaintenance::dropTestDatabase($legacyName);
}
echo "PASS: $checks database integrity, orphan, JSON, legacy collation, partial import and rerun checks.\n";
