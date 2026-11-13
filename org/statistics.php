<?php
/** ORG Portal: Statistics */
$pageTitle = 'Statistics';
$pageHeader = 'My Organization Statistics';
$pageSubtitle = 'Request outcomes, returned papers and funds collected for your drives.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

$counts = ['pending' => 0, 'approved' => 0, 'denied' => 0];
$st = $pdo->prepare("SELECT status, COUNT(*) c FROM solicitation_requests WHERE org_id = ? GROUP BY status");
$st->execute([$orgId]);
foreach ($st->fetchAll() as $r) { $counts[$r['status']] = (int)$r['c']; }

$f = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(ret.amount_collected),0) total
                    FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id = r.id
                    WHERE r.org_id = ? AND ret.papers_returned = 'yes'");
$f->execute([$orgId]);
$fund = $f->fetch();

$top = $pdo->prepare("SELECT ret.student_name, ret.student_id, COUNT(*) drives, SUM(ret.amount_collected) total
                      FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id = r.id
                      WHERE r.org_id = ? AND ret.papers_returned = 'yes'
                      GROUP BY ret.student_name, ret.student_id ORDER BY total DESC LIMIT 10");
$top->execute([$orgId]);
$topStudents = $top->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card"><div class="kpi-info"><h3><?= $counts['approved'] ?></h3><p>Approved Drives</p></div><div class="kpi-icon icon-forest"><i class="fa-solid fa-file-circle-check"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3><?= (int)$fund['n'] ?></h3><p>Papers Returned</p></div><div class="kpi-icon icon-blue"><i class="fa-solid fa-clipboard-check"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format((float)$fund['total'], 2) ?></h3><p>Funds Collected</p></div><div class="kpi-icon icon-lime"><i class="fa-solid fa-coins"></i></div></div>
</div>
<div class="charts-grid">
    <div class="chart-card"><h3><i class="fa-solid fa-chart-pie"></i> Request Status</h3><canvas id="statusChart" height="220"></canvas></div>
</div>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-star" style="color:var(--lemon-lime);"></i> Top Student Solicitors</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Drives</th><th>Total Returned</th></tr></thead>
            <tbody>
            <?php if (!$topStudents): ?><tr><td colspan="5" class="empty-cell">No student return records yet.</td></tr>
            <?php else: foreach ($topStudents as $i => $s): ?>
                <tr><td><?= $i + 1 ?></td><td><strong><?= htmlspecialchars($s['student_name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($s['student_id'] ?? 'N/A') ?></code></td><td><?= (int)$s['drives'] ?></td>
                    <td><strong>PHP <?= number_format($s['total'], 2) ?></strong></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<script>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: ['Pending','Approved','Denied'],
        datasets: [{ data: [<?= $counts['pending'] ?>, <?= $counts['approved'] ?>, <?= $counts['denied'] ?>],
            backgroundColor: ['#f39c12','#79B425','#e74c3c'], borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
