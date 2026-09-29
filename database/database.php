<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

// All SQL belongs in this file. Callers pass values to named operations.
final class MaintainProDatabase
{
    public function duplicateCandidates(string $id, string $since): array
    {
        return $this->run("SELECT c.id,c.status,c.created_at,c.street_normalized,
            JSON_UNQUOTE(JSON_EXTRACT(c.payload,'$.locationDetails.exactArea')) AS exact_area,
            JSON_EXTRACT(c.payload,'$.keyPoints') AS keypoints,
            c.street_normalized=source.street_normalized AS same_street
            FROM complaints source JOIN complaints c ON c.category_name=source.category_name
              AND c.concern_type=source.concern_type AND c.purok_key=source.purok_key
            WHERE source.id=? AND c.id<>source.id AND c.status NOT IN ('Linked to Primary','Rejected','Referred to Another Office')
              AND (c.status NOT IN ('Resolved','Verified') OR c.created_at>=?)
              AND NOT EXISTS(SELECT 1 FROM duplicate_dismissals d WHERE (d.complaint_id=source.id AND d.candidate_id=c.id) OR (d.candidate_id=source.id AND d.complaint_id=c.id))
            ORDER BY same_street DESC,c.created_at DESC,c.id DESC LIMIT 100",[$id,$since])->fetchAll();
    }

    public function dismissDuplicate(string $id, string $candidate, string $official): void
    {
        $this->run('INSERT INTO duplicate_dismissals(complaint_id,candidate_id,dismissed_by,created_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE complaint_id=VALUES(complaint_id)',[$id,$candidate,$official,time()]);
    }

    public function officialRules(string $category, string $type, ?string $point = null, bool $activeOnly = false): array
    {
        return $this->run('SELECT * FROM official_solution_rules WHERE category=? AND concern_type=?'.($point!==null?' AND keypoint=?':'').($activeOnly?' AND active=1':'').' ORDER BY keypoint,sort_order,id', $point!==null?[$category,$type,$point]:[$category,$type])->fetchAll();
    }

    public function officialRule(int $id): ?array
    {
        return $this->run('SELECT * FROM official_solution_rules WHERE id=?',[$id])->fetch() ?: null;
    }

    public function saveOfficialRule(array $v): void
    {
        $this->run('INSERT INTO official_solution_rules(category,concern_type,keypoint,action_text,sort_order,active,created_by,updated_by,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE action_text=VALUES(action_text),active=VALUES(active),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at),version=version+1',$v);
    }

    public function actionPlan(int $id): ?array
    {
        return $this->run('SELECT * FROM weekly_action_plans WHERE id=?',[$id])->fetch() ?: null;
    }

    public function createActionPlan(array $v): int
    {
        $existing=$this->run('SELECT id FROM weekly_action_plans WHERE request_key=?',[$v[count($v)-1]])->fetchColumn();
        if ($existing!==false) return (int)$existing;
        $this->run("INSERT INTO weekly_action_plans(solution_rule_id,category,concern_type,keypoint,selected_solution,title,notes,team,assigned_user_id,created_by,week_start,target_date,outcome,created_at,updated_at,request_key) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",$v);
        return (int)$this->connection->lastInsertId();
    }

    public function actionPlanRequest(string $key): ?int
    {
        $id=$this->run('SELECT id FROM weekly_action_plans WHERE request_key=?',[$key])->fetchColumn();
        return $id===false ? null : (int)$id;
    }

    public function updateActionPlan(int $id, array $v, int $version): void
    {
        $stmt=$this->run('UPDATE weekly_action_plans SET title=?,notes=?,team=?,assigned_user_id=?,target_date=?,status=?,completed_at=?,outcome=?,updated_at=?,version=version+1 WHERE id=? AND version=?',[...$v,$id,$version]);
        if ($stmt->rowCount()!==1) throw new ConflictException('This action plan changed. Reload before saving.');
    }

    public function actionPlans(string $status, string $week, int $page, int $perPage): array
    {
        $where='(?=\'\' OR status=?) AND (?=\'\' OR week_start=?)'; $values=[$status,$status,$week,$week];
        $total=(int)$this->run('SELECT COUNT(*) FROM weekly_action_plans WHERE '.$where,$values)->fetchColumn();
        $page=min(max(1,$page),max(1,(int)ceil($total/$perPage)));
        $items=$this->run('SELECT p.*,u.name AS personnel_name FROM weekly_action_plans p LEFT JOIN users u ON u.id=p.assigned_user_id WHERE '.$where.' ORDER BY p.created_at DESC,p.id DESC LIMIT '.(int)$perPage.' OFFSET '.(($page-1)*$perPage),$values)->fetchAll();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'perPage'=>$perPage];
    }

    public function feedback(string $id): ?array
    {
        return $this->run('SELECT rating,comment,created_at FROM concern_feedback WHERE complaint_id=?',[$id])->fetch() ?: null;
    }

    public function insertFeedback(string $id, string $user, int $rating, string $comment): void
    {
        $this->run('INSERT INTO concern_feedback(complaint_id,reporter_id,rating,comment,created_at,updated_at) VALUES(?,?,?,?,?,?)',[$id,$user,$rating,$comment,time(),time()]);
    }

    public function feedbackAnalytics(): array
    {
        return ['summary'=>$this->run('SELECT COUNT(*) AS responses,ROUND(AVG(rating),2) AS average FROM concern_feedback')->fetch(),
            'categories'=>$this->run('SELECT c.category_name AS category,COUNT(*) AS responses,ROUND(AVG(f.rating),2) AS average FROM concern_feedback f JOIN complaints c ON c.id=f.complaint_id GROUP BY c.category_name ORDER BY responses DESC')->fetchAll(),
            'recent'=>$this->run("SELECT f.complaint_id,f.rating,f.comment,f.created_at,c.category_name FROM concern_feedback f JOIN complaints c ON c.id=f.complaint_id WHERE f.comment<>'' ORDER BY f.created_at DESC LIMIT 20")->fetchAll()];
    }

