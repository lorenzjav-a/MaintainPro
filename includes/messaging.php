<?php
declare(strict_types=1);

// Message permissions stay with the existing account, concern and tracking guards.
trait ConcernMessaging
{
    private function messagingActor(string $id, bool $staffOnly = false): array
    {
        $actor=$this->actor($id);
        if (!$actor || $actor['must_change_password'] || ($staffOnly && !in_array($actor['role'],['official','personnel'],true))) {
            throw new DomainException('This conversation is unavailable to your account.');
        }
        return $actor;
    }

    private static function messageBody(mixed $body): string
    {
        return ComplaintWorkflow::text($body,'Message',2000);
    }

    private static function conversationOpen(array $concern): bool
    {
        return !in_array($concern['status'],['Verified','Rejected','Referred to Another Office','Linked to Primary'],true);
    }

    private function concernMessageAccess(string $userId, string $id, bool $lock = false): array
    {
        $actor=$this->messagingActor($userId);
        $concern=$this->concern($id,$lock);
        if (!$concern || !ComplaintWorkflow::canSee($concern,$actor)) throw new DomainException('Concern conversation unavailable.');
        $staff=$actor['role']==='official' || ($actor['role']==='personnel' && ($concern['assignedUserId'] ?? null)===$userId);
        return [$actor,$concern,$staff];
    }

    private function trackedMessageConcern(mixed $reference, mixed $token, bool $lock = false): array
    {
        if (!is_string($reference) || !preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/',$reference)
            || !is_string($token) || !preg_match('/\A[a-f0-9]{48}\z/',$token)) throw new DomainException('Reference or tracking code not found.');
        $row=$this->db->trackedConcern($reference,hash('sha256',$token),$lock);
        if (!$row) throw new DomainException('Reference or tracking code not found.');
        return self::decodeConcern($row);
    }

    private function concernMessagePage(string $id, string $readerKey, bool $staff, ?string $readerId, int $after, bool $guest = false, bool $markRead = true): array
    {
        $after=max(0,$after);
        $unread=$this->db->concernUnread($id,$readerKey,$staff,$readerId,$guest);
        $items=$this->db->concernMessages($id,$staff,$after);
        $last=$items ? (int)$items[array_key_last($items)]['id'] : $after;
        if ($markRead && $last>0) $this->db->markConcernRead($id,$readerKey,$last);
        return ['items'=>$items,'unread'=>$unread,'lastId'=>$last];
    }

    public function concernConversation(string $userId, string $id, int $after = 0, bool $markRead = true): array
    {
        [$actor,$concern,$staff]=$this->concernMessageAccess($userId,$id);
        $page=$this->concernMessagePage($id,'user:'.$actor['id'],$staff,$actor['id'],$after,false,$markRead);
        if ($markRead) $this->db->markChatNotificationsRead($userId,'concern',$id);
        return $page
            + ['canSend'=>self::conversationOpen($concern),'canWriteInternal'=>$staff];
    }

    public function olderConcernConversation(string $userId, string $id, int $before): array
    {
        [,,$staff]=$this->concernMessageAccess($userId,$id);
        return ['items'=>$this->db->olderConcernMessages($id,$staff,$before)];
    }

    public function guestConversation(mixed $reference, mixed $token, string $client, int $after = 0): array
    {
        $this->publicLimit('message_poll',$client);
        $concern=$this->trackedMessageConcern($reference,$token);
        $page=$this->concernMessagePage($concern['id'],'guest',false,null,$after,true);
        $page['remainingUnread']=$this->db->concernUnread($concern['id'],'guest',false,null,true);
        return $page + ['canSend'=>self::conversationOpen($concern)];
    }

    public function olderGuestConversation(mixed $reference, mixed $token, string $client, int $before): array
    {
        $this->publicLimit('message_poll',$client);
        $concern=$this->trackedMessageConcern($reference,$token);
        return ['items'=>$this->db->olderConcernMessages($concern['id'],false,$before)];
    }

    public function guestChatStatus(mixed $reference, mixed $token, string $client): array
    {
        $this->publicLimit('message_poll',$client);
        $concern=$this->trackedMessageConcern($reference,$token);
        return ['unread'=>$this->db->concernUnread($concern['id'],'guest',false,null,true),'canSend'=>self::conversationOpen($concern)];
    }

    private function notifyConcernMessage(array $concern, int $messageId, ?string $senderId, string $visibility, bool $reporterSent): void
    {
        $id=$concern['id'];
        $target='concern.php?id='.rawurlencode($id).'&open_chat=1#conversation';
        $recipients=$this->db->officialIds();
        if (!empty($concern['assignedUserId'])) $recipients[]=$concern['assignedUserId'];
        if ($visibility==='reporter' && !$reporterSent && !empty($concern['residentId'])) $recipients[]=$concern['residentId'];
        foreach (array_unique($recipients) as $recipient) {
            if ($recipient===$senderId) continue;
            $title=$visibility==='staff' ? 'New internal note on '.$id : 'New message on '.$id;
            $description=$visibility==='staff'?'A staff member added an internal note.':($reporterSent?'A reporter replied to the concern.':'You received a new message regarding your concern.');
            $this->db->createMessageNotification($recipient,'concern_message',$title,$description,$target,'concern-message:'.$messageId.':'.$recipient,$id);
        }
    }

