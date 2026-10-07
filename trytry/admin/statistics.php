<?php
/**
 * Admin: Statistics
 * Statistics based on returned solicitation, which org and student solicited 
 * had returned papers with funds successfully
 */

$pageTitle = 'Statistics';
$pageHeader = 'Governance Statistics & Analytics';
$pageSubtitle = 'Data insights on solicitation drives, org performance, return compliance, and fund collection outcomes.';

require_once __DIR__ . '/../config/auth.php';
require_login('admin');

// Overall Stats
$totalPending  = $pdo->query("SELECT COUNT(*) FROM solicitation_requests WHERE status='pending'")->fetchColumn();
$totalApproved = $pdo->query("SELECT COUNT(*) FROM solicitation_requests WHERE status='approved'")->fetchColumn();
$totalDenied   = $pdo->query("SELECT COUNT(*) FROM solicitation_requests WHERE status='denied'")->fetchColumn();
$totalReturned = $pdo->query("SELECT COUNT(*) FROM solicitation_returns WHERE papers_returned='yes'")->fetchColumn();
$totalPending2 = $pdo->query("SELECT COUNT(*) FROM solicitation_returns WHERE papers_returned='no'")->fetchColumn();
$totalFunds    = $pdo->query("SELECT COALESCE(SUM(amount_collected),0) FROM solicitation_returns WHERE papers_returned='yes'")->fetchColumn();
$totalOrgs     = $pdo->query("SELECT COUNT(*) FROM organizations WHERE status='active'")->fetchColumn();
$totalAudited  = $pdo->query("SELECT COUNT(*) FROM solicitation_returns WHERE audit_status='audited'")->fetchColumn();

