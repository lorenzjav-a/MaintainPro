<?php
declare(strict_types=1);
require_once __DIR__ . '/insights.php';

// All events are written in the same transaction as the concern change.
final class ConcernNotifications
{
    public function __construct(private MaintainProDatabase $db) {}

    private function officials(array $c, string $type, string $title, string $message, string $key): void
    {
        foreach ($this->db->officialIds() as $user) $this->db->createNotification($user,$type,$title,$message,$c['id'],$key);
    }

    public function changed(array $c, ?array $before, string $action): void
    {
        $key = $c['id'] . ':' . $c['version'] . ':' . $action;
        $id = $c['id'];
        $assigned = $c['assignedUserId'] ?? null;
        $previous = $before['assignedUserId'] ?? null;
        if ($action === 'submit') {
            $priority = $c['priorityRecommendation']['priority'];
            $title = in_array($priority,['High','Urgent'],true) ? 'New concern: ' . $priority . ' recommended' : 'New concern submitted';
            $this->officials($c,'new_concern',$title,$id . ' is ready for assessment. Recommended priority: ' . $priority . '.',$key);
        }
        if ($action === 'assign') {
            $this->db->createNotification($assigned,'assignment','Work assignment',$id . ' has been assigned to you. Review the official instructions.',$id,$key);
            if ($previous && $previous !== $assigned) $this->db->createNotification($previous,'reassignment','Assignment changed',$id . ' has been reassigned. Your other tasks remain in the work queue.',$id,$key,true);
            $this->officials($c,'assignment','Assignment updated',$id . ' has an updated personnel assignment.',$key);
        }
        if (in_array($action,['start','note','resolve','reopen','block'],true)) {
            $title = match ($action) {'start' => 'Work started', 'note' => 'Work progress recorded', 'resolve' => 'Completed work ready for review', 'block' => 'Work blocked / delayed', default => 'Concern reopened'};
            $last = $c['timeline'][array_key_last($c['timeline'])];
            if (in_array($last['workStatus'] ?? '', ['Materials required','Waiting for materials','Unable to complete','Requires another team'],true)) $title = 'Work needs additional action';
            $this->officials($c,$action,$title,$id . ': ' . $title . '.',$key);
            if ($action === 'reopen' && $previous) $this->db->createNotification($previous,'reopen','Completed work reopened',$id . ' was returned for further assessment. Wait for a new assignment.',$id,$key,true);
        }
        if ($action === 'followup') {
            $this->officials($c,'followup','Reporter information received',$id . ' has new information and is ready for reassessment.',$key);
        }
        if ($action === 'manage_block' && $assigned) {
            $this->db->createNotification($assigned,'instructions','Blocked-work update',$id . ' has new official instructions or approval. Open the concern before continuing.',$id,$key);
        }
        if ($action === 'link_concern') {
            if ($previous) $this->db->createNotification($previous,'reassignment','Report linked to a primary concern',$id . ' no longer needs a separate work assignment.',$id,$key,true);
            $this->officials($c,'link','Same-issue reports linked',$id . ' now follows its primary concern.',$key);
        }
        if ($before && $assigned && in_array($action,['assess','edit'],true)) {
            if ($before['priority'] !== $c['priority']) $this->db->createNotification($assigned,'priority','Priority changed',$id . ' priority is now ' . $c['priority'] . '.',$id,$key . ':priority');
            if ($before['recommendation'] !== $c['recommendation']) $this->db->createNotification($assigned,'instructions','Work instructions updated',$id . ' has updated official instructions. Open the concern before continuing work.',$id,$key . ':instructions');
        }
        if (in_array($action,['submit','edit'],true)) $this->recurrence($c);
    }

    private function recurrence(array $c): void
    {
        $config = ConcernInsights::config();
        $days = (int)$config['recurrence_days'];
        $related = $this->db->recurrenceFor($c['id'],date(DATE_ATOM,time()-$days*86400));
        $count = count($related);
        if (!$related) return;
        $level = ConcernInsights::recurrenceLevel($count);
        if (!in_array($level,['Recurring','High Recurrence'],true)) return;
        $scope = 'recurrence:' . $related[0]['recurrence_key'] . ':' . $level;
        if (!$this->db->claimAlert($scope,$days*86400)) return;
        $this->officials($c,'recurrence',$level . ' detected',$count . ' similar concerns in the same area during the last ' . $days . ' days. Open the concern to review related reports.',$scope . ':' . time());
    }

    public function deadlines(): void
    {
        // Shared throttle, so opening multiple tabs/users never repeats an expensive sweep.
        if (!$this->db->claimAlert('deadline-sweep',60)) return;
        $hours = (int)ConcernInsights::config()['due_soon_hours'];
        foreach ($this->db->dueAssignments(time()+$hours*3600) as $row) {
            $overdue = (int)$row['due_at'] <= time();
            $type = $overdue ? 'overdue' : 'due_soon';
            $key = $type . ':' . $row['id'] . ':' . $row['due_at'] . ':' . $row['assigned_user_id'];
            $title = $overdue ? 'Work assignment overdue' : 'Work assignment due soon';
            $message = $row['id'] . ($overdue ? ' has passed its target completion time.' : ' is due within ' . $hours . ' hours.');
            $this->db->createNotification($row['assigned_user_id'],$type,$title,$message,$row['id'],$key);
            if ($overdue) $this->officials($row,$type,$title,$message,$key);
        }
    }
}
