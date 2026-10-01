<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';

$test=new TestDatabase(); $checks=0;
function messagingCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function messagingDenied(callable $call, string $label): void
{
    try { $call(); } catch (DomainException $error) { messagingCheck(true,$label); return; }
    throw new RuntimeException('FAIL: expected denial: '.$label);
}
try {
    $store=new ComplaintStore($test->connect());
    $official=$store->setup(['name'=>'Messaging Official','email'=>'messaging-official@example.test','password'=>'Official-password-42']);
    $secondOfficial=$store->createUser($official['id'],['name'=>'Second Official','email'=>'messaging-official-2@example.test','role'=>'official']);
    $staff=$store->createUser($official['id'],['name'=>'Messaging Worker','email'=>'messaging-worker@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $otherStaff=$store->createUser($official['id'],['name'=>'Other Worker','email'=>'messaging-worker-2@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $otherTeam=$store->createUser($official['id'],['name'=>'Different Team Worker','email'=>'messaging-worker-3@example.test','role'=>'personnel','team'=>'Sanitation team']);
    foreach ([$secondOfficial,$staff,$otherStaff,$otherTeam] as $person) $store->changeTemporaryPassword($person['id'],['current_password'=>$person['temporary_password'],'password'=>'Staff-password-42','confirm_password'=>'Staff-password-42']);
    $resident=$store->register(['name'=>'Messaging Resident','email'=>'messaging-resident@example.test','password'=>'Resident-password-42']);
    $otherResident=$store->register(['name'=>'Other Resident','email'=>'messaging-resident-2@example.test','password'=>'Resident-password-42']);
    $report=['category'=>'Street Lighting','concernType'=>'Light not working','keyPoints'=>['Completely dark'],'purok'=>'Test Purok','street'=>'Test Street','exactArea'=>'Test gate'];
    $own=$store->submitAccount($resident['id'],$report);
    $other=$store->submitAccount($otherResident['id'],$report);
    $guest=$store->submitGuest($report,'messaging-guest');
    messagingDenied(fn()=>$store->concernConversation($resident['id'],$other),'resident cannot view another concern');
    messagingDenied(fn()=>$store->sendConcernMessage($otherStaff['id'],$own,['body'=>'Unauthorized']),'unassigned personnel cannot reply');
    messagingDenied(fn()=>$store->sendConcernMessage($resident['id'],$own,['visibility'=>'staff','body'=>'Private']),'resident cannot write internal note');
    messagingDenied(fn()=>$store->sendConcernMessage($resident['id'],$own,['body'=>'   ']),'empty message rejected');
    messagingDenied(fn()=>$store->sendConcernMessage($resident['id'],$own,['body'=>str_repeat('x',2001)]),'oversized message rejected');
    $store->sendConcernMessage($resident['id'],$own,['body'=>'<script>alert(1)</script>']);
    $store->sendConcernMessage($official['id'],$own,['visibility'=>'staff','body'=>'Internal coordination']);
    $store->sendConcernMessage($official['id'],$own,['body'=>'Please confirm the light.']);
    messagingCheck($store->chatOverview($resident['id'])['counts']['concerns']===1 && $store->chatOverview($resident['id'])['counts']['concerns']===1,'listing chat does not mark concern read');
    messagingCheck($store->concernConversation($resident['id'],$own,0,false)['unread']===1 && $store->messageCounts($resident['id'])['concerns']===1,'rendering concern before viewing conversation preserves unread');
    $residentPage=$store->concernConversation($resident['id'],$own);
    messagingCheck(count($residentPage['items'])===2 && !str_contains(json_encode($residentPage),'Internal coordination'),'reporter cannot read internal note');
    messagingCheck(str_contains(json_encode($residentPage),'<script>'),'message stays plain text for escaped rendering');
    messagingCheck(count($store->concernConversation($official['id'],$own)['items'])===3,'official sees full concern history');
    $residentOverview=$store->chatOverview($resident['id']);
    messagingCheck(count($residentOverview['staff'])===0 && count($residentOverview['concerns'])===1 && !str_contains(json_encode($residentOverview['concerns']),'Internal coordination'),'resident chat list excludes staff chat and internal notes');
    messagingCheck(count($store->chatOverview($resident['id'],$own)['concerns'])===1 && $store->chatOverview($resident['id'],$other)['concerns']===[],'resident search stays within own concerns');
    messagingCheck($store->chatOverview($resident['id'],"' OR 1=1 --")['concerns']===[],'search text cannot change concern authorization query');
    messagingCheck($store->chatOverview($resident['id'],'Messaging Worker')['people']===[],'resident search cannot enumerate staff');
    $newContact=$store->chatOverview($official['id'],'Other Worker')['people'];
    messagingCheck(count($newContact)===1 && $newContact[0]['id']===$otherStaff['id'] && $store->chatOverview($official['id'],'Other Worker')['staff']===[],'floating search finds staff with no conversation');
    messagingCheck($store->chatOverview($staff['id'],'Other Worker')['people'][0]['id']===$otherStaff['id'],'personnel can find same-team staff');
    messagingCheck($store->chatOverview($staff['id'],'Different Team Worker')['people']===[],'personnel cannot find other-team personnel');
    messagingCheck($store->chatOverview($staff['id'],'Second Official')['people'][0]['id']===$secondOfficial['id'],'personnel can find official with no conversation');
    messagingCheck(count($store->chatOverview($official['id'])['concerns'])===3,'official chat list includes authorized concerns');
    messagingCheck($store->messageCounts($resident['id'])['concerns']===0,'reading messages clears reporter unread');
    $store->sendGuestMessage(['reference'=>$guest['reference'],'trackingCode'=>$guest['trackingCode'],'body'=>'Guest reply'],'messaging-guest');
    $store->sendConcernMessage($official['id'],$guest['reference'],['visibility'=>'staff','body'=>'Guest-private staff note']);
    $store->sendConcernMessage($official['id'],$guest['reference'],['body'=>'We will inspect it.']);
    messagingCheck($store->guestChatStatus($guest['reference'],$guest['trackingCode'],'messaging-guest')['unread']===1,'guest status shows unread without opening thread');
    $guestPage=$store->guestConversation($guest['reference'],$guest['trackingCode'],'messaging-guest');
    messagingCheck(count($guestPage['items'])===2 && $guestPage['unread']===1,'guest sees own and staff replies with unread count');
    messagingCheck(!str_contains(json_encode($guestPage),'Guest-private staff note'),'guest cannot read internal notes');
    messagingCheck($store->guestChatStatus($guest['reference'],$guest['trackingCode'],'messaging-guest')['unread']===0,'guest opening thread clears unread');
    messagingDenied(fn()=>$store->guestConversation($guest['reference'],str_repeat('0',48),'messaging-guest'),'wrong guest code denied');
    messagingDenied(fn()=>$store->guestConversation($own,$guest['trackingCode'],'messaging-guest'),'tracking code bound to concern');
    messagingDenied(fn()=>$store->staffInbox($resident['id']),'resident cannot access staff inbox');
    messagingDenied(fn()=>$store->createStaffConversation($resident['id'],['recipientId'=>$staff['id']]),'resident cannot create staff chat');
    $direct=$store->createStaffConversation($official['id'],['recipientId'=>$staff['id'],'title'=>'Drainage coordination']);
    $staffChat=$store->createStaffConversation($staff['id'],['recipientId'=>$otherStaff['id'],'title'=>'Crew work']);
    $officialChat=$store->createStaffConversation($official['id'],['recipientId'=>$secondOfficial['id'],'title'=>'Official work']);
    messagingCheck($direct>0 && $staffChat>0 && $officialChat>0,'supported staff pairings created');
    messagingCheck($store->createStaffConversation($official['id'],['recipientId'=>$staff['id']])===$direct,'selecting a contact reuses existing direct conversation');
    messagingCheck(count($store->staffInbox($staff['id'],'Drainage coordination'))===1 && count($store->staffInbox($staff['id'],'Second Official'))===0,'staff search matches title without exposing other conversations');
    messagingCheck(count($store->chatOverview($official['id'],'Messaging Worker')['staff'])>=1,'floating search matches staff participant');
    messagingCheck($store->staffInbox($staff['id'],"' OR 1=1 --")===[],'search text cannot change staff membership query');
    messagingCheck(($store->staffInbox($staff['id'])[0]['id'] ?? null)===$staffChat || ($store->staffInbox($staff['id'])[1]['id'] ?? null)===$staffChat,'staff inbox contains conversation ID');
    $store->sendStaffMessage($official['id'],$direct,['body'=>'Bring the equipment.']);
    messagingCheck($store->messageCounts($staff['id'])['staff']===1,'recipient staff unread count');
    messagingCheck($store->chatOverview($staff['id'])['counts']['staff']===1,'opening staff list preserves unread');
    messagingCheck(count($store->staffConversationPage($staff['id'],$direct)['items'])===1,'staff member reads conversation');
    messagingCheck($store->messageCounts($staff['id'])['staff']===0,'staff read marker clears unread');
    messagingDenied(fn()=>$store->staffConversationPage($otherStaff['id'],$direct),'unrelated staff cannot read direct conversation');
    messagingDenied(fn()=>$store->sendStaffMessage($resident['id'],$direct,['body'=>'Intrusion']),'resident cannot send staff message');
    messagingDenied(fn()=>$store->sendStaffMessage($otherStaff['id'],$direct,['body'=>'Intrusion']),'nonmember cannot send staff message');
    messagingDenied(fn()=>$store->createStaffConversation($staff['id'],['recipientId'=>$official['id'],'concernId'=>$other]),'unassigned personnel cannot link unrelated concern');
    $store->mutate($official['id'],'assess',$own,['priority'=>'Medium','recommendation'=>'Inspect the street light'],1);
    $store->mutate($official['id'],'exception',$own,['status'=>'Rejected','notes'=>'Duplicate report'],2);
    messagingCheck(!$store->concernConversation($resident['id'],$own)['canSend'],'rejected concern becomes read-only');
    messagingDenied(fn()=>$store->sendConcernMessage($resident['id'],$own,['body'=>'After closure']),'closed concern blocks reporter reply');
    $store->mutate($official['id'],'reopen',$own,['feedback'=>'New information needs review'],3);
    $reopened=$store->concernConversation($resident['id'],$own);
    messagingCheck($reopened['canSend'] && str_contains(json_encode($reopened['items']),'Concern reopened.'),'reopened concern resumes messaging with system event');
    $store->mutate($official['id'],'assess',$own,['priority'=>'Medium','recommendation'=>'Inspect again'],4);
    $store->mutate($official['id'],'assign',$own,['personnelId'=>$staff['id']],5);
    $store->sendConcernMessage($staff['id'],$own,['body'=>'Assigned personnel update']);
    messagingCheck(str_contains(json_encode($store->concernConversation($resident['id'],$own)['items']),'Assigned personnel update'),'assigned personnel can reply');
    $linkedChat=$store->createStaffConversation($official['id'],['recipientId'=>$staff['id'],'concernId'=>$own]);
    messagingCheck((int)$store->staffConversationPage($staff['id'],$linkedChat)['conversation']['id']===$linkedChat,'assigned personnel can join work-linked staff chat');
    $store->mutate($official['id'],'assign',$own,['personnelId'=>$otherStaff['id']],6);
    messagingDenied(fn()=>$store->staffConversationPage($staff['id'],$linkedChat),'reassignment revokes linked staff chat');
    messagingCheck(!in_array($linkedChat,array_column($store->staffInbox($staff['id']),'id')),'revoked linked chat absent from inbox');
    messagingDenied(fn()=>$store->sendConcernMessage($staff['id'],$own,['body'=>'Old assignment']),'former assignee cannot reply');
    for ($i=0;$i<11;$i++) $store->sendGuestMessage(['reference'=>$guest['reference'],'trackingCode'=>$guest['trackingCode'],'body'=>'Update '.($i+1)],'messaging-guest');
    messagingDenied(fn()=>$store->sendGuestMessage(['reference'=>$guest['reference'],'trackingCode'=>$guest['trackingCode'],'body'=>'One too many'],'messaging-guest'),'guest message rate limit');
    $database=new MaintainProDatabase($test->connect());
    for ($i=0;$i<52;$i++) $database->insertConcernMessage($own,null,'system','MaintainPro','reporter','Historical event '.$i);
    $recent=$store->concernConversation($resident['id'],$own)['items'];
    $older=$store->olderConcernConversation($resident['id'],$own,(int)$recent[0]['id'])['items'];
    messagingCheck(count($recent)===50 && count($older)>0 && (int)end($older)['id']<(int)$recent[0]['id'],'older concern messages page backward without overlap');
    $test->assertHealthyLog();
    echo "PASS: $checks messaging authorization, visibility, guest, unread and staff coordination checks.\n";
} finally { $test->drop(); }