    public function sendConcernMessage(string $userId, string $id, array $data): array
    {
        $actor=$this->messagingActor($userId);
        $this->publicLimit('staff_message',$actor['id']);
        return $this->transaction(function() use($userId,$id,$data) {
            [$actor,$concern,$staff]=$this->concernMessageAccess($userId,$id,true);
            if (!self::conversationOpen($concern)) throw new DomainException('This concern is closed. Messages are read-only until an official reopens it.');
            $visibility=$data['visibility'] ?? 'reporter';
            if (!in_array($visibility,['reporter','staff'],true) || (!$staff && $visibility!=='reporter')) throw new DomainException('Choose a permitted message type.');
            $body=self::messageBody($data['body'] ?? null);
            $name=($concern['isAnonymous'] ?? false) && !$staff ? 'Anonymous reporter' : $actor['name'];
            $messageId=$this->db->insertConcernMessage($id,$actor['id'],$actor['role'],$name,$visibility,$body);
            if ($visibility==='staff') $this->db->recordAudit($actor,'concern_internal_note_created','concern',$id,$id,['messageId'=>$messageId]);
            $this->notifyConcernMessage($concern,$messageId,$actor['id'],$visibility,!$staff);
            $this->db->markConcernRead($id,'user:'.$actor['id'],$messageId);
            return [
                'id'=>$messageId,
                'sender_name'=>$name,
                'sender_role'=>$actor['role'],
                'visibility'=>$visibility,
                'body'=>$body,
                'created_at'=>time(),
            ];
        });
    }

    public function sendGuestMessage(array $data, string $client): array
    {
        $this->publicLimit('guest_message',$client);
        return $this->transaction(function() use($data) {
            $concern=$this->trackedMessageConcern($data['reference'] ?? null,$data['trackingCode'] ?? null,true);
            if (!self::conversationOpen($concern)) throw new DomainException('This concern is closed. Messages are read-only until an official reopens it.');
            $body=self::messageBody($data['body'] ?? null);
            $id=$this->db->insertConcernMessage($concern['id'],null,'guest','Guest reporter','reporter',$body);
            $this->notifyConcernMessage($concern,$id,null,'reporter',true);
            $this->db->markConcernRead($concern['id'],'guest',$id);
            return [
                'id'=>$id,
                'sender_name'=>'Guest reporter',
                'sender_role'=>'guest',
                'visibility'=>'reporter',
                'body'=>$body,
                'created_at'=>time(),
            ];
        });
    }

    public function messageCounts(string $userId): array
    {
        $actor=$this->messagingActor($userId);
        return ['concerns'=>$this->db->concernUnreadTotal($actor),
            'staff'=>in_array($actor['role'],['official','personnel'],true)?$this->db->staffUnreadTotal($userId,$actor['role']):0];
    }

    public function chatOverview(string $userId, string $query = ''): array
    {
        $actor=$this->messagingActor($userId);
        $query=trim($query);
        if (mb_strlen($query)>100) throw new DomainException('Search is too long.');
        $concerns=$this->db->concernChatList($actor,$query);
        $staff=in_array($actor['role'],['official','personnel'],true)?$this->db->staffConversations($userId,$actor['role'],$query):[];
        $people=$query!=='' && in_array($actor['role'],['official','personnel'],true)?$this->staffContacts($userId,$query):[];
        return ['concerns'=>$concerns,'staff'=>$staff,'people'=>$people,'counts'=>$this->messageCounts($userId),'notifications'=>$this->notifications($userId)];
    }

    public function staffContacts(string $userId, string $query = ''): array
    {
        $actor=$this->messagingActor($userId,true);
        $query=trim($query);
        if (mb_strlen($query)>100) throw new DomainException('Search is too long.');
        return $this->db->staffUsers($actor,$query);
    }

    private function staffConversationAccess(string $userId, int $id): array
    {
        $actor=$this->messagingActor($userId,true);
        $conversation=$this->db->staffConversation($id,$userId);
        if (!$conversation) throw new DomainException('Staff conversation unavailable.');
        if ($conversation['related_concern_id']!==null) {
            $concern=$this->concern($conversation['related_concern_id']);
            if (!$concern || !($actor['role']==='official' || ($concern['assignedUserId'] ?? null)===$userId)) throw new DomainException('Staff conversation unavailable.');
        }
        if ($conversation['related_action_plan_id']!==null) {
            $plan=$this->db->actionPlan((int)$conversation['related_action_plan_id']);
            if (!$plan || !($actor['role']==='official' || $plan['assigned_user_id']===$userId)) throw new DomainException('Staff conversation unavailable.');
        }
        return [$actor,$conversation];
    }

    public function staffInbox(string $userId, string $query = ''): array
    {
        $actor=$this->messagingActor($userId,true);
        $query=trim($query);
        if (mb_strlen($query)>100) throw new DomainException('Search is too long.');
        return $this->db->staffConversations($userId,$actor['role'],$query);
    }

