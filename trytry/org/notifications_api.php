<?php
/** ORG Portal: JSON feed for the notification bell (latest notifications + unread count) */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login('org');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$orgId = (int) current_user()['org_id'];
$out = ['unread' => 0, 'items' => []];

try {
    if ($orgId) {
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
