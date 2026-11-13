<?php
/**
 * Shared helpers: student solicitors (letter recipients) and return records with photo proof.
 * Student records live in the existing `solicitation_returns` table, so the admin audit,
 * funds and statistics pages keep working. Extra columns are added automatically.
 */

/** Adds the new columns the first time they are needed. */
function ensure_student_columns($pdo)
{
    try {
        $have = [];
        foreach ($pdo->query("SHOW COLUMNS FROM solicitation_returns")->fetchAll() as $c) { $have[$c['Field']] = true; }
        if (empty($have['target_amount'])) { $pdo->exec("ALTER TABLE solicitation_returns ADD COLUMN target_amount DECIMAL(12,2) NOT NULL DEFAULT 0"); }
        if (empty($have['proof_photo']))   { $pdo->exec("ALTER TABLE solicitation_returns ADD COLUMN proof_photo VARCHAR(255) NULL DEFAULT NULL"); }
        if (empty($have['year_section']))  { $pdo->exec("ALTER TABLE solicitation_returns ADD COLUMN year_section VARCHAR(50) NULL DEFAULT NULL"); }
        if (empty($have['control_no']))    { $pdo->exec("ALTER TABLE solicitation_returns ADD COLUMN control_no VARCHAR(40) NULL DEFAULT NULL"); }

        // organizations.logo (used in the letter header)
        if (!$pdo->query("SHOW COLUMNS FROM organizations LIKE 'logo'")->fetch()) {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) NULL DEFAULT NULL");
        }

        ensure_org_columns($pdo);

        // Give a control number to students that do not have one yet (older records)
        backfill_student_control_nos($pdo);
    } catch (PDOException $e) { /* surfaced by the page queries if it really failed */ }
}

/** Makes sure the organizations table has the columns used by the org profile. */
function ensure_org_columns($pdo)
{
    try {
        if (!$pdo->query("SHOW COLUMNS FROM organizations LIKE 'logo'")->fetch()) {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) NULL DEFAULT NULL");
        }
        if (!$pdo->query("SHOW COLUMNS FROM organizations LIKE 'course_year_sec'")->fetch()) {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN course_year_sec VARCHAR(100) NULL DEFAULT NULL");
        }
    } catch (PDOException $e) { /* surfaced by the page queries if it really failed */ }
}

/**
 * Next control number of a student for a drive: <request control no>-01, -02, ...
 * e.g. SOL-2026-0003-01. Numbers are never reused, even if a student is removed.
 */
function next_student_control_no($pdo, $requestId)
{
    $q = $pdo->prepare("SELECT control_no FROM solicitation_requests WHERE id = ?");
    $q->execute([(int)$requestId]);
    $prefix = (string) $q->fetchColumn();

    $e = $pdo->prepare("SELECT control_no FROM solicitation_returns WHERE request_id = ? AND control_no IS NOT NULL AND control_no <> ''");
    $e->execute([(int)$requestId]);
    $max = 0;
    foreach ($e->fetchAll(PDO::FETCH_COLUMN) as $cn) {
        if (preg_match('/-(\d+)$/', $cn, $m)) { $max = max($max, (int)$m[1]); }
    }
    return $prefix . '-' . str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
}

/** Fills in control numbers for student rows that have none. */
function backfill_student_control_nos($pdo)
{
    $rows = $pdo->query("SELECT id, request_id FROM solicitation_returns WHERE control_no IS NULL OR control_no = '' ORDER BY request_id, id")->fetchAll();
    if (!$rows) { return; }
    $upd = $pdo->prepare("UPDATE solicitation_returns SET control_no = ? WHERE id = ?");
    foreach ($rows as $r) {
        $upd->execute([next_student_control_no($pdo, $r['request_id']), $r['id']]);
    }
}

/** Approved request that belongs to this organization (with org info for the letter), or false. */
function get_org_request($pdo, $orgId, $requestId)
{
    $q = $pdo->prepare("SELECT r.*, o.org_name, o.acronym, o.president, o.course_year_sec, o.adviser, o.email, o.contact_no, o.logo AS org_logo
                        FROM solicitation_requests r JOIN organizations o ON r.org_id = o.id
                        WHERE r.id = ? AND r.org_id = ? AND r.status = 'approved'");
    $q->execute([(int)$requestId, (int)$orgId]);
    return $q->fetch();
}

/** Folder where proof photos are saved. */
function proof_dir()
{
    return __DIR__ . '/../assets/uploads/return_proofs/';
}

/**
 * Validates and saves an uploaded proof photo (PNG/JPG, max 4 MB).
 * Returns the saved file name, or null and fills $error.
 */
function save_proof_photo($file, $returnId, &$error)
{
    $error = '';
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Photo upload failed. Please try a smaller image.'; return null; }
    if ($file['size'] > 4 * 1024 * 1024) { $error = 'The photo must be 4 MB or smaller.'; return null; }

    $info  = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'];
    if (!$info || !isset($types[$info[2]])) { $error = 'The photo must be a PNG or JPG image.'; return null; }

    $dir = proof_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $error = 'Could not create the photo folder.'; return null; }

    $name = 'proof_' . (int)$returnId . '_' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) { $error = 'Could not save the uploaded photo.'; return null; }
    return $name;
}