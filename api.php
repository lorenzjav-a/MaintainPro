<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
function payload(): array
{
    $actor = br_actor();
    if (!$actor) throw new DomainException('Sign in to continue.');
    return [
        'mode' => 'account', 'actor' => $actor,
        'cases' => br_store()->recentConcerns($actor['id'], 50),
        'users' => $actor['is_system_admin'] ? br_store()->users($actor['id']) : [],
        'categories' => array_keys(ConcernCatalog::TYPES), 'legacyCategories' => ComplaintWorkflow::CATEGORIES, 'teams' => ComplaintWorkflow::TEAMS,
        'statuses' => ComplaintWorkflow::STATUSES, 'priorities' => ComplaintWorkflow::PRIORITIES,
        'submissionAllowance' => br_store()->submissionAllowance($actor['id']),
    ];
}
header('Content-Type: application/json; charset=utf-8');
try {
    $actor = br_actor();
    if (!$actor) {
        http_response_code(401);
        throw new DomainException('Your session has ended or your account is inactive. Sign in to continue.');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($actor['must_change_password']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Change your temporary password before continuing.', 'redirect' => 'login.php?view=change-password']);
        exit;
    }
    if ($method === 'GET') {
        if (($_GET['view'] ?? '') === 'session') {
            // Same-origin, no-store response; never send this through JSONP/CORS.
            echo json_encode(['userId' => $actor['id'], 'authVersion' => (int)$actor['auth_version'], 'csrf' => $_SESSION['br_csrf']], JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['view'] ?? '') === 'notifications') {
            echo json_encode(br_store()->notifications($actor['id']), JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['view'] ?? '') === 'chat_overview') {
            $query=is_string($_GET['q'] ?? null)?$_GET['q']:'';
            echo json_encode(br_store()->chatOverview($actor['id'],$query),JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['view'] ?? '') === 'concern_messages') {
            $id=is_string($_GET['id'] ?? null)?$_GET['id']:'';
            $after=filter_var($_GET['after'] ?? 0,FILTER_VALIDATE_INT);
            $before=isset($_GET['before'])?filter_var($_GET['before'],FILTER_VALIDATE_INT):null;
            if (!preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/',$id) || $after===false || $after<0 || ($before!==null && ($before===false || $before<1))) throw new DomainException('Invalid conversation request.');
            echo json_encode($before===null?br_store()->concernConversation($actor['id'],$id,$after):br_store()->olderConcernConversation($actor['id'],$id,$before),JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['view'] ?? '') === 'staff_messages') {
            $id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT);
            $after=filter_var($_GET['after'] ?? 0,FILTER_VALIDATE_INT);
            $before=isset($_GET['before'])?filter_var($_GET['before'],FILTER_VALIDATE_INT):null;
            if (!$id || $id<1 || $after===false || $after<0 || ($before!==null && ($before===false || $before<1))) throw new DomainException('Invalid conversation request.');
            echo json_encode($before===null?br_store()->staffConversationPage($actor['id'],$id,$after):br_store()->olderStaffConversation($actor['id'],$id,$before),JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['view'] ?? '') === 'weekly') {
            if ($actor['role'] !== 'official') { http_response_code(403); throw new DomainException('Only barangay officials can view weekly analytics.'); }
            echo json_encode(br_store()->weeklyConcerns($actor['id']), JSON_THROW_ON_ERROR);
            exit;
        }
        if (($_GET['export'] ?? '') === 'csv') {
            if ($actor['role'] !== 'official') {
                http_response_code(403);
                throw new DomainException('Only a barangay official can export reports.');
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="maintainpro-concerns.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Concern ID', 'Title', 'Category', 'Location', 'Status', 'Priority', 'Assigned team', 'Submitted', 'Resident suggestion (legacy)', 'Official recommendation', 'Resolution', 'Review feedback', 'Reopen count', 'Concern type', 'Key points', 'Assigned personnel', 'Purok / Sitio', 'Street', 'Exact area', 'Landmark', 'Temporary resident guidance', 'Submitted by', 'Submitter role']);
            foreach (br_store()->visibleConcerns($actor['id']) as $c) {
                $row = [$c['id'], $c['title'], $c['category'], $c['location'], $c['status'], $c['priority'], $c['team'], $c['createdAt'], $c['suggestion'] ?? '', $c['recommendation'], $c['resolution']['notes'] ?? '', $c['feedback'], (string)$c['reopenCount'], $c['concernType'] ?? '', implode('; ', $c['keyPoints'] ?? []), $c['assignedName'] ?? '', $c['locationDetails']['purok'] ?? '', $c['locationDetails']['street'] ?? '', $c['locationDetails']['exactArea'] ?? '', $c['locationDetails']['landmark'] ?? '', implode(' | ', $c['residentGuidance'] ?? [])];
                $row[] = $c['resident'];
                $row[] = $c['submitterRole'] ?? '';
                fputcsv($out, array_map(fn($v) => preg_match('/^[\s]*[=+\-@\t\r\n]/u', $v) ? "'" . $v : $v, $row));
            }
            fclose($out);
            exit;
        }
        echo json_encode(payload(), JSON_THROW_ON_ERROR);
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        throw new DomainException('Use GET or POST.');
    }
    if (!hash_equals($_SESSION['br_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'csrf_expired', 'error' => 'Your session changed after this page was opened. Refresh the security token before saving.']);
        exit;
    }
    $raw = file_get_contents('php://input', false, null, 0, 1600001);
    if (strlen($raw) > 1600000) throw new DomainException('This request is too large. Use a photo smaller than 1 MB.');
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new DomainException('Invalid request.');
    $action = $input['action'] ?? '';
    $data = $input['data'] ?? [];
    if (!is_string($action) || !is_array($data)) throw new DomainException('Invalid request.');
    $id = is_string($input['id'] ?? null) ? $input['id'] : '';
    $createdAccount = null;
    if (in_array($action, ['read_notification','read_all_notifications'], true)) {
        if ($action === 'read_notification' && !preg_match('/\A[1-9][0-9]{0,17}\z/', $id)) throw new DomainException('Invalid notification.');
        br_store()->readNotifications($actor['id'], $action === 'read_all_notifications' ? null : (int)$id);
        echo json_encode(['ok' => true] + br_store()->notifications($actor['id']), JSON_THROW_ON_ERROR);
        exit;
    }
    if (in_array($action, ['switch_role', 'reset'], true)) {
        http_response_code(403);
        throw new DomainException('This action is unavailable. Your account determines your role.');
    } elseif (in_array($action, ['create_user', 'update_user', 'profile'], true)) {
        if (in_array($action, ['create_user', 'update_user'], true)) br_store()->confirmPassword($actor['id'], $data['current_password'] ?? null);
        if ($action === 'create_user') $createdAccount = br_store()->createUser($actor['id'], $data);
        if ($action === 'update_user') br_store()->updateUser($actor['id'], $id, $data);
        if ($action === 'profile') {
            $requestedEmail=is_string($data['email'] ?? null)?strtolower(trim($data['email'])):'';
            if ($requestedEmail!==strtolower($actor['email'])) {
                require_once __DIR__ . '/includes/mail.php';
                $mailer=br_mailer();
                $challenge=br_store()->requestEmailChange($actor['id'],$requestedEmail,$data['current_password'] ?? null,
                    fn(string $recipient,string $code)=>br_send_email_change_code($mailer,$recipient,$code));
                $data['email']=$actor['email'];
                $_SESSION['br_email_change_challenge']=$challenge;
            }
            br_store()->updateProfile($actor['id'], $data);
            $_SESSION['br_auth_version'] = (int)br_store()->user($actor['id'])['auth_version'];
            session_regenerate_id(true);
            if (isset($challenge)) {
                echo json_encode(['ok'=>true,'redirect'=>'profile.php?email-verification=pending'],JSON_THROW_ON_ERROR);
                exit;
            }
        }
    } elseif ($action === 'verify_email_change') {
        $challenge=is_string($_SESSION['br_email_change_challenge'] ?? null)?$_SESSION['br_email_change_challenge']:'';
        $changed=br_store()->verifyEmailChange($actor['id'],$challenge,$data['code'] ?? null);
        unset($_SESSION['br_email_change_challenge']);
        require_once __DIR__ . '/includes/mail.php';
        br_send_email_changed_notice($changed['old_email'],$changed['name'],$changed['new_email']);
        br_enter_account($changed['user']);
        echo json_encode(['ok'=>true,'redirect'=>'profile.php?email-verification=complete'],JSON_THROW_ON_ERROR);
        exit;
    } elseif ($action === 'save_official_rules') {
        br_store()->saveOfficialRules($actor['id'],$data);
    } elseif (in_array($action,['create_action_plan','update_action_plan'],true)) {
        if ($action==='update_action_plan' && !preg_match('/\A[1-9][0-9]{0,17}\z/',$id)) throw new DomainException('Invalid action plan.');
        $id=(string)br_store()->saveActionPlan($actor['id'],$action==='create_action_plan'?0:(int)$id,$data);
    } elseif ($action==='personnel_action_plan') {
        if (!preg_match('/\A[1-9][0-9]{0,17}\z/',$id)) throw new DomainException('Invalid action plan.');
        br_store()->savePersonnelActionPlan($actor['id'],(int)$id,$data);
    } elseif ($action==='send_concern_message') {
        if (!preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/',$id)) throw new DomainException('Invalid concern.');
        $message=br_store()->sendConcernMessage($actor['id'],$id,$data);
        echo json_encode(['ok'=>true]+$message,JSON_THROW_ON_ERROR);
        exit;
    } elseif ($action==='create_staff_conversation') {
        $conversationId=br_store()->createStaffConversation($actor['id'],$data);
        echo json_encode(['ok'=>true,'id'=>$conversationId,'redirect'=>'messages.php?id='.$conversationId],JSON_THROW_ON_ERROR);
        exit;
    } elseif ($action==='send_staff_message') {
        $conversationId=filter_var($id,FILTER_VALIDATE_INT);
        if (!$conversationId || $conversationId<1) throw new DomainException('Invalid staff conversation.');
        $message=br_store()->sendStaffMessage($actor['id'],$conversationId,$data);
        echo json_encode(['ok'=>true]+$message,JSON_THROW_ON_ERROR);
        exit;
    } elseif ($action==='dismiss_duplicate') {
        br_store()->dismissDuplicate($actor['id'],$id,$data);
    } elseif ($action==='submit_feedback') {
        br_store()->submitFeedback($actor['id'],$id,$data);
    } elseif (in_array($action, ['save_rule', 'reset_rule'], true)) {
        br_store()->saveRule($actor['id'], $data, $action === 'reset_rule');
    } elseif (in_array($action, ['create_location', 'update_location', 'toggle_location'], true)) {
        if ($action === 'create_location') br_store()->createLocation($actor['id'], $data);
        else {
            if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $id)) throw new DomainException('Invalid location.');
            br_store()->updateLocation($actor['id'], (int)$id, $data, $action === 'toggle_location');
        }
    } else {
        $id = br_store()->mutate($actor['id'], $action, $id, $data, $input['version'] ?? null);
    }
    if ($action === 'assign') {
        // Assignment is already committed; SMTP failure must not roll it back.
        require_once __DIR__ . '/includes/mail.php';
        $assignedCase = br_store()->concernForActor($actor['id'], $id);
        $personnel = $assignedCase ? br_store()->user($assignedCase['assignedUserId']) : null;
        $sent = $personnel && br_send_assignment($personnel, $assignedCase);
        $_SESSION['assignment_notice'] = $sent ? 'Assignment saved. Personnel email sent.' : 'Assignment saved, but the personnel email could not be sent. Check the mail configuration and notify the assigned person.';
    }
    $response = ['ok' => true, 'id' => $id];
    if ($createdAccount !== null) $response['created_account'] = $createdAccount;
    if ($action === 'assign') $response['notification_sent'] = (bool)$sent;
    echo json_encode($response, JSON_THROW_ON_ERROR);
} catch (ConflictException $e) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (DomainException | JsonException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'The request could not be completed. Please try again.']);
}
