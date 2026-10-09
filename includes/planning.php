<?php
declare(strict_types=1);

// Structured planning and feedback extend the existing store transaction/guards.
trait ConcernPlanning
{
    public function possibleDuplicates(string $officialId, string $id): array
    {
        $this->authorizeOfficial($officialId);
        $source=$this->concern($id);
        return $source ? $this->matchDuplicates($source) : [];
    }

    private function notifyDuplicateSuggestions(array $source): void
    {
        $matches=$this->matchDuplicates($source);
        if (!$matches) return;
        foreach ($this->db->officialIds() as $official) $this->db->createNotification($official,'possible_duplicate','Possible Existing Concern',
            $source['id'].' may describe an existing concern. Review the matching reasons before linking.', $source['id'],$source['id'].':duplicate:'.$source['version']);
    }

    private function matchDuplicates(array $source): array
    {
        $id=$source['id'];
        if (!$source || in_array($source['status'],['Linked to Primary','Verified'],true)) return [];
        $location=$source['locationDetails'] ?? [];
        $street=ConcernInsights::normalizedStreet($location['street'] ?? '');
        if ($street==='') return [];
        $matches=[];
        foreach ($this->db->duplicateCandidates($id,date(DATE_ATOM,time()-ConcernInsights::config()['duplicate_recent_days']*86400)) as $row) {
            $same=(bool)$row['same_street'];
            $other=ConcernInsights::normalizedStreet($row['street_normalized']);
            $similar=$same || (min(mb_strlen($street),mb_strlen($other))>=8 && levenshtein($street,$other)<=2);
            $area=ConcernInsights::normalizedStreet($location['exactArea'] ?? '');
            $sameArea=$area!=='' && $area===ConcernInsights::normalizedStreet($row['exact_area'] ?? '');
            $points=array_intersect($source['keyPoints'] ?? [],json_decode($row['keypoints'] ?? '[]',true) ?: []);
            if (!$similar || (!$same && !$sameArea && !$points)) continue;
            $row['reasons']=['Same concern type','Same Purok',$same?'Same normalized street':'Similar street'];
            if ($sameArea) $row['reasons'][]='Same exact area';
            if ($points) $row['reasons'][]='Shared keypoints: '.implode(', ',$points);
            $row['daysAgo']=max(0,(int)floor((time()-strtotime($row['created_at']))/86400));
            $row['score']=($same?3:1)+($sameArea?2:0)+min(3,count($points));
            $matches[]=$row;
        }
        usort($matches,fn($a,$b)=>$b['score']<=>$a['score'] ?: strcmp($b['created_at'],$a['created_at']));
        return array_slice($matches,0,10);
    }

    public function dismissDuplicate(string $officialId, string $id, array $data): void
    {
        $this->transaction(function() use($officialId,$id,$data) {
            $actor=$this->authorizeOfficial($officialId);
            $candidate=ComplaintWorkflow::text($data['candidateId'] ?? '', 'Candidate',64);
            if (!in_array($candidate,array_column($this->possibleDuplicates($officialId,$id),'id'),true)) throw new DomainException('This suggestion is no longer available.');
            $this->db->dismissDuplicate($id,$candidate,$officialId);
            $this->db->recordAudit($actor,'duplicate_suggestion_dismissed','concern',$id,$id,['candidate'=>$candidate]);
        });
    }

