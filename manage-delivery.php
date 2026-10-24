<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance()->getConnection();

$message = '';
$delivery_prices_file = 'delivery_prices.json';

// Fetch current prices from JSON (as per existing logic)
$prices = @json_decode(file_get_contents($delivery_prices_file), true) ?: [];

// Fetch Wilaya List
$wilaya_json = @file_get_contents('wilaya.json');
$wilaya_data = $wilaya_json ? json_decode($wilaya_json, true) : [];
$wilayas = isset($wilaya_data['wilayas']) ? $wilaya_data['wilayas'] : [];

// --- POST Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_prices'])) {
        foreach ($_POST['prices'] as $wid => $data) {
            $prices[$wid] = ['home' => (int)$data['home'], 'stopdesk' => (int)$data['stopdesk']];
        }
        file_put_contents($delivery_prices_file, json_encode($prices, JSON_PRETTY_PRINT));
        $message = 'Prices updated!';
    } elseif (isset($_POST['import_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle); // Skip header
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 4) {
                        $prices[$data[0]] = ['home' => (int)$data[2], 'stopdesk' => (int)$data[3]];
                    }
                }
                fclose($handle);
                file_put_contents($delivery_prices_file, json_encode($prices, JSON_PRETTY_PRINT));
                $message = 'CSV Import Success!';
            }
        }
    }
}

// Action: Export CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=delivery_prices.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Wilaya', 'Home Price', 'Stopdesk Price']);
    foreach ($wilayas as $w) {
        $wid = $w['wilaya_id'];
        $home = $prices[$wid]['home'] ?? 900;
        $stop = $prices[$wid]['stopdesk'] ?? 300;
        fputcsv($output, [$wid, $w['wilaya_name_latin'], $home, $stop]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Prices | Linky Dz</title>
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
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; margin-top: 0.5rem;">Delivery Management</h1>
                </div>
                <div class="header-actions">
                    <a href="manage-delivery?export_csv=1" class="btn-premium export">
                        <i class="fa-solid fa-file-export"></i> Export CSV
                    </a>
                </div>
            </div>

            <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 2.5rem;">
                <div class="stat-card">
                    <h3>Import Prices</h3>
                    <form method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
                        <input type="hidden" name="import_csv" value="1">
                        <label for="csv_file" class="btn-premium" style="width: 100%; justify-content: center; cursor: pointer; background: #f1f5f9; color: #475569;">
                            <i class="fa-solid fa-file-csv"></i> Choose File
                        </label>
                        <input type="file" id="csv_file" name="csv_file" style="display: none;" onchange="document.getElementById('fileName').innerText = this.files[0].name">
                        <div id="fileName" style="font-size: 0.7rem; color: var(--text-dim); text-align: center; margin: 0.5rem 0;">No file chosen</div>
                        <button type="submit" class="btn-premium" style="width: 100%; justify-content: center; background: var(--primary); color: #fff; border: none;">
                            Update Prices
                        </button>
                    </form>
                </div>
                <div class="stat-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <div style="font-size: 3rem; font-weight: 800; color: var(--primary); font-family: 'Outfit', sans-serif;">
                        <?php echo count($wilayas); ?>
                    </div>
                    <div style="color: var(--text-dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.8rem;">Wilayas Covered</div>
                </div>
            </div>

            <?php if ($message): ?>
                <script>document.addEventListener('DOMContentLoaded', () => showToast("<?php echo $message; ?>", "success"));</script>
            <?php endif; ?>

            <div class="table-section">
                <form method="POST">
                    <input type="hidden" name="save_prices" value="1">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <div class="search-box" style="flex: 1;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="priceSearch" placeholder="Filter wilayas...">
                        </div>
                        <button type="submit" class="btn-premium" style="background: var(--primary); color: #fff; border: none; padding: 0.75rem 2rem;">
                            <i class="fa-solid fa-floppy-disk"></i> Save All Changes
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Wilaya Name</th>
                                    <th>To Home (DA)</th>
                                    <th>To Stopdesk (DA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wilayas as $w): 
                                    $id = $w['wilaya_id'];
                                    $hp = $prices[$id]['home'] ?? 900;
                                    $sp = $prices[$id]['stopdesk'] ?? 300;
                                ?>
                                <tr class="price-row">
                                    <td style="font-weight: 800;">#<?php echo $id; ?></td>
                                    <td style="font-weight: 700;"><?php echo $w['wilaya_name_latin']; ?></td>
                                    <td>
                                        <input type="number" name="prices[<?php echo $id; ?>][home]" value="<?php echo $hp; ?>" class="select-glass" style="width: 120px; text-align: center;">
                                    </td>
                                    <td>
                                        <input type="number" name="prices[<?php echo $id; ?>][stopdesk]" value="<?php echo $sp; ?>" class="select-glass" style="width: 120px; text-align: center;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.getElementById('priceSearch').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.price-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        </script>
    </main>
</body>
</html>
