<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    header("Location: login");
    exit;
}

$db = Database::getInstance()->getConnection();

// Fetch Wilaya Data
$wilaya_json = file_get_contents('wilaya.json');
$wilaya_data = json_decode($wilaya_json, true);
$wilayas = [];
if (isset($wilaya_data['wilayas'])) {
    foreach ($wilaya_data['wilayas'] as $w) {
        $wilayas[$w['wilaya_id']] = $w['wilaya_name_latin'];
    }
}

// Fetch Orders
$stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// File name
$filename = "linky_orders_" . date('Y-m-d') . ".xls";

// Set Headers for Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Export XML format (Excel 2003 XML) which is very robust for UTF-8 and formatting
echo "<?xml version=\"1.0\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
echo " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
echo " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
echo " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
echo " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

echo " <Styles>\n";
echo "  <Style ss:ID=\"sHeader\">\n";
echo "   <Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/>\n";
echo "   <Interior ss:Color=\"#00853f\" ss:Pattern=\"Solid\"/>\n";
echo "   <Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/>\n";
echo "  </Style>\n";
echo "  <Style ss:ID=\"sData\">\n";
echo "   <Alignment ss:Vertical=\"Center\"/>\n";
echo "  </Style>\n";
echo " </Styles>\n";

echo " <Worksheet ss:Name=\"Orders\">\n";
echo "  <Table>\n";
echo "   <Column ss:Width=\"60\"/>\n"; // ID
echo "   <Column ss:Width=\"120\"/>\n"; // Date
echo "   <Column ss:Width=\"150\"/>\n"; // Customer
echo "   <Column ss:Width=\"100\"/>\n"; // Phone
echo "   <Column ss:Width=\"100\"/>\n"; // Wilaya
echo "   <Column ss:Width=\"100\"/>\n"; // Commune
echo "   <Column ss:Width=\"100\"/>\n"; // Pack
echo "   <Column ss:Width=\"100\"/>\n"; // Delivery
echo "   <Column ss:Width=\"80\"/>\n"; // Price
echo "   <Column ss:Width=\"80\"/>\n"; // Status

// Header Row
echo "   <Row ss:Height=\"25\">\n";
foreach (['Order ID', 'Date', 'Customer Name', 'Phone', 'Wilaya', 'Commune', 'Pack', 'Delivery Type', 'Total Price', 'Status'] as $h) {
    echo "    <Cell ss:StyleID=\"sHeader\"><Data ss:Type=\"String\">$h</Data></Cell>\n";
}
echo "   </Row>\n";

// Data Rows
foreach ($orders as $row) {
    $wilayaName = $wilayas[$row['wilaya_id']] ?? "Wilaya " . $row['wilaya_id'];
    echo "   <Row ss:Height=\"20\">\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"Number\">{$row['id']}</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['created_at']) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['customer_name']) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['phone']) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($wilayaName) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['commune']) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['pack']) . "</Data></Cell>\n";
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . htmlspecialchars($row['delivery_type']) . "</Data></Cell>\n";
    
    // Clean price for number type
    $cleanPrice = (int)preg_replace('/[^0-9]/', '', $row['total_price']);
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"Number\">$cleanPrice</Data></Cell>\n";
    
    echo "    <Cell ss:StyleID=\"sData\"><Data ss:Type=\"String\">" . ucfirst($row['status']) . "</Data></Cell>\n";
    echo "   </Row>\n";
}

echo "  </Table>\n";
echo " </Worksheet>\n";
echo "</Workbook>\n";
exit;
?>