// Per-Org Statistics
$orgStats = $pdo->query("SELECT o.org_name, o.acronym,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='approved') as approved,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='pending') as pending,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='denied') as denied,
    (SELECT COUNT(*) FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id=r.id WHERE r.org_id=o.id AND ret.papers_returned='yes') as returned,
    (SELECT COALESCE(SUM(ret.amount_collected),0) FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id=r.id WHERE r.org_id=o.id AND ret.papers_returned='yes') as funds_collected
    FROM organizations o WHERE o.status='active' ORDER BY funds_collected DESC")->fetchAll();

// Top students who returned with funds
$topStudents = $pdo->query("SELECT ret.student_name, ret.student_id, o.acronym,
    COUNT(*) as drives, SUM(ret.amount_collected) as total
    FROM solicitation_returns ret
    JOIN solicitation_requests r ON ret.request_id=r.id
    JOIN organizations o ON r.org_id=o.id
    WHERE ret.papers_returned='yes'
    GROUP BY ret.student_name, ret.student_id, o.acronym
    ORDER BY total DESC
    LIMIT 10")->fetchAll();

// Monthly trend (last 6 months)
$monthlyData = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 
    COUNT(*) as requests,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved
    FROM solicitation_requests 
    GROUP BY month 
    ORDER BY month DESC 
    LIMIT 6")->fetchAll();
$monthlyData = array_reverse($monthlyData);

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<!-- KPI Summary -->
<div class="kpi-grid" style="margin-bottom:28px;">
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $totalOrgs ?></h3><p>Active Organizations</p></div>
        <div class="kpi-icon icon-forest"><i class="fa-solid fa-sitemap"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $totalApproved ?></h3><p>Approved Drives</p></div>
        <div class="kpi-icon icon-lime"><i class="fa-solid fa-file-circle-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $totalReturned ?></h3><p>Papers Returned</p></div>
        <div class="kpi-icon icon-blue"><i class="fa-solid fa-clipboard-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3>PHP <?= number_format($totalFunds,2) ?></h3><p>Total Funds Collected</p></div>
        <div class="kpi-icon icon-yellow"><i class="fa-solid fa-coins"></i></div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <!-- Request Status Donut -->
    <div class="chart-card">
        <h3><i class="fa-solid fa-chart-pie"></i> Request Status Distribution</h3>
        <canvas id="statusChart" height="220"></canvas>
    </div>

    <!-- Solicitation Return Rate Donut -->
    <div class="chart-card">
        <h3><i class="fa-solid fa-rotate-left"></i> Solicitation Paper Return Rate</h3>
        <canvas id="returnChart" height="220"></canvas>
    </div>

    <!-- Monthly Trend -->
    <div class="chart-card" style="grid-column: 1 / -1;">
        <h3><i class="fa-solid fa-chart-line"></i> Monthly Solicitation Trend</h3>
        <canvas id="trendChart" height="120"></canvas>
    </div>
</div>

<!-- Per-Org Stats Table -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-trophy" style="color:var(--lemon-lime);"></i> Organization Performance</div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Approved Drives</th>
                        <th>Pending</th>
                        <th>Denied</th>
                        <th>Papers Returned</th>
                        <th>Funds Collected</th>
                        <th>Return Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgStats as $i => $o): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($o['acronym']) ?></strong><br>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($o['org_name']) ?></small>
                        </td>
                        <td><span class="badge badge-approved"><?= $o['approved'] ?></span></td>
                        <td><span class="badge badge-pending"><?= $o['pending'] ?></span></td>
                        <td><span class="badge badge-denied"><?= $o['denied'] ?></span></td>
                        <td><strong style="color:var(--forest-green);"><?= $o['returned'] ?></strong> solicitor(s)</td>
                        <td><strong>PHP <?= number_format($o['funds_collected'],2) ?></strong></td>
                        <td>
                            <?php $pct = $o['approved'] > 0 ? min(100, round(($o['returned'] / max(1,$o['approved'])) * 100)) : 0; ?>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="flex:1; background:#e9ecef; border-radius:10px; height:8px;">
                                    <div style="width:<?= $pct ?>%; background:var(--lemon-lime); height:8px; border-radius:10px;"></div>
                                </div>
                                <span style="font-size:0.82rem; font-weight:700; color:var(--forest-green);"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top Students who Returned Papers with Funds -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-star" style="color:var(--lemon-lime);"></i> Top Students — Returned Solicitation Papers with Funds Successfully</div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Solicitor</th>
                        <th>Student ID</th>
                        <th>Organization</th>
                        <th>Drives Participated</th>
                        <th>Total Funds Returned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topStudents)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No student return records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topStudents as $i => $s): ?>
                        <tr>
                            <td>
                                <?php if ($i === 0): ?>
                                    <span style="font-size:1.2rem;">🥇</span>
                                <?php elseif ($i === 1): ?>
                                    <span style="font-size:1.2rem;">🥈</span>
                                <?php elseif ($i === 2): ?>
                                    <span style="font-size:1.2rem;">🥉</span>
                                <?php else: ?>
                                    <strong style="color:var(--text-muted);"><?= $i+1 ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($s['student_name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($s['student_id'] ?? 'N/A') ?></code></td>
                            <td><span class="badge badge-approved"><?= htmlspecialchars($s['acronym']) ?></span></td>
                            <td><?= $s['drives'] ?> drive(s)</td>
                            <td><strong style="color:var(--forest-green);">PHP <?= number_format($s['total'],2) ?></strong></td>
                            <td><span class="badge badge-approved"><i class="fa-solid fa-check-circle"></i> Returned w/ Funds</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Status Donut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Approved', 'Denied'],
        datasets: [{
            data: [<?= $totalPending ?>, <?= $totalApproved ?>, <?= $totalDenied ?>],
            backgroundColor: ['#f39c12','#79B425','#e74c3c'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Return Rate Donut
new Chart(document.getElementById('returnChart'), {
    type: 'doughnut',
    data: {
        labels: ['Returned w/ Funds', 'Pending Return'],
        datasets: [{
            data: [<?= $totalReturned ?>, <?= $totalPending2 ?>],
            backgroundColor: ['#1D6517','#dbe7da'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Monthly Trend
const months = <?= json_encode(array_column($monthlyData, 'month')) ?>;
const requests = <?= json_encode(array_map('intval', array_column($monthlyData, 'requests'))) ?>;
const approved = <?= json_encode(array_map('intval', array_column($monthlyData, 'approved'))) ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Total Requests',
                data: requests,
                backgroundColor: 'rgba(85,166,195,0.6)',
                borderColor: '#55A6C3',
                borderWidth: 1
            },
            {
                label: 'Approved',
                data: approved,
                backgroundColor: 'rgba(121,180,37,0.7)',
                borderColor: '#79B425',
                borderWidth: 1,
                type: 'line',
                fill: false,
                pointRadius: 5,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
