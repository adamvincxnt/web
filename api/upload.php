<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตรวจสอบไฟล์ที่ต้อง include
$required_files = [
    '../config/database.php',
    '../includes/functions.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => "Required file not found: {$file}"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบการล็อกอิน
session_start();
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบก่อน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // รับข้อมูลจากฟอร์ม
    $order_id = isset($_POST['order_id']) ? sanitize($_POST['order_id']) : '';
    
    // Validation
    if (empty($order_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบรหัสคำสั่งซื้อ'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ตรวจสอบว่า order มีอยู่จริงและเป็นของผู้ใช้คนนี้
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบคำสั่งซื้อหรือคุณไม่มีสิทธิ์เข้าถึง'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ตรวจสอบสถานะ order
    if ($order['status'] !== 'pending') {
        echo json_encode([
            'success' => false,
            'message' => 'คำสั่งซื้อนี้ไม่สามารถอัปโหลดสลิปได้แล้ว'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ตรวจสอบไฟล์ที่อัปโหลด
    if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาเลือกไฟล์สลิปการโอน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $file = $_FILES['slip'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // ตรวจสอบประเภทไฟล์
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาอัปโหลดไฟล์รูปภาพ (JPG, JPEG, PNG) เท่านั้น'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_size) {
        echo json_encode([
            'success' => false,
            'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // อัปโหลดไฟล์ไป Discord
    $discord_url = uploadToDiscord($file, $order_id);
    
    if (!$discord_url) {
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // อัปเดตฐานข้อมูล
    $stmt = $db->prepare("UPDATE orders SET 
        payment_slip_url = ?, 
        status = 'payment_uploaded',
        updated_at = NOW()
        WHERE order_number = ?");
    
    $result = $stmt->execute([$discord_url, $order_id]);
    
    if ($result) {
        // ส่งแจ้งเตือนไป Discord (ใช้ DISCORD_WEBHOOK_RECEIPTS)
        if (defined('DISCORD_WEBHOOK_RECEIPTS') && DISCORD_WEBHOOK_RECEIPTS) {
            $order_total = number_format($order['total_amount'], 2);
            $discord_data = [
                'content' => "🧾 **มีสลิปการโอนใหม่!**\n\n" .
                            "📋 **หมายเลขออเดอร์:** {$order_id}\n" .
                            "👤 **ลูกค้า:** {$order['customer_name']}\n" .
                            "📱 **เบอร์โทร:** {$order['customer_phone']}\n" .
                            "🏢 **ตึก:** {$order['customer_building']}\n" .
                            "💰 **ยอดเงิน:** ฿{$order_total}\n" .
                            "⏰ **เวลา:** " . date('d/m/Y H:i:s') . "\n\n" .
                            "📎 **สลิปการโอน:**",
                'embeds' => [
                    [
                        'title' => 'สลิปการโอนเงิน',
                        'description' => "ออเดอร์: {$order_id}",
                        'image' => [
                            'url' => $discord_url
                        ],
                        'color' => 0x00ff00,
                        'timestamp' => date('c')
                    ]
                ]
            ];
            
            sendToDiscord(DISCORD_WEBHOOK_RECEIPTS, $discord_data);
        }
        
        // ส่งแจ้งเตือนไป LINE (ถ้ามี function)
        if (function_exists('sendLineMessage')) {
            $line_message = "🧾 มีสลิปการโอนใหม่!\n\n" .
                           "📋 ออเดอร์: {$order_id}\n" .
                           "👤 ลูกค้า: {$order['customer_name']}\n" .
                           "💰 ยอดเงิน: ฿{$order_total}\n" .
                           "🏢 ตึก: {$order['customer_building']}\n\n" .
                           "กรุณาตรวจสอบสลิปใน Discord";
            
            sendLineMessage($line_message);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดสลิปการโอนเรียบร้อยแล้ว กรุณารอการตรวจสอบจากแอดมิน',
            'order_id' => $order_id,
            'status' => 'payment_uploaded'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดระบบฐานข้อมูล'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Database error in upload.php: " . $e->getMessage());
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดระบบ'
    ], JSON_UNESCAPED_UNICODE);
    error_log("General error in upload.php: " . $e->getMessage());
}

/**
 * อัปโหลดไฟล์ไป Discord และคืนค่า URL
 */
function uploadToDiscord($file, $order_id) {
    // ใช้ DISCORD_WEBHOOK_RECEIPTS สำหรับอัปโหลดสลิป
    if (!defined('DISCORD_WEBHOOK_RECEIPTS') || !DISCORD_WEBHOOK_RECEIPTS) {
        error_log("Discord webhook not configured");
        return false;
    }
    
    $webhook_url = DISCORD_WEBHOOK_RECEIPTS;
    
    // สร้างชื่อไฟล์ใหม่
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "slip_{$order_id}_" . time() . ".{$extension}";
    
    // เตรียมข้อมูลสำหรับส่ง
    $curl_file = new CURLFile($file['tmp_name'], $file['type'], $filename);
    
    $data = [
        'content' => "📎 สลิปการโอนสำหรับออเดอร์: {$order_id}",
        'file' => $curl_file
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $response_data = json_decode($response, true);
        if (isset($response_data['attachments'][0]['url'])) {
            return $response_data['attachments'][0]['url'];
        }
    }
    
    error_log("Discord upload failed: HTTP {$http_code}, Response: {$response}");
    return false;
}
?>