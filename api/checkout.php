<?php
session_start();
require_once '../.env.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบตะกร้าสินค้า
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ตะกร้าสินค้าว่าง'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // รับข้อมูลจากฟอร์ม
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $customer_line_id = sanitize($_POST['customer_line_id'] ?? '');
    $customer_building = sanitize($_POST['customer_building'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? 'promptpay');
    $total_amount = floatval($_POST['total_amount'] ?? 0);

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($customer_name)) {
        throw new Exception('กรุณากรอกชื่อ-นามสกุล');
    }

    if (empty($customer_phone) || !preg_match('/^[0-9]{10}$/', $customer_phone)) {
        throw new Exception('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก');
    }

    if (empty($customer_line_id)) {
        throw new Exception('กรุณากรอก LINE ID');
    }

    if (empty($customer_building)) {
        throw new Exception('กรุณากรอกตึก/หอพัก');
    }

    if ($total_amount <= 0) {
        throw new Exception('ยอดเงินไม่ถูกต้อง');
    }

    // คำนวณยอดรวมจริงจากฐานข้อมูล
    $calculated_total = 0;
    $order_items = [];

    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $db->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception('สินค้าบางรายการไม่พร้อมจำหน่าย');
        }

        if ($product['stock'] < $quantity) {
            throw new Exception('สินค้า ' . $product['name'] . ' มีสต็อกไม่เพียงพอ');
        }

        $item_total = $product['price'] * $quantity;
        $calculated_total += $item_total;

        $order_items[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'total' => $item_total
        ];
    }

    // ตรวจสอบยอดเงิน
    if (abs($calculated_total - $total_amount) > 0.01) {
        throw new Exception('ยอดเงินไม่ตรงกับการคำนวณ');
    }

    // เริ่ม Transaction
    $db->beginTransaction();

    // สร้างรหัสออเดอร์
    $order_number = generateOrderId();

    // สร้าง QR Code PromptPay
    $qr_code_url = '';
    if ($payment_method === 'promptpay') {
        $qr_code_url = generatePromptPayQR($total_amount);
        if (!$qr_code_url) {
            throw new Exception('ไม่สามารถสร้าง QR Code ได้');
        }
    }

    // บันทึกออเดอร์
    $stmt = $db->prepare("
        INSERT INTO orders (
            order_number, user_id, customer_name, customer_phone, 
            customer_line_id, customer_building, total_amount, 
            status, payment_method, qr_code_url, notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $order_number,
        $_SESSION['user_id'],
        $customer_name,
        $customer_phone,
        $customer_line_id,
        $customer_building,
        $total_amount,
        $payment_method,
        $qr_code_url,
        $notes
    ]);

    $order_id = $db->lastInsertId();

    // บันทึกรายการสินค้าในออเดอร์
    $stmt = $db->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, price, quantity, total) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($order_items as $item) {
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['total']
        ]);

        // ลดสต็อกสินค้า
        $update_stock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update_stock->execute([$item['quantity'], $item['product_id']]);
    }

    // Commit Transaction
    $db->commit();

    // ส่งแจ้งเตือนไป Discord
    $discord_data = [
        'embeds' => [
            [
                'title' => '🛒 คำสั่งซื้อใหม่',
                'color' => 0x00ff00,
                'fields' => [
                    [
                        'name' => 'เลขที่คำสั่งซื้อ',
                        'value' => $order_number,
                        'inline' => true
                    ],
                    [
                        'name' => 'ลูกค้า',
                        'value' => $customer_name,
                        'inline' => true
                    ],
                    [
                        'name' => 'เบอร์โทร',
                        'value' => $customer_phone,
                        'inline' => true
                    ],
                    [
                        'name' => 'LINE ID',
                        'value' => $customer_line_id,
                        'inline' => true
                    ],
                    [
                        'name' => 'ที่อยู่',
                        'value' => $customer_building,
                        'inline' => true
                    ],
                    [
                        'name' => 'ยอดรวม',
                        'value' => '฿' . number_format($total_amount, 2),
                        'inline' => true
                    ]
                ],
                'footer' => [
                    'text' => 'สถานะ: รอชำระเงิน | ' . date('d/m/Y H:i:s')
                ]
            ]
        ]
    ];

    // ส่งไป Discord ทั้ง 3 ห้อง
    if (defined('DISCORD_WEBHOOK_ORDERS')) {
        sendToDiscord(DISCORD_WEBHOOK_ORDERS, $discord_data);
    }
    if (defined('DISCORD_WEBHOOK_ADMIN')) {
        sendToDiscord(DISCORD_WEBHOOK_ADMIN, $discord_data);
    }
    if (defined('DISCORD_WEBHOOK_GENERAL')) {
        sendToDiscord(DISCORD_WEBHOOK_GENERAL, $discord_data);
    }

    // ส่งแจ้งเตือนไป LINE Admin
    $line_message = "🛒 คำสั่งซื้อใหม่\n\n";
    $line_message .= "เลขที่: {$order_number}\n";
    $line_message .= "ลูกค้า: {$customer_name}\n";
    $line_message .= "เบอร์: {$customer_phone}\n";
    $line_message .= "LINE: {$customer_line_id}\n";
    $line_message .= "ที่อยู่: {$customer_building}\n";
    $line_message .= "ยอดรวม: ฿" . number_format($total_amount, 2) . "\n";
    $line_message .= "สถานะ: รอชำระเงิน\n\n";
    
    $item_list = "รายการสินค้า:\n";
    foreach ($order_items as $item) {
        $item_list .= "• {$item['name']} x{$item['quantity']} = ฿" . number_format($item['total'], 2) . "\n";
    }
    $line_message .= $item_list;

    if ($notes) {
        $line_message .= "\nหมายเหตุ: {$notes}";
    }

    sendLineMessage($line_message);

    // ล้างตะกร้าสินค้า
    unset($_SESSION['cart']);

    // ส่งผลลัพธ์กลับ
    echo json_encode([
        'success' => true,
        'message' => 'สั่งซื้อสำเร็จ',
        'order_id' => $order_id,
        'order_number' => $order_number,
        'qr_code_url' => $qr_code_url,
        'total_amount' => $total_amount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Rollback Transaction ถ้ามี
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Checkout Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Rollback Transaction ถ้ามี
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Database Error in Checkout: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล'
    ], JSON_UNESCAPED_UNICODE);
}
?>