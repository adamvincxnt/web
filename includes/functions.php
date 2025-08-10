<?php
require_once __DIR__ . '/../config/database.php';

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ฟังก์ชันตรวจสอบ Session
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ฟังก์ชันตรวจสอบ Admin
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// ฟังก์ชันส่งข้อมูลไป Discord
function sendToDiscord($webhook_url, $data) {
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($webhook_url, false, $context);
    
    return $result !== FALSE;
}

// ฟังก์ชันส่งข้อความ LINE OA
function sendLineMessage($message) {
    $data = [
        'to' => LINE_ADMIN_USER_ID,
        'messages' => [
            [
                'type' => 'text',
                'text' => $message
            ]
        ]
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n" .
                       "Authorization: Bearer " . LINE_CHANNEL_ACCESS_TOKEN . "\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents('https://api.line.me/v2/bot/message/push', false, $context);
    
    return $result !== FALSE;
}

// ฟังก์ชันสร้าง QR PromptPay
function generatePromptPayQR($amount) {
    $phone = PROMPTPAY_PHONE;
    // ใช้ API สำเร็จรูปสำหรับสร้าง QR PromptPay
    $qr_url = "https://promptpay.io/{$phone}/{$amount}.png";
    return $qr_url;
}

// ฟังก์ชันอัปโหลดไฟล์ไป Discord
function uploadToDiscord($file_path, $webhook_url) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($file_path)
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return $response;
}

// ฟังก์ชันจัดการตะกร้าสินค้า
function addToCart($product_id, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

function removeFromCart($product_id) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

function getCartTotal() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $total = 0;
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $db->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $total += $product['price'] * $quantity;
        }
    }
    
    return $total;
}

// ฟังก์ชันสร้างรหัสออเดอร์
function generateOrderId() {
    return 'ORD' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}
?>