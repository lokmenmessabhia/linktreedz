<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance()->getConnection();

// Fetch Wilaya Data for names
$wilaya_json = @file_get_contents('wilaya.json');
$wilaya_data = $wilaya_json ? json_decode($wilaya_json, true) : [];
$wilayas = [];
if (isset($wilaya_data['wilayas'])) {
    foreach ($wilaya_data['wilayas'] as $w) {
        $wilayas[$w['wilaya_id']] = [
            'ar' => $w['wilaya_name_arabic'],
            'fr' => $w['wilaya_name_latin']
        ];
    }
}

// Fetch Orders
$stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalCount = count($orders);
$pendingCount = 0;
$totalRevenue = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'pending') $pendingCount++;
    if ($o['status'] !== 'cancelled') {
        $cleanPrice = preg_replace('/[^0-9]/', '', $o['total_price']);
        $totalRevenue += (int)$cleanPrice;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management | Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-style.css?v=21">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main">
        <?php include 'topbar.php'; ?>
        
        <div class="container-fluid" style="padding: 1.5rem;">
            <div class="header" style="margin-bottom: 2rem;">
                <div class="header-title">
                    <a href="dashboard" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; margin-top: 0.5rem;">Order Management</h1>
                </div>
                <div class="header-actions" style="display: flex; gap: 0.75rem;">
                    <button onclick="copyTableForSheets()" class="btn-premium copy">
                        <i class="fa-solid fa-copy"></i> Copy for Sheets
                    </button>
                    <button onclick="exportToExcel()" class="btn-premium export">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </button>
                    <a href="bulk-generate" class="btn-premium" style="background: var(--primary); color: #fff; border: none;">
                        <i class="fa-solid fa-qrcode"></i> Bulk QR
                    </a>
                </div>
            </div>

            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $totalCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="value" style="color: #fbbf24;"><?php echo $pendingCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value" style="color: var(--primary);"><?php echo number_format($totalRevenue); ?> DA</div>
                </div>
            </div>

            <div class="table-section">
                <div class="filter-header" style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <div class="search-box" style="flex: 1; min-width: 250px; position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--text-dim); pointer-events: none;"></i>
                        <input type="text" id="orderSearch" class="input-glass" placeholder="Search by ID, Customer, or Wilaya..." style="padding-left: 3.2rem;">
                    </div>
                    <select id="statusFilter" class="select-glass" style="width: auto; min-width: 200px;">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending Orders</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="shipped">Shipped</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Location</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr class="order-row" data-id="<?php echo $order['id']; ?>">
                                <td style="font-weight: 800; font-family: 'Outfit', sans-serif;">#<?php echo $order['id']; ?></td>
                                <td>
                                    <div style="font-weight: 700;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <a href="https://wa.me/213<?php echo preg_replace('/[^0-9]/', '', $order['phone']); ?>" target="_blank" class="whatsapp-link">
                                        <i class="fa-brands fa-whatsapp"></i> <?php echo $order['phone']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $w_id = $order['wilaya_id'];
                                    $w_name = isset($wilayas[$w_id]) ? $wilayas[$w_id]['fr'] : $w_id;
                                    ?>
                                    <div style="font-weight: 700;"><?php echo $w_name; ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-dim);"><?php echo htmlspecialchars($order['commune']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem;">Pack: <?php echo $order['pack']; ?></div>
                                    <div class="price-badge" style="color: var(--primary); font-size: 1rem;"><?php echo $order['total_price']; ?> DA</div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <select class="select-glass" style="font-size: 0.8rem; padding: 0.5rem 2rem 0.5rem 1rem; width: auto; height: auto;" onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)">
                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button class="btn-action btn-delete" onclick="deleteOrder(<?php echo $order['id']; ?>, this)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script>
        async function updateOrderStatus(orderId, status) {
            try {
                const response = await fetch('api-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', order_id: orderId, status: status })
                });
                const res = await response.json();
                if (res.success) {
                    showToast('Order #' + orderId + ' status updated to ' + status, 'success');
                    const badge = document.querySelector('[data-id="' + orderId + '"] .status-badge');
                    if (badge) {
                        badge.className = 'status-badge status-' + status;
                        badge.innerText = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                } else {
                    showToast(res.error || 'Update failed', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }
        }

        async function deleteOrder(orderId, btn) {
            if (!confirm('Are you sure you want to delete order #' + orderId + '?')) return;
            try {
                const response = await fetch('api-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_order', order_id: orderId })
                });
                const res = await response.json();
                if (res.success) {
                    showToast('Order #' + orderId + ' deleted', 'warning');
                    const row = btn.closest('.order-row');
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                } else {
                    showToast(res.error || 'Delete failed', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }
        }

        // Search & Filter
        const searchInput = document.getElementById('orderSearch');
        const statusFilter = document.getElementById('statusFilter');
        
        function runFilters() {
            const search = searchInput.value.toLowerCase();
            const status = statusFilter.value.toLowerCase();
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowStatus = row.querySelector('.status-badge').innerText.toLowerCase();
                const matchesSearch = text.includes(search);
                const matchesStatus = status === '' || rowStatus === status;
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }
        
        searchInput.addEventListener('input', runFilters);
        statusFilter.addEventListener('change', runFilters);

        function exportToExcel() {
            const table = document.querySelector(".premium-table");
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, "Linky_Orders_Export.xlsx");
        }

        function copyTableForSheets() {
             const rows = document.querySelectorAll('.order-row');
             let content = "Order ID\tCustomer\tWilaya\tCommune\tPack\tPrice\tStatus\n";
             rows.forEach(row => {
                 if (row.style.display === 'none') return;
                 const id = row.cells[0].innerText;
                 const customer = row.cells[1].querySelector('div').innerText;
                 const wilaya = row.cells[2].querySelector('div').innerText;
                 const commune = row.cells[2].querySelector('div:nth-child(2)').innerText;
                 const details = row.cells[3].innerText;
                 const status = row.cells[4].innerText;
                 content += `${id}\t${customer}\t${wilaya}\t${commune}\t${details}\t${status}\n`;
             });
             navigator.clipboard.writeText(content).then(() => {
                 showToast('Table copied for Google Sheets!', 'success');
             });
        }
    
        </script>
    </main>
</body>
</html>
