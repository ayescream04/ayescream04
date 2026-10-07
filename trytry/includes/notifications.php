<?php
/**
 * Shared helpers for organization notifications.
 * Used by: admin/pending.php (creates them), org/index.php and org/notifications.php (show them).
 */

/** Creates the notifications table the first time it is needed. */
function ensure_notifications_table($pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_id INT NOT NULL,
        request_id INT NULL,
        type ENUM('approved','denied') NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_org_read (org_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Adds a notification for the organization that owns a request (call right after approve/deny). */
function notify_request_decision($pdo, int $requestId, string $status, string $approver, string $remarks): void
{
    ensure_notifications_table($pdo);

    $q = $pdo->prepare("SELECT org_id, control_no, event_name FROM solicitation_requests WHERE id = ?");
    $q->execute([$requestId]);
    $req = $q->fetch();
    if (!$req) { return; }

    if ($status === 'approved') {
        $title   = 'Request approved: ' . $req['control_no'];
        $message = 'Your solicitation request "' . $req['event_name'] . '" (' . $req['control_no'] . ') was APPROVED by ' . $approver
                 . '. You can now view and download the solicitation letter under Approved & DOCX.'
                 . ($remarks !== '' ? ' Remarks: ' . $remarks : '');
    } else {
        $title   = 'Request denied: ' . $req['control_no'];
        $message = 'Your solicitation request "' . $req['event_name'] . '" (' . $req['control_no'] . ') was DENIED by ' . $approver
                 . '. Reason: ' . ($remarks !== '' ? $remarks : 'No reason provided.');
    }

    $pdo->prepare("INSERT INTO notifications (org_id, request_id, type, title, message) VALUES (?,?,?,?,?)")
        ->execute([(int)$req['org_id'], $requestId, $status, $title, $message]);
}