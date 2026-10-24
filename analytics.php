<?php
session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Check if admin is logged in
$auth = new Auth();
$auth->requireAuth();

// Get analytics data
$db = Database::getInstance()->getConnection();

// Get filter ID
$pageId = $_GET['id'] ?? null;
$whereClause = "";
$whereClauseLinks = "WHERE l.is_active = 1";
$params = [];

if ($pageId && is_numeric($pageId)) {
    $whereClause = "WHERE lp.id = ?";
    $whereClauseLinks = "WHERE l.is_active = 1 AND lp.id = ?";
    $params = [$pageId];
    
    // Fetch profile name for the filtered view
    $profileStmt = $db->prepare("SELECT profile_name FROM link_pages WHERE id = ?");
    $profileStmt->execute([$pageId]);
    $filterProfile = $profileStmt->fetchColumn();
}

// Get total clicks per link
$stmt = $db->prepare("
    SELECT 
        l.id,
        l.title,
        l.url,
        lp.profile_name,
        lp.token_id,
        t.token,
        COUNT(la.id) as total_clicks,
        MAX(la.clicked_at) as last_click
    FROM links l
    LEFT JOIN link_analytics la ON l.id = la.link_id
    LEFT JOIN link_pages lp ON l.link_page_id = lp.id
    LEFT JOIN tokens t ON lp.token_id = t.id
    $whereClauseLinks
    GROUP BY l.id
    ORDER BY total_clicks DESC, l.id DESC
");
$stmt->execute($params);
$linkStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get page view stats per token (if filtered, this will only have one result)
$pageViewStmt = $db->prepare("
    SELECT 
        lp.id,
        lp.profile_name,
        t.token,
        COUNT(pa.id) as total_views,
        MAX(pa.viewed_at) as last_view,
        COUNT(DISTINCT pa.ip_address) as unique_visitors
    FROM link_pages lp
    LEFT JOIN page_analytics pa ON lp.id = pa.link_page_id
    LEFT JOIN tokens t ON lp.token_id = t.id
    $whereClause
    GROUP BY lp.id
    ORDER BY total_views DESC
");
$pageViewStmt->execute($params);
$pageViewStats = $pageViewStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent clicks (last 50)
$recentStmt = $db->prepare("
    SELECT 
        la.*,
        l.title as link_title,
        lp.profile_name
    FROM link_analytics la
    JOIN links l ON la.link_id = l.id
    JOIN link_pages lp ON l.link_page_id = lp.id
    $whereClause
    ORDER BY la.clicked_at DESC
    LIMIT 50
");
$recentStmt->execute($params);
$recentClicks = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent page views (last 50)
$recentPageViewsStmt = $db->prepare("
    SELECT 
        pa.*,
        lp.profile_name,
        t.token
    FROM page_analytics pa
    JOIN link_pages lp ON pa.link_page_id = lp.id
    JOIN tokens t ON lp.token_id = t.id
    $whereClause
    ORDER BY pa.viewed_at DESC
    LIMIT 50
");
$recentPageViewsStmt->execute($params);
$recentPageViews = $recentPageViewsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get total stats
$totalClicksStmt = $db->prepare("
    SELECT COUNT(la.id) as total 
    FROM link_analytics la
    JOIN links l ON la.link_id = l.id
    JOIN link_pages lp ON l.link_page_id = lp.id
    $whereClause
");
$totalClicksStmt->execute($params);
$totalClicks = $totalClicksStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$totalPageViewsStmt = $db->prepare("
    SELECT COUNT(pa.id) as total 
    FROM page_analytics pa
    JOIN link_pages lp ON pa.link_page_id = lp.id
    $whereClause
");
$totalPageViewsStmt->execute($params);
$totalPageViews = $totalPageViewsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Chart Data: Views over the last 7 days
$timeFilter = "pa.viewed_at >= DATE(NOW()) - INTERVAL 6 DAY";
$chartWhere = $whereClause ? $whereClause . " AND " . $timeFilter : "WHERE " . $timeFilter;

$chartDatesStmt = $db->prepare("
    SELECT DATE(pa.viewed_at) as view_date, COUNT(*) as views 
    FROM page_analytics pa 
    JOIN link_pages lp ON pa.link_page_id = lp.id
    $chartWhere
    GROUP BY DATE(pa.viewed_at)
    ORDER BY view_date ASC
");
$chartDatesStmt->execute($params);
$viewsOverTime = $chartDatesStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data: Device Type Breakdown
$deviceStmt = $db->prepare("
    SELECT IFNULL(NULLIF(device_type, ''), 'Unknown') as label, COUNT(*) as value 
    FROM page_analytics pa
    JOIN link_pages lp ON pa.link_page_id = lp.id
    $whereClause
    GROUP BY label
");
$deviceStmt->execute($params);
$deviceData = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data: Browser Breakdown
$browserStmt = $db->prepare("
    SELECT IFNULL(NULLIF(browser, ''), 'Unknown') as label, COUNT(*) as value 
    FROM page_analytics pa
    JOIN link_pages lp ON pa.link_page_id = lp.id
    $whereClause
    GROUP BY label
");
$browserStmt->execute($params);
$browserData = $browserStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data: Country Breakdown
$countryStmt = $db->prepare("
    SELECT IFNULL(NULLIF(country, ''), 'Unknown') as label, COUNT(*) as value 
    FROM page_analytics pa
    JOIN link_pages lp ON pa.link_page_id = lp.id
    $whereClause
    GROUP BY label
");
$countryStmt->execute($params);
$countryData = $countryStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-style.css?v=21">
    <style>
        .chart-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-sm);
            height: 350px;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 1024px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main">
        <?php include 'topbar.php'; ?>
        
        <div class="container-fluid" style="padding: 1.5rem;">
            <div class="header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div class="header-title">
                    <a href="dashboard" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; margin-top: 0.5rem;">
                        <?php echo isset($filterProfile) ? htmlspecialchars($filterProfile) . " Insights" : "Global Analytics"; ?>
                    </h1>
                </div>
                <?php if (isset($filterProfile)): ?>
                <a href="analytics" class="btn-premium" style="background: var(--primary-glow); color: var(--primary); border: none;">
                    <i class="fa-solid fa-globe"></i> Reset Global View
                </a>
                <?php endif; ?>
            </div>

            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <div class="stat-card">
                    <h3>Page Views</h3>
                    <div class="value" style="color: var(--primary);"><?php echo number_format($totalPageViews); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Link Clicks</h3>
                    <div class="value" style="color: var(--accent);"><?php echo number_format($totalClicks); ?></div>
                </div>
                <div class="stat-card">
                    <h3>CTR (Avg)</h3>
                    <div class="value" style="color: #6366f1;">
                        <?php 
                        $ctr = ($totalPageViews > 0) ? ($totalClicks / $totalPageViews) * 100 : 0;
                        echo number_format($ctr, 1) . '%';
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Active Profiles</h3>
                    <div class="value"><?php echo count($pageViewStats); ?></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-container">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1.5rem; font-weight: 800;">Traffic Over Time</h3>
                    <div style="height: 250px;">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1.5rem; font-weight: 800;">Device Breakdown</h3>
                    <div style="height: 250px;">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 2rem;">
                 <div class="chart-container">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1.5rem; font-weight: 800;">Top Browsers</h3>
                    <div style="height: 250px;">
                        <canvas id="browserChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1.5rem; font-weight: 800;">Geo Locations</h3>
                    <div style="height: 250px;">
                        <canvas id="countryChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-section" style="margin-bottom: 2rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-weight: 800; margin-bottom: 1.5rem;">Profile Performance</h3>
                <div class="table-responsive">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Views</th>
                                <th>Visitors</th>
                                <th>Last Activity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageViewStats as $stat): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700;"><?php echo htmlspecialchars($stat['profile_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-dim);">token: <?php echo $stat['token']; ?></div>
                                </td>
                                <td><span class="status-badge status-shipped"><?php echo $stat['total_views']; ?> views</span></td>
                                <td><?php echo $stat['unique_visitors']; ?> unique</td>
                                <td>
                                    <?php echo $stat['last_view'] ? date('M j, Y g:i A', strtotime($stat['last_view'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <a href="analytics?id=<?php echo $stat['id']; ?>" class="btn-action" title="Deep Dive">
                                        <i class="fa-solid fa-chart-pie"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-section">
                <h3 style="font-family: 'Outfit', sans-serif; font-weight: 800; margin-bottom: 1.5rem;">Recent Interaction Log</h3>
                <div class="table-responsive">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Profile</th>
                                <th>IP Address</th>
                                <th>Environment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClicks as $click): ?>
                            <tr>
                                <td style="font-size: 0.8rem;"><?php echo date('M j, g:i A', strtotime($click['clicked_at'])); ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--accent);">
                                        <i class="fa-solid fa-mouse-pointer"></i> Click: <?php echo htmlspecialchars($click['link_title']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($click['profile_name']); ?></td>
                                <td><code style="font-size: 0.75rem;"><?php echo htmlspecialchars($click['ip_address'] ?? 'N/A'); ?></code></td>
                                <td style="font-size: 0.75rem; color: var(--text-dim);">
                                    <?php echo htmlspecialchars($click['referrer'] ?? 'Direct'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Chart global defaults
            Chart.defaults.color = '#64748b';
            Chart.defaults.font.family = "'Inter', sans-serif";
            
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            };

            // --- Traffic Line Chart ---
            const trafficData = <?php echo json_encode($viewsOverTime); ?>;
            new Chart(document.getElementById('trafficChart'), {
                type: 'line',
                data: {
                    labels: trafficData.map(d => d.view_date),
                    datasets: [{
                        label: 'Views',
                        data: trafficData.map(d => d.views),
                        borderColor: '#00853f',
                        backgroundColor: 'rgba(0, 133, 63, 0.05)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#00853f',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // --- Pie/Doughnut Creator ---
            function createDoughnut(ctxId, dataItems) {
                new Chart(document.getElementById(ctxId), {
                    type: 'doughnut',
                    data: {
                        labels: dataItems.map(d => d.label),
                        datasets: [{
                            data: dataItems.map(d => d.value),
                            backgroundColor: ['#6366f1', '#a855f7', '#00853f', '#d21034', '#0ea5e9', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 20
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 10, usePointStyle: true, font: { size: 10 } } } },
                        cutout: '70%'
                    }
                });
            }

            createDoughnut('deviceChart', <?php echo json_encode($deviceData); ?>);
            createDoughnut('browserChart', <?php echo json_encode($browserData); ?>);
            createDoughnut('countryChart', <?php echo json_encode($countryData); ?>);
        </script>
    </main>
</body>
</html>