    public function evidenceFiles(): array
    {
        return $this->run('SELECT file_path FROM concern_evidence ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function insertEvidence(string $id, string $concern, ?string $user, string $type, string $path, string $original, string $mime, int $size, int $width, int $height, int $created): void
    {
        $this->run('INSERT INTO concern_evidence (id,complaint_id,uploaded_by,evidence_type,file_path,original_filename,mime_type,file_size,width,height,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$id,$concern,$user,$type,$path,$original,$mime,$size,$width,$height,$created]);
    }

    public function evidenceForActor(string $id, array $actor): ?array
    {
        return $this->run("SELECT e.* FROM concern_evidence e JOIN complaints c ON c.id=e.complaint_id WHERE e.id=? AND (?='official' OR (?='personnel' AND c.assigned_user_id=?) OR c.resident_id=?)", [$id,$actor['role'],$actor['role'],$actor['id'],$actor['id']])->fetch() ?: null;
    }

    public function locations(bool $includeInactive = false): array
    {
        return $this->run('SELECT * FROM locations' . ($includeInactive ? '' : ' WHERE active=1') . ' ORDER BY sort_order,name,id')->fetchAll();
    }

    public function location(int $id): ?array
    {
        return $this->run('SELECT * FROM locations WHERE id=?',[$id])->fetch() ?: null;
    }

    public function insertLocation(string $name, int $sort, int $created): int
    {
        $this->run('INSERT INTO locations (name,sort_order,created_at,updated_at) VALUES (?,?,?,?)',[$name,$sort,$created,$created]);
        return (int)$this->connection->lastInsertId();
    }

    public function updateLocation(int $id, string $name, int $sort, bool $active, int $updated): void
    {
        $this->run('UPDATE locations SET name=?,sort_order=?,active=?,updated_at=? WHERE id=?',[$name,$sort,(int)$active,$updated,$id]);
    }

    public function rootConcernId(string $id): string
    {
        $seen=[];
        while ($id !== '' && !isset($seen[$id])) {
            $seen[$id]=true;
            $parent=$this->primaryConcernId($id);
            if ($parent===null) return $id;
            $id=$parent;
        }
        return ''; // Reject corrupt cycles, rather than walking forever.
    }

    public function linkedConcernCount(string $id): int
    {
        return (int)$this->run("SELECT COUNT(*) FROM complaints WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.linkedPrimaryId'))=?",[$id])->fetchColumn();
    }

    public function concernLinks(string $id): array
    {
        return ['primaryConcernId'=>$this->primaryConcernId($id), 'linkedConcerns'=>$this->run("SELECT id,status,created_at FROM complaints WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.linkedPrimaryId'))=? ORDER BY created_at DESC",[$id])->fetchAll()];
    }

    public function blockedConcerns(int $page, int $perPage): array
    {
        $where="JSON_EXTRACT(payload,'$.blocked.active')=true AND status IN ('Assigned','In Progress')";
        $total=(int)$this->run('SELECT COUNT(*) FROM complaints WHERE '.$where)->fetchColumn();
        $items=$this->run('SELECT id,status,payload,version FROM complaints WHERE '.$where.' ORDER BY updated_at DESC LIMIT '.(int)$perPage.' OFFSET '.(($page-1)*$perPage))->fetchAll();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'perPage'=>$perPage];
    }

    public function auditLogs(array $filters, int $page, int $perPage): array
    {
        $where=['1=1']; $values=[];
        if ($filters['date']!=='') { $where[]='created_at LIKE ?'; $values[]=$filters['date'].'%'; }
        if ($filters['action']!=='') { $where[]='action=?'; $values[]=$filters['action']; }
        if ($filters['user']!=='') { $where[]='actor_id=?'; $values[]=$filters['user']; }
        $condition=implode(' AND ',$where);
        $total=(int)$this->run('SELECT COUNT(*) FROM audit_logs WHERE '.$condition,$values)->fetchColumn();
        $items=$this->run('SELECT * FROM audit_logs WHERE '.$condition.' ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(($page-1)*$perPage),$values)->fetchAll();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'perPage'=>$perPage];
    }

    public function pagedComplaints(array $actor, array $filters, int $page, int $perPage, bool $history): array
    {
        $own = ($filters['scope'] ?? '') === 'mine' || $actor['role'] === 'resident';
        $where = [$own ? 'resident_id=?' : "(?='official' OR (?='personnel' AND assigned_user_id=?))"];
        $values = $own ? [$actor['id']] : [$actor['role'], $actor['role'], $actor['id']];
        if ($history) $where[]="(status IN ('Resolved','Verified','Rejected','Referred to Another Office') OR JSON_TYPE(JSON_EXTRACT(payload,'$.resolution'))='OBJECT')";
        $tab=$filters['tab'] ?? '';
        if ($tab==='assessment') $where[]="status IN ('Submitted','Under Review','Reopened')";
        if (in_array($tab,['active','work'],true)) $where[]="status IN ('Assigned','In Progress')";
        if (in_array($tab,['pending','urgent'],true)) $where[]="status NOT IN ('Verified','Rejected','Referred to Another Office','Linked to Primary')";
        if ($tab==='urgent') $where[]="JSON_UNQUOTE(JSON_EXTRACT(payload,'$.priority'))='Urgent'";
        if ($tab==='reopened') $where[]="JSON_EXTRACT(payload,'$.reopenCount')>0";
        $statuses=['progress'=>'In Progress','assigned'=>'Assigned','submitted'=>'Submitted','reopened_now'=>'Reopened'];
        if (isset($statuses[$tab])) { $where[]='status=?'; $values[]=$statuses[$tab]; }
        if ($tab==='resolved') $where[]="status='Resolved'";
        if ($tab==='verified') $where[]="status='Verified'";
        foreach (['priority','category'] as $field) if (($filters[$field] ?? '')!=='') { $where[]="JSON_UNQUOTE(JSON_EXTRACT(payload,'$.".$field."'))=?"; $values[]=$filters[$field]; }
        if (($filters['status'] ?? '')!=='') { $where[]='status=?'; $values[]=$filters['status']; }
        foreach (['team' => 'team', 'personnel' => 'assigned_user_id', 'type' => 'concern_type'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') { $where[]=$column.'=?'; $values[]=$filters[$filter]; }
        }
        if (!empty($filters['week'])) {
            $start = new DateTimeImmutable($filters['week'], new DateTimeZone('Asia/Manila'));
            $where[]='created_at>=? AND created_at<?';
            array_push($values,$start->format(DATE_ATOM),$start->modify('+7 days')->format(DATE_ATOM));
            if (($filters['type'] ?? '') === '') $where[]="(concern_type IS NULL OR concern_type='')";
        }
        if (($filters['search'] ?? '')!=='') {
            $where[]="LOCATE(?,CONCAT_WS(' ',id,JSON_UNQUOTE(JSON_EXTRACT(payload,'$.title')),JSON_UNQUOTE(JSON_EXTRACT(payload,'$.location')),category_name,team,
                CASE WHEN COALESCE(JSON_EXTRACT(payload,'$.isAnonymous')=true,resident_id IS NULL) THEN 'Anonymous'
                ELSE COALESCE((SELECT name FROM users WHERE users.id=complaints.resident_id),JSON_UNQUOTE(JSON_EXTRACT(payload,'$.resident'))) END))>0";
            $values[]=$filters['search'];
        }
        $condition=implode(' AND ',$where);
        $total=(int)$this->run('SELECT COUNT(*) FROM complaints WHERE '.$condition,$values)->fetchColumn();
        $page = min($page,max(1,(int)ceil($total/$perPage)));
        $order = $history ? 'updated_at DESC,id DESC' : 'created_at DESC,id DESC';
        if ($actor['role']==='personnel' && !$own && !$history) $order="FIELD(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.priority')),'Urgent','High','Medium','Low'),created_at,id";
        $items=$this->run('SELECT payload,version FROM complaints WHERE '.$condition.' ORDER BY '.$order.' LIMIT '.(int)$perPage.' OFFSET '.(($page-1)*$perPage),$values)->fetchAll();
        return ['items'=>$items,'total'=>$total,'page'=>$page,'perPage'=>$perPage];
    }

    public function complaintMetrics(array $actor, bool $own = false): array
    {
        $where=$own ? 'resident_id=?' : "(?='official' OR (?='personnel' AND assigned_user_id=?) OR (?='resident' AND resident_id=?))";
        $values=$own?[$actor['id']]:[$actor['role'],$actor['role'],$actor['id'],$actor['role'],$actor['id']];
        $active="status NOT IN ('Verified','Rejected','Referred to Another Office','Linked to Primary')";
        $expressions=['total'=>'COUNT(*)','assessment'=>"SUM(status IN ('Submitted','Under Review','Reopened'))",'pending'=>'SUM('.$active.')',
            'urgent'=>"SUM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.priority'))='Urgent' AND ".$active.')',
            'reopened'=>"SUM(JSON_EXTRACT(payload,'$.reopenCount')>0)"];
        foreach(['progress'=>'In Progress','resolved'=>'Resolved','verified'=>'Verified','assigned'=>'Assigned','submitted'=>'Submitted','reopened_now'=>'Reopened'] as $name=>$status) $expressions[$name]="SUM(status='".$status."')";
        $select=[];
        foreach($expressions as $name=>$expression) $select[]='COALESCE('.$expression.',0) AS '.$name;
        $select[]="AVG(CASE WHEN status IN ('Resolved','Verified') THEN GREATEST(0,TIMESTAMPDIFF(SECOND,REPLACE(LEFT(created_at,19),'T',' '),REPLACE(LEFT(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.resolution.date')),19),'T',' ')))/86400 END) AS average";
        $row=$this->run('SELECT '.implode(',',$select).' FROM complaints WHERE '.$where,$values)->fetch();
        foreach($expressions as $name=>$unused) $row[$name]=(int)$row[$name];
        $row['average']=$row['average']===null?'—':number_format((float)$row['average'],1);
        return $row;
    }

    public function legacyEvidenceConcern(string $id, array $actor): ?array
    {
        return $this->run("SELECT payload,version FROM complaints WHERE (?='official' OR (?='personnel' AND assigned_user_id=?) OR resident_id=?)
            AND JSON_SEARCH(payload,'one',?,NULL,'$.timeline[*].evidenceId') IS NOT NULL LIMIT 1",[$actor['role'],$actor['role'],$actor['id'],$actor['id'],$id])->fetch() ?: null;
    }

    public function dashboardGroups(): array
    {
        return ['categories'=>$this->run('SELECT category_name AS label,COUNT(*) AS count FROM complaints GROUP BY category_name ORDER BY count DESC,label')->fetchAll(),
            'teams'=>$this->run("SELECT team AS label,COUNT(*) AS count FROM complaints WHERE team<>'' AND status IN ('Assigned','In Progress','Reopened') GROUP BY team ORDER BY count DESC,team LIMIT 3")->fetchAll()];
    }

    public function publicStatistics(): array
    {
        $month=(new DateTimeImmutable('first day of this month',new DateTimeZone('Asia/Manila')))->setTime(0,0)->format(DATE_ATOM);
        return ['summary'=>$this->run("SELECT COUNT(*) AS total,COALESCE(SUM(created_at>=?),0) AS this_month,
            COALESCE(SUM(status IN ('Submitted','Under Review','Returned for Information','Reopened')),0) AS under_review,
            COALESCE(SUM(status IN ('Assigned','In Progress')),0) AS in_progress,
            COALESCE(SUM(status IN ('Resolved','Verified')),0) AS resolved FROM complaints",[$month])->fetch(),
            'categories'=>$this->run('SELECT category_name AS label,COUNT(*) AS count FROM complaints GROUP BY category_name ORDER BY count DESC,label')->fetchAll(),
            // Free-text addresses never enter public groups. Only the official location registry is used.
            'puroks'=>$this->run("SELECT COALESCE(l.name,'Unspecified area') AS label,COUNT(*) AS count FROM complaints c LEFT JOIN locations l ON c.purok_key=CONCAT('id:',l.id) GROUP BY l.id,l.name ORDER BY count DESC,label")->fetchAll(),
            'months'=>$this->run("SELECT LEFT(created_at,7) AS label,COUNT(*) AS count FROM complaints GROUP BY LEFT(created_at,7) ORDER BY label DESC LIMIT 12")->fetchAll()];
    }

    public function sqlBackup(bool $withoutCredentials = false): string
    {
        $sql="-- MaintainPro database backup. Restore only into an empty database.\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($this->run('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $quoted='`'.str_replace('`','``',$table).'`';
            $schema=$this->run('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            $sql.=$schema.";\n";
            if ($withoutCredentials && in_array($table,['password_resets','password_reset_requests','login_attempts','public_attempts','feature_alerts'],true)) continue;
            $columns=$this->run('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll();
            $columns=array_column(array_filter($columns,fn($c)=>!str_contains($c['Extra'],'GENERATED')),'Field');
            $names=implode(',',array_map(fn($name)=>'`'.str_replace('`','``',$name).'`',$columns));
            foreach($this->run('SELECT '.$names.' FROM '.$quoted)->fetchAll(PDO::FETCH_NUM) as $row) {
                if ($withoutCredentials && $table==='users') {
                    $row[array_search('password_hash',$columns,true)]='!reset-required';
                    $row[array_search('auth_version',$columns,true)]=(int)$row[array_search('auth_version',$columns,true)]+1;
                }
                $values=array_map(fn($v)=>$v===null?'NULL':$this->connection->quote((string)$v),$row);
                $sql.='INSERT INTO '.$quoted.' ('.$names.') VALUES ('.implode(',',$values).");\n";
            }
        }
        return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
    }
    public function recordAudit(array $actor, string $action, string $entityType, ?string $entityId, string $label, array $changes): void
    {
        $this->run('INSERT INTO audit_logs (actor_id,actor_name,action,entity_type,entity_id,entity_label,changes,created_at) VALUES (?,?,?,?,?,?,?,?)', [
            $actor['id'], $actor['name'], $action, $entityType, $entityId, $label,
            json_encode($changes, JSON_THROW_ON_ERROR), date(DATE_ATOM),
        ]);
    }

    public function workloads(): array
    {
        return $this->run("SELECT u.id,u.name,u.team,u.active,
            COALESCE(SUM(c.status IN ('Assigned','In Progress')),0) AS active_work,
            COALESCE(SUM(c.status IN ('Resolved','Verified')),0) AS completed
            FROM users u LEFT JOIN complaints c ON c.assigned_user_id=u.id
            WHERE u.role='personnel' GROUP BY u.id,u.name,u.team,u.active ORDER BY u.name")->fetchAll();
    }

    public function navigationCounts(array $actor): array
    {
        return $this->run("SELECT COUNT(*) AS total,COALESCE(SUM(status IN ('Submitted','Under Review','Reopened')),0) AS assessment
            FROM complaints WHERE ?='official' OR (?='personnel' AND assigned_user_id=?) OR (?='resident' AND resident_id=?)", [$actor['role'],$actor['role'],$actor['id'],$actor['role'],$actor['id']])->fetch();
    }

    public function recurrenceGroups(string $since, int $minimum = 2): array
    {
        return $this->run("SELECT g.*,c.concern_type,
            JSON_UNQUOTE(JSON_EXTRACT(c.payload,'$.locationDetails.purok')) AS area,
            JSON_UNQUOTE(JSON_EXTRACT(c.payload,'$.locationDetails.street')) AS street
            FROM (SELECT recurrence_key,COUNT(*) AS total,MAX(created_at) AS latest,MIN(id) AS example_id
              FROM complaints WHERE created_at>=? AND recurrence_key IS NOT NULL
              GROUP BY recurrence_key HAVING COUNT(*)>=? ORDER BY total DESC,latest DESC LIMIT 100) g
            JOIN complaints c ON c.id=g.example_id ORDER BY g.total DESC,g.latest DESC", [$since,$minimum])->fetchAll();
    }

    public function recurrenceFor(string $id, string $since): array
    {
        return $this->run('SELECT c.id,c.status,c.created_at,c.concern_type,c.recurrence_key FROM complaints c
            JOIN complaints source ON source.recurrence_key=c.recurrence_key
            WHERE source.id=? AND c.created_at>=? ORDER BY c.created_at DESC,c.id DESC', [$id,$since])->fetchAll();
    }

    public function createNotification(string $user, string $type, string $title, string $message, string $concern, string $event, bool $queue = false): void
    {
        $target = $queue ? 'concerns.php' : 'concern.php?id=' . rawurlencode($concern);
        $this->run('INSERT INTO notifications (user_id,type,title,message,related_concern_id,target_url,event_key,created_at)
            SELECT users.id,?,?,?,?,?,?,? FROM users WHERE users.id=? AND active=1 AND role IN (\'official\',\'personnel\',\'resident\')
            ON DUPLICATE KEY UPDATE notifications.id=notifications.id', [$type,$title,$message,$concern,$target,hash('sha256',$event),time(),$user]);
    }

    public function officialIds(): array
    {
        return $this->run("SELECT id FROM users WHERE active=1 AND role='official'")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function notifications(array $actor, int $before = 0): array
    {
        $rows = $this->run('SELECT n.id,n.type,n.title,n.message,n.related_concern_id,n.target_url,n.is_read,n.created_at,n.read_at,c.assigned_user_id,c.resident_id
            FROM notifications n LEFT JOIN complaints c ON c.id=n.related_concern_id
            WHERE n.user_id=? AND (?=0 OR n.id<?) ORDER BY n.id DESC LIMIT 30', [$actor['id'],$before,$before])->fetchAll();
        foreach ($rows as &$row) {
            // A historical assignment message does not restore access after reassignment.
            if ($actor['role'] !== 'official' && $row['assigned_user_id'] !== $actor['id'] && $row['resident_id'] !== $actor['id']) $row['target_url'] = 'concerns.php';
            unset($row['assigned_user_id'],$row['resident_id']);
        }
        unset($row);
        return ['items' => $rows, 'unread' => (int)$this->run('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$actor['id']])->fetchColumn()];
    }

    public function markNotificationsRead(string $user, ?int $id): void
    {
        if ($id === null) $this->run('UPDATE notifications SET is_read=1,read_at=? WHERE user_id=? AND is_read=0', [time(),$user]);
        else {
            if (!$this->run('SELECT id FROM notifications WHERE id=? AND user_id=?', [$id,$user])->fetchColumn()) throw new DomainException('Notification unavailable.');
            $this->run('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,?) WHERE id=? AND user_id=?', [time(),$id,$user]);
        }
    }

    // Call inside the normal write transaction. One claim per cooldown, including concurrent requests.
    public function claimAlert(string $key, int $seconds): bool
    {
        $key = hash('sha256',$key);
        $last = $this->run('SELECT last_sent FROM feature_alerts WHERE alert_key=?', [$key])->fetchColumn();
        if ($last !== false && (int)$last > time()-$seconds) return false;
        $this->run('INSERT INTO feature_alerts (alert_key,last_sent) VALUES (?,?) ON DUPLICATE KEY UPDATE last_sent=VALUES(last_sent)', [$key,time()]);
        return true;
    }

    public function dueAssignments(int $until): array
    {
        return $this->run("SELECT id,assigned_user_id,due_at FROM complaints WHERE status IN ('Assigned','In Progress') AND due_at IS NOT NULL AND due_at<=? AND assigned_user_id IS NOT NULL", [$until])->fetchAll();
    }

    public function recordPublicAttempt(string $bucket, int $limit, int $window): bool
    {
        $this->run('DELETE FROM public_attempts WHERE attempted_at < ?', [time() - 3600]);
        $count = $this->run('SELECT COUNT(*) FROM public_attempts WHERE bucket=? AND attempted_at>?', [$bucket, time() - $window])->fetchColumn();
        if ((int)$count >= $limit) return false;
        $this->run('INSERT INTO public_attempts (bucket,attempted_at) VALUES (?,?)', [$bucket, time()]);
        return true;
    }

    public function insertTracking(string $id, string $hash): void
    {
        $this->run('INSERT INTO concern_tracking (complaint_id,token_hash) VALUES (?,?)', [$id, $hash]);
    }

    public function trackedConcern(string $id, string $hash, bool $forUpdate = false): ?array
    {
        return $this->run('SELECT c.payload,c.version FROM complaints c JOIN concern_tracking t ON t.complaint_id=c.id WHERE c.id=? AND t.token_hash=?' . ($forUpdate ? ' FOR UPDATE' : ''), [$id, $hash])->fetch() ?: null;
    }

    public function solutionRules(): array
    {
        return $this->run('SELECT category,concern_type,actions FROM solution_rules ORDER BY category,concern_type')->fetchAll();
    }

    public function saveSolutionRule(string $category, string $type, array $actions): void
    {
        $this->run('INSERT INTO solution_rules (category,concern_type,actions) VALUES (?,?,?) ON DUPLICATE KEY UPDATE actions=VALUES(actions)', [$category, $type, json_encode($actions, JSON_THROW_ON_ERROR)]);
    }

    public function deleteSolutionRule(string $category, string $type): void
    {
        $this->run('DELETE FROM solution_rules WHERE category=? AND concern_type=?', [$category, $type]);
    }

    public function updateAccountIdentity(string $id, string $name, string $email): void
    {
        $this->run('UPDATE users SET name=?,email=? WHERE id=?', [$name, $email, $id]);
    }
    public function __construct(private PDO $connection) {}

    private function run(string $sql, array $values = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    public function transaction(callable $work): mixed
    {
        $this->connection->beginTransaction();
        try {
            // Lock before reads so setup, permissions, IDs and versions see current state.
            $lock = $this->run("SELECT value FROM settings WHERE name='next_id' FOR UPDATE");
            if ($lock->fetchColumn() === false) throw new RuntimeException('Run database/setup.php to initialize storage.');
            $lock->closeCursor();
            $result = $work();
            $this->connection->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $error;
        }
    }

    // Accounts
    public function setupAvailable(): bool
    {
        return !(bool)$this->run("SELECT EXISTS(SELECT 1 FROM users WHERE role='official') OR EXISTS(SELECT 1 FROM settings WHERE name='setup_complete' AND value='1')")->fetchColumn();
    }

    public function completeSetup(): void
    {
        $this->run("INSERT INTO settings(name,value) VALUES('setup_complete','1') ON DUPLICATE KEY UPDATE value='1'");
    }

    public function userCount(): int
    {
        return (int)$this->run('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function user(string $id): array|false
    {
        return $this->run('SELECT id,name,email,role,team,active,auth_version,must_change_password,created_at FROM users WHERE id=?', [$id])->fetch();
    }

    public function users(): array
    {
        return $this->run('SELECT id,name,email,role,team,active,must_change_password,created_at FROM users ORDER BY created_at DESC,name')->fetchAll();
    }

    public function insertUser(string $id, string $name, string $email, string $hash, string $role, string $team, string $createdAt): void
    {
        $this->run('INSERT INTO users(id,name,email,password_hash,role,team,created_at) VALUES(?,?,?,?,?,?,?)', [$id, $name, $email, $hash, $role, $team, $createdAt]);
    }

    public function requirePasswordChange(string $id): void
    {
        $this->run('UPDATE users SET must_change_password=1 WHERE id=?', [$id]);
    }

    public function passwordHash(string $id): string|false
    {
        return $this->run('SELECT password_hash FROM users WHERE id=?', [$id])->fetchColumn();
    }

    public function replacePassword(string $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?', [$hash, $id]);
    }

    public function updateUser(string $id, string $role, string $team, bool $active): void
    {
        $this->run('UPDATE users SET auth_version=auth_version+IF(active<>? OR role<>?,1,0),role=?,team=?,active=? WHERE id=?', [$active ? 1 : 0, $role, $role, $team, $active ? 1 : 0, $id]);
    }

    public function updateProfile(string $id, string $name, string $email, string $hash, bool $revokeSessions): void
    {
        $this->run('UPDATE users SET name=?,email=?,password_hash=?,auth_version=auth_version+? WHERE id=?', [$name, $email, $hash, $revokeSessions ? 1 : 0, $id]);
    }

    public function loginUser(string $email): array|false
    {
        return $this->run('SELECT id,password_hash,active FROM users WHERE email=?', [$email])->fetch();
    }

    // Sign-in throttling
    public function deleteOldLoginAttempts(int $before): void
    {
        $this->run('DELETE FROM login_attempts WHERE attempted_at < ?', [$before]);
    }

    public function loginAttemptCount(string $bucket): int
    {
        return (int)$this->run('SELECT COUNT(*) FROM login_attempts WHERE bucket=?', [$bucket])->fetchColumn();
    }

    public function recordLoginAttempt(string $bucket, int $at): void
    {
        $this->run('INSERT INTO login_attempts(bucket,attempted_at) VALUES(?,?)', [$bucket, $at]);
    }

    public function clearLoginAttempts(string $bucket): void
    {
        $this->run('DELETE FROM login_attempts WHERE bucket=?', [$bucket]);
    }

    // Password recovery
    public function deleteOldResetRequests(int $before): void
    {
        $this->run('DELETE FROM password_reset_requests WHERE requested_at < ?', [$before]);
    }

    public function deleteExpiredResets(int $now): void
    {
        $this->run('DELETE FROM password_resets WHERE expires_at < ? AND (reset_expires_at IS NULL OR reset_expires_at < ?)', [$now, $now]);
    }

    public function resetRequestRate(string $bucket): array
    {
        return $this->run('SELECT COUNT(*) AS total, MAX(requested_at) AS latest FROM password_reset_requests WHERE bucket=?', [$bucket])->fetch();
    }

    public function recordResetRequest(string $bucket, int $at): void
    {
        $this->run('INSERT INTO password_reset_requests(bucket,requested_at) VALUES(?,?)', [$bucket, $at]);
    }

    public function resetUser(string $email): array|false
    {
        return $this->run("SELECT id,email,auth_version FROM users WHERE email=? AND active=1 AND role IN ('resident','official','personnel')", [$email])->fetch();
    }

    public function deleteUserResets(string $userId): void
    {
        $this->run('DELETE FROM password_resets WHERE user_id=?', [$userId]);
    }

    public function insertReset(string $id, string $userId, string $email, int $authVersion, string $hash, int $expiresAt): void
    {
        $this->run('INSERT INTO password_resets(id,user_id,email,auth_version,otp_hash,expires_at) VALUES(?,?,?,?,?,?)', [$id, $userId, $email, $authVersion, $hash, $expiresAt]);
    }

    public function deleteReset(string $id): void
    {
        $this->run('DELETE FROM password_resets WHERE id=?', [$id]);
    }

    public function resetRequest(string $id): array|false
    {
        return $this->run('SELECT r.* FROM password_resets r JOIN users u ON u.id=r.user_id
            WHERE r.id=? AND u.active=1 AND r.auth_version=u.auth_version AND r.email=u.email', [$id])->fetch();
    }

    public function recordResetAttempt(string $id): void
    {
        $this->run('UPDATE password_resets SET attempts=attempts+1 WHERE id=?', [$id]);
    }

    public function grantPasswordReset(string $id, string $tokenHash, int $expiresAt): void
    {
        $this->run('UPDATE password_resets SET reset_token_hash=?,reset_expires_at=?,otp_hash=? WHERE id=?', [$tokenHash, $expiresAt, '', $id]);
    }

    // Complaint persistence
    public function complaint(string $id, bool $forUpdate = false): ?array
    {
        return $this->run('SELECT payload,version FROM complaints WHERE id=?' . ($forUpdate ? ' FOR UPDATE' : ''), [$id])->fetch() ?: null;
    }

    public function primaryConcernId(string $id): ?string
    {
        // The workflow already records this relationship in the concern JSON.
        $value = $this->run("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,'$.linkedPrimaryId')) FROM complaints WHERE id=?", [$id])->fetchColumn();
        return is_string($value) && $value !== '' && $value !== 'null' ? $value : null;
    }

    public function complaints(): array
    {
        return $this->run('SELECT payload,version FROM complaints ORDER BY created_at DESC,id DESC')->fetchAll();
    }

    public function nextComplaintId(): int
    {
        return (int)$this->run("SELECT value FROM settings WHERE name='next_id'")->fetchColumn();
    }

    public function setNextComplaintId(int $nextId): void
    {
        $this->run('UPDATE settings SET value=? WHERE name=?', [(string)$nextId, 'next_id']);
    }

    public function submissionCount(string $userId, string $start, string $end): int
    {
        return (int)$this->run('SELECT COUNT(*) FROM complaints WHERE resident_id=? AND created_at>=? AND created_at<?', [$userId, $start, $end])->fetchColumn();
    }

    public function weeklyComplaints(string $start, string $end): array
    {
        return $this->run("SELECT JSON_OBJECT('id',id,'category',category_name,'concernType',concern_type,
            'keyPoints',JSON_EXTRACT(payload,'$.keyPoints'),'priority',JSON_UNQUOTE(JSON_EXTRACT(payload,'$.priority')),
            'locationDetails',JSON_EXTRACT(payload,'$.locationDetails')) AS payload,version
            FROM complaints WHERE created_at>=? AND created_at<? ORDER BY created_at DESC,id DESC", [$start, $end])->fetchAll();
    }

    public function insertComplaint(array $complaint): void
    {
        $this->run('INSERT INTO complaints(id,resident_id,team,status,version,created_at,updated_at,payload) VALUES(?,?,?,?,?,?,?,?)', [
            $complaint['id'], $complaint['residentId'], $complaint['team'], $complaint['status'], $complaint['version'],
            $complaint['createdAt'], $complaint['updatedAt'], json_encode($complaint, JSON_THROW_ON_ERROR),
        ]);
    }

    public function updateComplaint(array $complaint, ?int $expectedVersion = null): void
    {
        $values = [
            $complaint['team'], $complaint['status'], $complaint['version'], $complaint['updatedAt'],
            json_encode($complaint, JSON_THROW_ON_ERROR), $complaint['id'],
        ];
        if ($expectedVersion !== null) $values[] = $expectedVersion;
        $statement = $this->run('UPDATE complaints SET team=?,status=?,version=?,updated_at=?,payload=? WHERE id=?' . ($expectedVersion !== null ? ' AND version=?' : ''), $values);
        if ($expectedVersion !== null && $statement->rowCount() !== 1) throw new ConflictException('Another user updated this concern. Refresh before saving.');
    }
}

// CLI installation and disposable test database operations.
final class DatabaseMaintenance
{
    private static function requireCli(): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Database maintenance requires the command line.');
    }

    public static function create(string $name, bool $ifNotExists = false): void
    {
        self::requireCli();
        // The connection factory validates the database identifier before interpolation.
        $server = br_database($name, serverOnly: true);
        $server->exec('CREATE DATABASE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . '`' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public static function initialize(PDO $connection): void
    {
        self::requireCli();
        $database = (string)$connection->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $database)) {
            throw new RuntimeException('Select a valid MaintainPro database before running migrations.');
        }
        $lockName = 'maintainpro:migrate:' . substr(hash('sha256', $database), 0, 40);
        $lock = $connection->prepare('SELECT GET_LOCK(?,30)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('Another database migration is already running. Try again shortly.');
        }

        try {
            $connection->exec(self::schema());
            $columns = $connection->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('must_change_password', $columns, true)) {
                $connection->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT NOT NULL DEFAULT 0');
            }
            self::applyMigrations($connection);
            self::seedOfficialSolutions($connection);
        } finally {
            try {
                $release = $connection->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            } catch (Throwable) {
                // Closing the CLI connection also releases the advisory lock.
            }
        }
    }

    /** @return array<string,string> version => absolute file path */
    private static function seedOfficialSolutions(PDO $connection): void
    {
        if ($connection->query("SELECT value FROM settings WHERE name='official_solutions_seeded'")->fetchColumn()!==false) return;
        require_once dirname(__DIR__).'/includes/concern-catalog.php';
        $connection->beginTransaction();
        try {
            $insert=$connection->prepare('INSERT IGNORE INTO official_solution_rules(category,concern_type,keypoint,action_text,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
            foreach (ConcernCatalog::TYPES as $category=>$types) foreach ($types as $type) foreach (ConcernCatalog::POINTS[$category] as $point) {
                foreach (ConcernCatalog::OFFICIAL_SOLUTIONS[$category][$point] as $i=>$action) $insert->execute([$category,$type,$point,$action,$i+1,time(),time()]);
            }
            $connection->exec("INSERT INTO settings(name,value) VALUES('official_solutions_seeded','1')");
            $connection->commit();
        } catch (Throwable $error) { $connection->rollBack(); throw $error; }
    }

    public static function migrationFiles(): array
    {
        $files = glob(__DIR__ . '/migrations/*.sql');
        if ($files === false) throw new RuntimeException('Unable to read database migrations.');
        sort($files, SORT_STRING);
        $migrations = [];
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/\A[0-9]{8}_[a-z0-9_]+\z/', $version)) {
                throw new RuntimeException('Invalid migration filename: ' . basename($file));
            }
            if (isset($migrations[$version])) throw new RuntimeException('Duplicate migration version: ' . $version);
            $migrations[$version] = $file;
        }
        return $migrations;
    }

    public static function migrationsSql(): string
    {
        $sections = [];
        foreach (self::migrationFiles() as $version => $file) {
            $sql = file_get_contents($file);
            if ($sql === false) throw new RuntimeException('Unable to read migration: ' . $version);
            $sections[] = '-- Migration ' . $version . "\n" . rtrim($sql);
        }
        return implode("\n\n", $sections);
    }

    private static function applyMigrations(PDO $connection): void
    {
        $applied = $connection->query('SELECT version,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $record = $connection->prepare('INSERT INTO schema_migrations(version,checksum,applied_at) VALUES(?,?,?)');
        foreach (self::migrationFiles() as $version => $file) {
            $sql = file_get_contents($file);
            if ($sql === false) throw new RuntimeException('Unable to read migration: ' . $version);
            $checksum = hash('sha256', str_replace("\r\n", "\n", $sql));
            if (isset($applied[$version])) {
                if (!self::migrationChecksumMatches($sql,(string)$applied[$version])) {
                    throw new RuntimeException('Applied migration checksum changed: ' . $version . '. Restore the original file and add a new migration.');
                }
                continue;
            }
            if ($version==='20260929_system_upgrade') self::preflightUpgrade($connection);
            $connection->exec($sql);
            $record->execute([$version, $checksum, time()]);
        }
    }

    public static function migrationChecksumMatches(string $sql, string $stored): bool
    {
        // Git on Windows may convert LF to CRLF. Accept only these exact byte
        // variants; do not trim whitespace, remove comments, or rewrite history.
        $lf=str_replace("\r\n","\n",$sql);
        foreach ([$sql,$lf,str_replace("\n","\r\n",$lf)] as $candidate) {
            if (hash_equals($stored,hash('sha256',$candidate))) return true;
        }
        return false;
    }

    private static function preflightUpgrade(PDO $connection): void
    {
        foreach ([['complaints','resident_id','users'],['concern_tracking','complaint_id','complaints'],['notifications','user_id','users'],
            ['notifications','related_concern_id','complaints'],['password_resets','user_id','users'],['audit_logs','actor_id','users'],
            ['concern_evidence','complaint_id','complaints'],['concern_evidence','uploaded_by','users']] as [$table,$column,$parent]) {
            $orphans=(int)$connection->query('SELECT COUNT(*) FROM '.$table.' c LEFT JOIN '.$parent.' p ON p.id=c.'.$column.' WHERE c.'.$column.' IS NOT NULL AND p.id IS NULL')->fetchColumn();
            if ($orphans) throw new RuntimeException('Schema repair stopped: '.$orphans.' orphan reference(s) in '.$table.'.'.$column.'. Reconcile ownership from a trusted backup and rerun setup; no records were deleted.');
        }
        foreach (['complaints'=>'payload','solution_rules'=>'actions'] as $table=>$column) {
            if ((int)$connection->query('SELECT COUNT(*) FROM '.$table.' WHERE NOT JSON_VALID('.$column.')')->fetchColumn()) throw new RuntimeException('Schema repair stopped: invalid JSON in '.$table.'.'.$column.'. Correct the affected data and rerun setup; no records were deleted.');
        }
    }

    public static function insightsMigration(): string
    {
        $sql = file_get_contents(__DIR__ . '/migrations/20260921_staff_insights.sql');
        if ($sql === false) throw new RuntimeException('Unable to read the staff insights migration.');
        return $sql;
    }

    public static function anonymousMigration(): string
    {
        // This is also supplied as an importable SQL migration for existing installs.
        $sql = file_get_contents(__DIR__ . '/migrations/20260921_anonymous_concerns.sql');
        if ($sql === false) throw new RuntimeException('Unable to read the anonymous concern migration.');
        return $sql;
    }

    public static function requireTestDatabase(string $name): void
    {
        self::requireCli();
        if (!preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $name)) {
            throw new RuntimeException('This operation requires a disposable test database.');
        }
    }

    public static function dropTestDatabase(string $name): void
    {
        self::requireTestDatabase($name);
        br_database($name, serverOnly: true)->exec('DROP DATABASE `' . $name . '`');
    }

    public static function schema(): string
    {
        return <<<'SQL'
-- MaintainPro: import into the maintainpro database. No sample accounts or complaints.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('resident', 'official', 'personnel') NOT NULL,
    team VARCHAR(100) NOT NULL DEFAULT '',
    active TINYINT NOT NULL DEFAULT 1,
    auth_version INT NOT NULL DEFAULT 1,
    must_change_password TINYINT NOT NULL DEFAULT 0,
    created_at VARCHAR(35) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    checksum CHAR(64) NOT NULL,
    applied_at BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL,
    auth_version INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT NOT NULL,
    reset_token_hash CHAR(64) DEFAULT NULL,
    reset_expires_at BIGINT DEFAULT NULL,
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    requested_at BIGINT NOT NULL,
    INDEX reset_requests_bucket (bucket, requested_at),
    INDEX reset_requests_time (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaints (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    resident_id VARCHAR(64) NULL,
    team VARCHAR(100) NOT NULL,
    status VARCHAR(64) NOT NULL,
    version INT NOT NULL,
    created_at VARCHAR(35) NOT NULL,
    updated_at VARCHAR(35) NOT NULL,
    payload LONGTEXT NOT NULL,
    INDEX complaints_resident (resident_id),
    INDEX complaints_team (team),
    CONSTRAINT complaints_resident_fk FOREIGN KEY (resident_id) REFERENCES users(id),
    CONSTRAINT complaints_payload_json CHECK (JSON_VALID(payload))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This counter also serializes writes, including first-account setup.
INSERT INTO settings (name, value) VALUES ('next_id', '1')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    attempted_at BIGINT NOT NULL,
    INDEX attempts_bucket (bucket, attempted_at),
    INDEX attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}

// Test fixtures use this same SQL boundary and cannot target the workspace database.
final class DatabaseTestFixtures
{
    public function repeatUpgradeMigration(): void
    {
        $this->connection->exec(file_get_contents(__DIR__.'/migrations/20260929_system_upgrade.sql'));
    }

    public function legacyEvidence(string $id, string $evidenceId, string $photo): void
    {
        $statement=$this->connection->prepare("UPDATE complaints SET payload=JSON_SET(payload,'$.timeline[0].evidenceId',?,'$.timeline[0].photo',?) WHERE id=?");
        $statement->execute([$evidenceId,$photo,$id]);
    }

    public function upgradeConstraints(): array
    {
        return $this->connection->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
    }

    public function invalidPayload(string $id): void
    {
        $stmt=$this->connection->prepare('UPDATE complaints SET payload=? WHERE id=?'); $stmt->execute(['invalid json',$id]);
    }

    public function orphanNotification(): void
    {
        $this->connection->exec("INSERT INTO notifications(user_id,type,title,message,event_key,created_at,target_url) VALUES('missing','test','test','test','test',1,'')");
    }

    public function allUsersInactive(): void
    {
        $this->connection->exec('UPDATE users SET active=0');
    }

    public function removeOfficialRole(): void
    {
        $this->connection->exec("UPDATE users SET role='resident' WHERE role='official'");
    }

    public function failNotifications(bool $enabled): void
    {
        $this->connection->exec('DROP TRIGGER IF EXISTS test_fail_notification');
        if ($enabled) $this->connection->exec("CREATE TRIGGER test_fail_notification BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Isolated transaction failure'");
    }

    public function restoreBackup(string $sql): void
    {
        if ($this->connection->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Restore test requires an empty test database.');
        $this->connection->exec($sql);
    }

    public function ageConcern(string $id, string $date): void
    {
        $statement = $this->connection->prepare("UPDATE complaints SET created_at=?,payload=JSON_SET(payload,'$.createdAt',?) WHERE id=?");
        $statement->execute([$date,$date,$id]);
    }

    public function elapseDeadlineSweep(): void
    {
        $statement = $this->connection->prepare('DELETE FROM feature_alerts WHERE alert_key=?');
        $statement->execute([hash('sha256','deadline-sweep')]);
    }
    public function __construct(private PDO $connection)
    {
        DatabaseMaintenance::requireTestDatabase((string)$connection->query('SELECT DATABASE()')->fetchColumn());
    }

    public function clearResetRate(): void
    {
        $this->connection->exec('DELETE FROM password_reset_requests');
    }

    public function reset(string $id): array|false
    {
        $statement = $this->connection->prepare('SELECT * FROM password_resets WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetch();
    }

    public function resetCount(): int
    {
        return (int)$this->connection->query('SELECT COUNT(*) FROM password_resets')->fetchColumn();
    }

    public function expireResetCodes(): void
    {
        $this->connection->exec('UPDATE password_resets SET expires_at=0');
    }

    public function expireResetGrants(): void
    {
        $this->connection->exec('UPDATE password_resets SET reset_expires_at=0');
    }

    public function elapseResetCooldown(): void
    {
        $this->connection->exec('UPDATE password_reset_requests SET requested_at=requested_at-61');
    }
}
