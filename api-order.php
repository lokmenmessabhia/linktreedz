<?php
/**
 * API: Order Processing & Management
 * Handles order creation (Landing), status updates (Admin), and deletions (Admin).
 */

header('Content-Type: application/json');
require_once 'config.php';
require_once 'database.php';

// Get input
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? 'create'; 
$db = Database::getInstance()->getConnection();

try {
    // 1. ADMIN ACTIONS (Security required)
    if ($action === 'update_status' || $action === 'delete_order') {
        require_once 'auth.php';
        (new Auth())->requireAuth();

        if ($action === 'update_status') {
            $id = (int)$data['order_id'];
            $status = $data['status'];
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true, 'message' => 'Order status updated']);
            exit;
        }

        if ($action === 'delete_order') {
            $id = (int)$data['order_id'];
            $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
            exit;
        }
    }

    // 2. LANDING PAGE ACTIONS (Order Creation)
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $wilaya_id = $data['wilaya'] ?? '';
    $baldia = $data['baldia'] ?? '';
    $pack = $data['selected_pack'] ?? '';
    $delivery_type = $data['delivery_method'] ?? '';
    $lang = $data['lang'] ?? 'ar';

    if (empty($name) || empty($phone) || empty($wilaya_id) || empty($baldia)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Ensure orders table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_name` varchar(255) NOT NULL,
      `phone` varchar(20) NOT NULL,
      `wilaya_id` int(11) NOT NULL,
      `commune` varchar(100) NOT NULL,
      `pack` varchar(50) NOT NULL,
      `delivery_type` varchar(50) NOT NULL,
      `total_price` varchar(50) NOT NULL,
      `status` enum('pending','confirmed','shipped','cancelled') DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Fetch dynamic pricing
    $stmtS = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('price_pack_all', 'price_pack_4')");
    $settings = $stmtS->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $price_all = (int)($settings['price_pack_all'] ?? 2000);
    $price_4 = (int)($settings['price_pack_4'] ?? 2900);

    $packPrice = ($pack === '4 Links') ? $price_4 : $price_all;
    $deliveryPrice = ($delivery_type === 'Stopdesk') ? 300 : 900;
    $total_price = $packPrice + $deliveryPrice;

    $stmt = $db->prepare("INSERT INTO orders (customer_name, phone, wilaya_id, commune, pack, delivery_type, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $wilaya_id, $baldia, $pack, $delivery_type, $total_price]);
    $order_id = $db->lastInsertId();

    // Telegram Notification
    try {
        $wilaya_name = $wilaya_id;
        $wilaya_json = @file_get_contents('wilaya.json');
        if ($wilaya_json) {
            $wilaya_data = json_decode($wilaya_json, true);
            if (isset($wilaya_data['wilayas'])) {
                foreach ($wilaya_data['wilayas'] as $w) {
                    if ($w['wilaya_id'] == $wilaya_id) {
                        $wilaya_name = ($lang === 'ar' ? $w['wilaya_name_arabic'] : $w['wilaya_name_latin']);
                        break;
                    }
                }
            }
        }

        $telegram_message = "🚀 *New Order #$order_id*\n\n";
        $telegram_message .= "👤 *Customer:* $name\n";
        $telegram_message .= "📞 *Phone:* $phone\n";
        $telegram_message .= "📍 *Location:* $wilaya_name, $baldia\n";
        $telegram_message .= "📦 *Pack:* $pack\n";
        $telegram_message .= "🚚 *Delivery:* $delivery_type\n";
        $telegram_message .= "💰 *Total:* $total_price " . ($lang === 'ar' ? 'د.ج' : 'DA') . "\n";
        $telegram_message .= "\n⏰ " . date('Y-m-d H:i:s');
        
        $telegram_token = TELEGRAM_BOT_TOKEN;
        $chat_id = TELEGRAM_CHAT_ID;
        $telegram_url = "https://api.telegram.org/bot$telegram_token/sendMessage";
        
        $ch = curl_init($telegram_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'text' => $telegram_message,
            'parse_mode' => 'Markdown'
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
