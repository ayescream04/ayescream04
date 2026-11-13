<?php
/** ORG Portal: JSON feed for the notification bell (latest notifications + unread count) */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/students.php';
require_login('org');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$orgId = (int) current_user()['org_id'];
$out = ['unread' => 0, 'items' => [], 'org' => null];

try {
    if ($orgId) {
        ensure_org_columns($pdo);
        // Organization info for the sidebar profile
        try {
            $o = $pdo->prepare("SELECT org_name, acronym, adviser, president, course_year_sec, email, contact_no, status, created_at, logo FROM organizations WHERE id = ?");
            $o->execute([$orgId]);
        } catch (Throwable $e) {
            $o = $pdo->prepare("SELECT org_name, acronym, adviser, president, email, contact_no, status, created_at FROM organizations WHERE id = ?");
            $o->execute([$orgId]);
        }
        $row = $o->fetch();
        if ($row) {
            $out['org'] = [
                'name'    => $row['org_name'],
                'acronym' => $row['acronym'],
                'adviser' => $row['adviser'],
                'president' => $row['president'],
                'course'  => $row['course_year_sec'] ?? '',
                'email'   => $row['email'],
                'contact' => $row['contact_no'],
                'status'  => $row['status'],
                'since'   => $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '',
                'logo'    => !empty($row['logo']) ? basename($row['logo']) : '',
            ];
        }

        ensure_notifications_table($pdo);

        $c = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE org_id = ? AND is_read = 0");
        $c->execute([$orgId]);
        $out['unread'] = (int) $c->fetchColumn();

        $s = $pdo->prepare("SELECT id, type, title, message, is_read, created_at
                            FROM notifications WHERE org_id = ? ORDER BY id DESC LIMIT 8");
        $s->execute([$orgId]);
        foreach ($s->fetchAll() as $n) {
            $out['items'][] = [
                'id'      => (int) $n['id'],
                'type'    => $n['type'],
                'title'   => $n['title'],
                'message' => $n['message'],
                'is_read' => (int) $n['is_read'],
                'time'    => date('M j, g:i A', strtotime($n['created_at'])),
            ];
        }
    }
} catch (Throwable $e) {
    // return the empty feed; the page itself keeps working
}

echo json_encode($out);