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
        // Account reporters receive their own safe status messages, including accounts
        // that hide their identity. Guests have no residentId and use tracking only.
        $reporterTitles = ['submit'=>'Concern received','assess'=>'Priority assessed / under review','assign'=>'Concern assigned',
            'start'=>'Work in progress','request_information'=>'More information requested','resolve'=>'Concern resolved',
            'verify'=>'Concern closed','reopen'=>'Concern reopened','exception'=>'Concern outcome updated','link_concern'=>'Report linked'];
        if (!empty($c['residentId']) && isset($reporterTitles[$action])) {
            $title=$reporterTitles[$action];
            $this->db->createNotification($c['residentId'],'reporter_status',$title,$id . ': ' . $title . '. Open your concern in your MaintainPro dashboard.', $id,$key.':reporter');
        }
        if ($action === 'submit') {
            $priority = $c['priorityRecommendation']['priority'];
            $title = in_array($priority,['High','Urgent'],true) ? 'New concern: ' . $priority . ' recommended' : 'New concern submitted';
            $this->officials($c,'new_concern',$title,$id . ' is ready for assessment. Recommended priority: ' . $priority . '.',$key);
        }
        if ($action === 'assign') {
            foreach ($this->db->currentTeamRecipients($id) as $recipient) {
                $this->db->createNotification($recipient['id'],'team_assignment','New team assignment',$id . ' has been assigned to your team. Review the concern and accept the work if you are available.',$id,$key.':'.$recipient['id']);
            }
            if ($previous && $previous !== $assigned) $this->db->createNotification($previous,'reassignment','Assignment changed',$id . ' has been reassigned. Your other tasks remain in the work queue.',$id,$key,true);
            $this->officials($c,'assignment','Team assignment updated',$id . ' has been offered to ' . $c['team'] . '.',$key);
        }
        if ($action==='accept_work') $this->officials($c,'assignment_accepted','Team assignment accepted',$id . ' was accepted by ' . ($c['assignedName'] ?? 'a personnel member') . '.',$key);
        if (in_array($action,['start','note','resolve','reopen','block'],true)) {
            $title = match ($action) {'start' => 'Work started', 'note' => 'Work progress recorded', 'resolve' => 'Completed work ready for review', 'block' => 'Work blocked / delayed', default => 'Concern reopened'};
            $last = $c['timeline'][array_key_last($c['timeline'])];
            if (in_array($last['workStatus'] ?? '', ['Materials required','Waiting for materials','Unable to complete','Requires another team'],true)) $title = 'Work needs additional action';
            $this->officials($c,$action,$title,$id . ': ' . $title . '.',$key);
            if ($action === 'reopen' && $previous) $this->db->createNotification($previous,'reopen','Completed work reopened',$id . ' was returned for further assessment. Wait for a new assignment.',$id,$key,true);
        }
        if ($action === 'followup') {
            $this->officials($c,'followup','Reporter information received',$id . ' has new information. Review it alongside the current assessment or assigned work.',$key);
        }
        if ($action === 'manage_block' && $assigned) {
            $this->db->createNotification($assigned,'instructions','Blocked-work update',$id . ' has new official instructions or approval. Open the concern before continuing.',$id,$key);
        }
        if ($action === 'link_concern') {
            if ($previous) $this->db->createNotification($previous,'reassignment','Report linked to a primary concern',$id . ' no longer needs a separate work assignment.',$id,$key,true);
            $this->officials($c,'link','Same-issue reports linked',$id . ' now follows its primary concern.',$key);
        }
        if ($before && $assigned && $action === 'assess') {
            if ($before['priority'] !== $c['priority']) $this->db->createNotification($assigned,'priority','Priority changed',$id . ' priority is now ' . $c['priority'] . '.',$id,$key . ':priority');
            if ($before['recommendation'] !== $c['recommendation']) $this->db->createNotification($assigned,'instructions','Work instructions updated',$id . ' has updated official instructions. Open the concern before continuing work.',$id,$key . ':instructions');
        }
        if ($action === 'submit') $this->recurrence($c);
    }

    public function teamResponse(array $c,array $personnel,bool $accepted): void
    {
        $key=$c['id'].':team-response:'.$personnel['id'].':'.($accepted?'accepted':'declined');
        $title=$accepted?'Team assignment accepted':'Personnel not available';
        $message=$c['id'].' was '.($accepted?'accepted by ':'declined by ').$personnel['name'].' ('.$personnel['team'].').';
        $this->officials($c,$accepted?'assignment_accepted':'assignment_declined',$title,$message,$key);
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
        $today=date('Y-m-d');
        $through=date('Y-m-d',time()+$hours*3600);
        $afterId=0;
        do {
        $batch=$this->db->dueActionPlans($through,$afterId);
        foreach ($batch as $plan) {
            $afterId=(int)$plan['id'];
            $overdue=$plan['target_date']<$today;
            $type=$overdue?'plan_overdue':'plan_due_soon';
            $title=$overdue?'Action plan overdue':'Action plan due soon';
            $message='Action plan #'.$plan['id'].($overdue?' is past its target date.':' is approaching its target date.');
            $key='plan:'.$plan['id'].':'.$plan['target_date'].':'.$plan['assigned_user_id'].':'.$type;
            $this->db->createPlanNotification($plan['assigned_user_id'],$type,$title,$message,(int)$plan['id'],$key);
            if ($overdue) foreach ($this->db->officialIds() as $official) {
                $this->db->createPlanNotification($official,$type,$title,$message,(int)$plan['id'],$key,false);
            }
        }
        } while (count($batch)===500);
    }
}