    public function staffConversationPage(string $userId, int $id, int $after = 0): array
    {
        [$actor,$conversation]=$this->staffConversationAccess($userId,$id);
        $items=$this->db->staffMessages($id,max(0,$after));
        $last=$items ? (int)$items[array_key_last($items)]['id'] : max(0,$after);
        if ($last>0) $this->db->markStaffRead($id,$userId,$last);
        $this->db->markChatNotificationsRead($userId,'staff',(string)$id);
        return ['conversation'=>$conversation,'members'=>$this->db->staffConversationMembers($id),'items'=>$items,'lastId'=>$last];
    }

    public function olderStaffConversation(string $userId, int $id, int $before): array
    {
        $this->staffConversationAccess($userId,$id);
        return ['items'=>$this->db->olderStaffMessages($id,$before)];
    }

    public function createStaffConversation(string $userId, array $data): int
    {
        return $this->transaction(function() use($userId,$data) {
            $actor=$this->messagingActor($userId,true);
            $recipientId=$data['recipientId'] ?? null;
            if (!is_string($recipientId) || $recipientId===$userId || $recipientId==='' || strlen($recipientId)>64) throw new DomainException('Choose another staff member.');
            $this->db->lockedUser($recipientId);
            $recipient=$this->messagingActor($recipientId,true);
            if ($actor['role']==='personnel' && $recipient['role']==='personnel' && $actor['team']!==$recipient['team']) throw new DomainException('Personnel can message another personnel member on the same team.');
            $concernId=$data['concernId'] ?? '';
            $planId=$data['planId'] ?? '';
            if ($concernId!=='' && $planId!=='') throw new DomainException('Choose one work item for this conversation.');
            $type='direct'; $concern=null; $plan=null;
            if ($concernId!=='') {
                if (!is_string($concernId) || !preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/',$concernId)) throw new DomainException('Choose a valid concern.');
                $c=$this->concern($concernId);
                if (!$c || !($actor['role']==='official' || ($c['assignedUserId'] ?? null)===$userId)
                    || !($recipient['role']==='official' || ($c['assignedUserId'] ?? null)===$recipientId)) throw new DomainException('Both staff members must be authorized for this concern.');
                $type='concern'; $concern=$concernId;
            }
            if ($planId!=='') {
                $plan=filter_var($planId,FILTER_VALIDATE_INT);
                $p=$plan ? $this->db->actionPlan((int)$plan) : null;
                if (!$p || !($actor['role']==='official' || $p['assigned_user_id']===$userId)
                    || !($recipient['role']==='official' || $p['assigned_user_id']===$recipientId)) throw new DomainException('Both staff members must be authorized for this action plan.');
                $type='action_plan';
            }
            $requestedTitle=$data['title'] ?? '';
            if ($type==='direct' && is_string($requestedTitle) && trim($requestedTitle)==='') {
                $existing=$this->db->directStaffConversation($userId,$recipientId);
                if ($existing!==null) return $existing;
            }
            $title=ComplaintWorkflow::text($data['title'] ?? '', 'Conversation title',180,false);
            if ($title==='') $title=$type==='concern'?'Concern '.$concern.' coordination':($type==='action_plan'?'Action plan #'.$plan.' coordination':'Work coordination with '.$recipient['name']);
            $id=$this->db->createStaffConversation($type,$concern,$type==='action_plan'?(int)$plan:null,$title,$userId);
            $this->db->addStaffMember($id,$userId);
            $this->db->addStaffMember($id,$recipientId);
            $this->db->recordAudit($actor,'staff_conversation_created','staff_conversation',(string)$id,$title,['recipientId'=>$recipientId,'contextType'=>$type]);
            $this->db->createMessageNotification($recipientId,'staff_message','New staff conversation','Open Messages to coordinate work.','messages.php?id='.$id.'&open_chat=1','staff-conversation:'.$id.':'.$recipientId,$concern);
            return $id;
        });
    }

    public function sendStaffMessage(string $userId, int $conversationId, array $data): array
    {
        $actor=$this->messagingActor($userId,true);
        $this->publicLimit('staff_message',$actor['id']);
        return $this->transaction(function() use($userId,$conversationId,$data) {
            [$actor,$conversation]=$this->staffConversationAccess($userId,$conversationId);
            $body=self::messageBody($data['body'] ?? null);
            $id=$this->db->insertStaffMessage($conversationId,$actor,$body);
            $this->db->markStaffRead($conversationId,$userId,$id);
            foreach ($this->db->staffConversationMembers($conversationId) as $member) {
                if ($member['id']===$userId || !$member['active']) continue;
                $this->db->createMessageNotification($member['id'],'staff_message','New message from '.$actor['name'],'You received a new staff message.','messages.php?id='.$conversationId.'&open_chat=1','staff-message:'.$id.':'.$member['id'],$conversation['related_concern_id']);
            }
            return [
                'id'=>$id,
                'sender_name'=>$actor['name'],
                'sender_role'=>$actor['role'],
                'visibility'=>'staff',
                'body'=>$body,
                'created_at'=>time(),
            ];
        });
    }
}