    public function officialRules(string $officialId, string $category, string $type, string $point): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->officialRules($category,$type,$point);
    }

    public static function ruleRevision(array $rows): string
    {
        return hash('sha256',json_encode(array_map(fn($r)=>[$r['id'],$r['version']],$rows),JSON_THROW_ON_ERROR));
    }

    public function saveOfficialRules(string $officialId, array $data): void
    {
        $this->transaction(function() use($officialId,$data) {
            $actor=$this->authorizeOfficial($officialId);
            [$category,$type]=ConcernCatalog::selections($data);
            $point=ComplaintWorkflow::text($data['keypoint'] ?? '', 'Keypoint',100);
            if (!in_array($point,ConcernCatalog::POINTS[$category],true)) throw new DomainException('Choose a keypoint for this category.');
            $current=$this->db->officialRules($category,$type,$point);
            if (!is_string($data['revision'] ?? null) || !hash_equals(self::ruleRevision($current),$data['revision'])) throw new ConflictException('These official actions changed. Reload before saving.');
            $seen=[];
            for ($i=1;$i<=3;$i++) {
                $text=ComplaintWorkflow::text($data['action'.$i] ?? '', 'Suggested action',700,false);
                $active=($data['active'.$i] ?? '')==='1';
                if ($active && $text==='') throw new DomainException('An active action needs text.');
                if ($text!=='' && in_array(mb_strtolower($text),$seen,true)) throw new DomainException('Use different suggested actions.');
                $seen[]=mb_strtolower($text);
                $this->db->saveOfficialRule([$category,$type,$point,$text,$i,(int)$active,$actor['id'],$actor['id'],time(),time()]);
            }
            $this->db->recordAudit($actor,'official_solutions_updated','official_solution',null,$category.' / '.$type.' / '.$point,['actions'=>3]);
        });
    }

    private function planFields(array $data): array
    {
        $title=ComplaintWorkflow::text($data['title'] ?? '', 'Action title',180);
        $notes=ComplaintWorkflow::text($data['notes'] ?? '', 'Description / notes',4000,false);
        $team=ComplaintWorkflow::text($data['team'] ?? '', 'Responsible team',100);
        if (!in_array($team,ComplaintWorkflow::TEAMS,true)) throw new DomainException('Choose a responsible team.');
        $personnel=ComplaintWorkflow::text($data['personnelId'] ?? '', 'Assigned personnel',64,false);
        if ($personnel!=='') {
            $this->db->lockedUser($personnel);
            $user=$this->actor($personnel);
            if (!$user || $user['role']!=='personnel' || $user['team']!==$team) throw new DomainException('Choose active personnel from the responsible team.');
        }
        $date=ComplaintWorkflow::text($data['targetDate'] ?? '', 'Target date',10,false);
        $parsed=$date!=='' ? DateTimeImmutable::createFromFormat('!Y-m-d',$date) : false;
        if ($date!=='' && (!$parsed || $parsed->format('Y-m-d')!==$date)) throw new DomainException('Choose a valid target date.');
        return [$title,$notes,$team,$personnel ?: null,$date ?: null];
    }

    private function notifyActionPlanTeam(string $team,int $planId,string $type,string $title,string $message,string $event): int
    {
        $sent=0;
        foreach ($this->db->activePersonnelForTeam($team) as $person) {
            $this->db->createPlanNotification($person['id'],$type,$title,$message,$planId,$event.':'.$person['id']);
            $sent++;
        }
        return $sent;
    }

    public function saveActionPlan(string $officialId, int $id, array $data): int
    {
        return $this->transaction(function() use($officialId,$id,$data) {
            $actor=$this->authorizeOfficial($officialId);
            [$title,$notes,$team,$personnel,$date]=$this->planFields($data);
            if ($id===0) {
                $ruleId=filter_var($data['ruleId'] ?? null,FILTER_VALIDATE_INT);
                if (!$ruleId || $ruleId<1) throw new DomainException('Choose a valid suggested action.');
                $rule=$this->db->officialRule($ruleId);
                if (!$rule || !$rule['active'] || trim($rule['action_text'])==='') throw new DomainException('Choose an active suggested official action.');
                if (filter_var($data['ruleVersion'] ?? null,FILTER_VALIDATE_INT)!==(int)$rule['version']) throw new ConflictException('The selected solution changed. Reload and review it before creating the plan.');
                $key=$data['requestKey'] ?? '';
                if (!is_string($key) || !preg_match('/\A[a-f0-9]{64}\z/',$key)) throw new DomainException('Reload the action-plan form before saving.');
                $existing=$this->db->actionPlanRequest(hash('sha256',$actor['id'].':'.$key));
                if ($existing!==null) return $existing;
                $id=$this->db->createActionPlan([$rule['id'],$rule['category'],$rule['concern_type'],$rule['keypoint'],$rule['action_text'],$title,$notes,$team,$personnel,$actor['id'],ConcernInsights::week()['date'],$date,'',time(),time(),hash('sha256',$actor['id'].':'.$key)]);
                $action='weekly_action_plan_created'; $changes=['status'=>'Planned'];
                if ($personnel) $this->db->createPlanNotification($personnel,'plan_assignment','Action plan assigned','Action plan #'.$id.' has been assigned to you.',$id,'plan:'.$id.':assigned:1');
                else $changes['teamNotifications']=$this->notifyActionPlanTeam($team,$id,'plan_team_assignment','New team action plan','Weekly action plan #'.$id.' is available for '.$team.'.',$id.':team-assigned:1');
            } else {
                $before=$this->db->actionPlan($id);
                $version=filter_var($data['version'] ?? null,FILTER_VALIDATE_INT);
                if (!$before || $version!==(int)$before['version']) throw new ConflictException('This action plan changed. Reload before saving.');
                $status=$data['status'] ?? '';
                if (!in_array($status,['Planned','Ongoing','Completed','Cancelled'],true)) throw new DomainException('Choose a valid action-plan status.');
                $outcome=ComplaintWorkflow::text($data['outcome'] ?? '', 'Result / outcome',4000,$status==='Completed');
                $completed=$status==='Completed' ? ($before['completed_at'] ?: time()) : null;
                $this->db->updateActionPlan($id,[$title,$notes,$team,$personnel,$date,$status,$completed,$outcome,time()],$version);
                $action='weekly_action_plan_updated';
                $changes=['previousStatus'=>$before['status'],'status'=>$status,'previousTeam'=>$before['team'],'team'=>$team,'previousPersonnel'=>$before['assigned_user_id'],'personnel'=>$personnel,'targetDate'=>$date];
                if ($before['assigned_user_id'] && $before['assigned_user_id']!==$personnel) {
                    $this->db->createPlanNotification($before['assigned_user_id'],'plan_reassigned','Action plan reassigned','Action plan #'.$id.' is no longer assigned to you.',$id,'plan:'.$id.':reassigned:'.$version);
                }
                if ($personnel && ($before['assigned_user_id']!==$personnel || $before['status']!==$status || $before['target_date']!==$date || $before['notes']!==$notes)) {
                    $this->db->createPlanNotification($personnel,'plan_updated','Action plan updated','Action plan #'.$id.' has new assignment details.',$id,'plan:'.$id.':updated:'.$version);
                }
                if (!$personnel && ($before['assigned_user_id']!==null || $before['team']!==$team || $before['status']!==$status || $before['target_date']!==$date || $before['notes']!==$notes || $before['title']!==$title)) {
                    $changes['teamNotifications']=$this->notifyActionPlanTeam($team,$id,'plan_team_updated','Team action plan updated','Weekly action plan #'.$id.' has updated work details for '.$team.'.',$id.':team-updated:'.$version);
                }
            }
            $this->db->recordAudit($actor,$action,'action_plan',(string)$id,$title,$changes);
            return $id;
        });
    }

    public function actionPlan(string $officialId, int $id): ?array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->actionPlan($id);
    }

    public function visibleActionPlan(string $actorId, int $id): ?array
    {
        $actor=$this->actor($actorId);
        if (!$actor || $actor['must_change_password']) return null;
        $plan=$this->db->actionPlan($id);
        if (!$plan) return null;
        $personnelAccess=$actor['role']==='personnel' && ($plan['assigned_user_id']===$actorId || ($plan['assigned_user_id']===null && $plan['team']===$actor['team']));
        if ($actor['role']!=='official' && !$personnelAccess) return null;
        $plan['progress']=$this->db->actionPlanProgress($id);
        return $plan;
    }

    public function myActionPlans(string $personnelId, string $status='', int $page=1): array
    {
        $actor=$this->actor($personnelId);
        if (!$actor || $actor['must_change_password'] || $actor['role']!=='personnel') throw new DomainException('Personnel account required.');
        if ($status!=='' && !in_array($status,['Planned','Ongoing','Completed','Cancelled'],true)) throw new DomainException('Choose a valid status.');
        return $this->db->personnelActionPlans($personnelId,$actor['team'],$status,$page,20);
    }

    public function savePersonnelActionPlan(string $personnelId, int $id, array $data): void
    {
        $this->transaction(function() use($personnelId,$id,$data) {
            $actor=$this->actor($personnelId);
            if (!$actor || $actor['must_change_password'] || $actor['role']!=='personnel') throw new DomainException('Personnel account required.');
            $plan=$this->db->lockedActionPlan($id);
            if (!$plan || !($plan['assigned_user_id']===$personnelId || ($plan['assigned_user_id']===null && $plan['team']===$actor['team']))) throw new DomainException('Action plan unavailable.');
            $version=filter_var($data['version'] ?? null,FILTER_VALIDATE_INT);
            if ($version!==(int)$plan['version']) throw new ConflictException('This action plan changed. Reload before saving.');
            $step=$data['step'] ?? '';
            $from=$plan['status'];
            $to=match($step) {
                'start' => $from==='Planned' ? 'Ongoing' : null,
                'progress' => $from==='Ongoing' ? 'Ongoing' : null,
                'complete' => $from==='Ongoing' ? 'Completed' : null,
                default => null,
            };
            if ($to===null) throw new DomainException('This action plan is not ready for that update.');
            $note=ComplaintWorkflow::text($data['note'] ?? '', 'Progress note',4000,$step!=='start');
            if ($note==='') $note='Work started';
            $evidence=null;
            if (($data['photo'] ?? '')!=='') {
                $evidence=EvidenceStorage::storeDataUri($data['photo'],$data['photoName'] ?? '');
                $this->pendingEvidence[]=$evidence['file_path'];
            }
            $outcome=$step==='complete' ? $note : null;
            if (!$this->db->updatePersonnelPlan($id,$personnelId,$actor['team'],$from,$to,$outcome,$version)) throw new ConflictException('This action plan changed. Reload before saving.');
            $this->db->insertPlanProgress($id,$actor,$note,$evidence);
            $this->db->recordAudit($actor,'action_plan_'.$step,'action_plan',(string)$id,$plan['title'],['statusFrom'=>$from,'statusTo'=>$to,'evidence'=>$evidence!==null]);
            if ($step==='complete') foreach ($this->db->officialIds() as $official) {
                $this->db->createPlanNotification($official,'plan_completed','Action plan completed','Action plan #'.$id.' was completed by '.($plan['assigned_user_id']===null?'the responsible team':'assigned personnel').'.',$id,'plan:'.$id.':completed:'.$version,false);
            }
        });
    }

    public function planEvidenceRecord(string $actorId, string $evidenceId): ?array
    {
        $actor=$this->actor($actorId);
        if (!$actor || $actor['must_change_password']) return null;
        return $this->db->planEvidence($evidenceId,$actorId) ?: null;
    }

    public function selectedOfficialRule(string $officialId, int $id): ?array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->officialRule($id);
    }

    public function actionPlans(string $officialId, string $status='', string $week='', int $page=1, int $perPage=20): array
    {
        $this->authorizeOfficial($officialId);
        if ($status!=='' && !in_array($status,['Planned','Ongoing','Completed','Cancelled'],true)) throw new DomainException('Choose a valid status.');
        $date=$week!=='' ? DateTimeImmutable::createFromFormat('!Y-m-d',$week) : false;
        if ($week!=='' && (!$date || $date->format('Y-m-d')!==$week || $date->format('N')!=='1')) throw new DomainException('Choose the Monday beginning a valid week.');
        return $this->db->actionPlans($status,$week,$page,min(50,max(1,$perPage)));
    }

    private function mayGiveFeedback(array $actor, array $c): bool
    {
        if (($c['residentId'] ?? null)!==$actor['id'] || !in_array($c['status'],['Resolved','Verified'],true)) return false;
        if (($c['assignedUserId'] ?? null)===$actor['id'] || ($c['resolution']['uploadedBy'] ?? null)===$actor['id']) return false;
        foreach ($c['timeline'] as $event) if (($event['actorId'] ?? null)===$actor['id'] && isset($event['workStatus'])) return false;
        return true;
    }

    public function concernFeedback(string $userId, string $id): array
    {
        $actor=$this->actor($userId); $c=$this->concern($id);
        if (!$actor || $actor['must_change_password'] || !$c || !ComplaintWorkflow::canSee($c,$actor)) throw new DomainException('Concern unavailable.');
        $feedback=$actor['role']==='official' || ($c['residentId'] ?? null)===$userId ? $this->db->feedback($id) : null;
        return ['feedback'=>$feedback,'canSubmit'=>$this->mayGiveFeedback($actor,$c) && !$this->db->feedback($id)];
    }

    public function submitFeedback(string $userId, string $id, array $data): void
    {
        $this->transaction(function() use($userId,$id,$data) {
            $actor=$this->actor($userId); $c=$this->concern($id,true);
            if (!$actor || $actor['must_change_password'] || !$c || !$this->mayGiveFeedback($actor,$c)) throw new DomainException('Only the original reporter may rate completed work that they did not perform.');
            if ($this->db->feedback($id)) throw new DomainException('Feedback has already been submitted for this concern.');
            $rating=filter_var($data['rating'] ?? null,FILTER_VALIDATE_INT);
            if ($rating===false || $rating<1 || $rating>5) throw new DomainException('Choose a rating from 1 to 5.');
            $comment=ComplaintWorkflow::text($data['comment'] ?? '', 'Comment',2000,false);
            $this->db->insertFeedback($id,$userId,$rating,$comment);
            // Anonymous reporters remain anonymous in staff-facing audit history.
            if (!empty($c['isAnonymous'])) $actor=['id'=>null,'name'=>'Anonymous reporter'];
            $this->db->recordAudit($actor,'resolution_feedback_submitted','concern',$id,$id,['rating'=>$rating]);
        });
    }

    public function feedbackAnalytics(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->feedbackAnalytics();
    }
}
