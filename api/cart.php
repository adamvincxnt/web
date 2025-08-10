<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// เริ่ม session
session_start();

// ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ฟังก์ชันส่ง Response JSON
function sendResponse($success = true, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ฟังก์ชันตรวจสอบสินค้าในฐานข้อมูล
function getProduct($id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Database error in getProduct: " . $e->getMessage());
        return false;
    }
}

// ฟังก์ชันคำนวณยอดรวมตะกร้า
function calculateCartTotal() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

// ฟังก์ชันนับจำนวนสินค้าในตะกร้า
function getCartCount() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

// รับ Action จาก Request
$action = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

// กรณีไม่มี action
if (empty($action)) {
    sendResponse(false, 'ไม่พบ Action ที่ต้องการ', null, 400);
}

// จัดการ Action ต่างๆ
switch ($action) {
    
    // เพิ่มสินค้าในตะกร้า
    case 'add':
        $product_id = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);
        $quantity = (int)($input['quantity'] ?? $_POST['quantity'] ?? 1);
        
        if ($product_id <= 0) {
            sendResponse(false, 'ไม่พบรหัสสินค้า', null, 400);
        }
        
        if ($quantity <= 0) {
            sendResponse(false, 'จำนวนสินค้าต้องมากกว่า 0', null, 400);
        }
        
        // ตรวจสอบสินค้าในฐานข้อมูล
        $product = getProduct($product_id);
        if (!$product) {
            sendResponse(false, 'ไม่พบสินค้าที่ต้องการ', null, 404);
        }
        
        // ตรวจสอบสต็อก
        if ($product['stock'] < $quantity) {
            sendResponse(false, 'สินค้าเหลือไม่เพียงพอ (คงเหลือ: ' . $product['stock'] . ' ชิ้น)', null, 400);
        }
        
        // เตรียมข้อมูลสินค้า
        $cart_item = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'image_url' => $product['image_url'],
            'category' => $product['category'],
            'quantity' => $quantity
        ];
        
        // สร้างตะกร้าถ้าไม่มี
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // ตรวจสอบว่ามีสินค้านี้ในตะกร้าแล้วหรือไม่
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $new_quantity = $item['quantity'] + $quantity;
                
                // ตรวจสอบสต็อกอีกครั้ง
                if ($product['stock'] < $new_quantity) {
                    sendResponse(false, 'สินค้าเหลือไม่เพียงพอ (คงเหลือ: ' . $product['stock'] . ' ชิ้น)', null, 400);
                }
                
                $item['quantity'] = $new_quantity;
                $found = true;
                break;
            }
        }
        
        // ถ้าไม่เจอ ให้เพิ่มใหม่
        if (!$found) {
            $_SESSION['cart'][] = $cart_item;
        }
        
        $response_data = [
            'cart_count' => getCartCount(),
            'cart_total' => calculateCartTotal(),
            'product_added' => $cart_item
        ];
        
        sendResponse(true, 'เพิ่มสินค้าในตะกร้าเรียบร้อยแล้ว', $response_data);
        break;
        
    // ลบสินค้าออกจากตะกร้า
    case 'remove':
        $product_id = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);
        
        if ($product_id <= 0) {
            sendResponse(false, 'ไม่พบรหัสสินค้า', null, 400);
        }
        
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            sendResponse(false, 'ตะกร้าสินค้าว่างเปล่า', null, 400);
        }
        
        // หาและลบสินค้า
        $removed = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
                $removed = true;
                break;
            }
        }
        
        if (!$removed) {
            sendResponse(false, 'ไม่พบสินค้าในตะกร้า', null, 404);
        }
        
        $response_data = [
            'cart_count' => getCartCount(),
            'cart_total' => calculateCartTotal(),
            'cart_items' => $_SESSION['cart']
        ];
        
        sendResponse(true, 'ลบสินค้าออกจากตะกร้าเรียบร้อยแล้ว', $response_data);
        break;
        
    // อัปเดตจำนวนสินค้าในตะกร้า
    case 'update':
        $product_id = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);
        $quantity = (int)($input['quantity'] ?? $_POST['quantity'] ?? 0);
        
        if ($product_id <= 0) {
            sendResponse(false, 'ไม่พบรหัสสินค้า', null, 400);
        }
        
        if ($quantity < 0) {
            sendResponse(false, 'จำนวนสินค้าไม่ถูกต้อง', null, 400);
        }
        
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            sendResponse(false, 'ตะกร้าสินค้าว่างเปล่า', null, 400);
        }
        
        // หาและอัปเดตสินค้า
        $updated = false;
        foreach ($_SESSION['cart'] as $key => &$item) {
            if ($item['id'] == $product_id) {
                if ($quantity == 0) {
                    // ลบสินค้าถ้าจำนวนเป็น 0
                    unset($_SESSION['cart'][$key]);
                    $_SESSION['cart'] = array_values($_SESSION['cart']);
                } else {
                    // ตรวจสอบสต็อก
                    $product = getProduct($product_id);
                    if (!$product) {
                        sendResponse(false, 'ไม่พบสินค้าที่ต้องการ', null, 404);
                    }
                    
                    if ($product['stock'] < $quantity) {
                        sendResponse(false, 'สินค้าเหลือไม่เพียงพอ (คงเหลือ: ' . $product['stock'] . ' ชิ้น)', null, 400);
                    }
                    
                    $item['quantity'] = $quantity;
                }
                $updated = true;
                break;
            }
        }
        
        if (!$updated) {
            sendResponse(false, 'ไม่พบสินค้าในตะกร้า', null, 404);
        }
        
        $response_data = [
            'cart_count' => getCartCount(),
            'cart_total' => calculateCartTotal(),
            'cart_items' => $_SESSION['cart']
        ];
        
        sendResponse(true, 'อัปเดตจำนวนสินค้าเรียบร้อยแล้ว', $response_data);
        break;
        
    // ล้างตะกร้าทั้งหมด
    case 'clear':
        $_SESSION['cart'] = [];
        
        $response_data = [
            'cart_count' => 0,
            'cart_total' => 0,
            'cart_items' => []
        ];
        
        sendResponse(true, 'ล้างตะกร้าสินค้าเรียบร้อยแล้ว', $response_data);
        break;
        
    // นับจำนวนสินค้าในตะกร้า
    case 'count':
        $response_data = [
            'cart_count' => getCartCount()
        ];
        
        sendResponse(true, 'จำนวนสินค้าในตะกร้า', $response_data);
        break;
        
    // คำนวณยอดรวมตะกร้า
    case 'total':
        $response_data = [
            'cart_total' => calculateCartTotal(),
            'formatted_total' => number_format(calculateCartTotal(), 2) . ' บาท'
        ];
        
        sendResponse(true, 'ยอดรวมตะกร้าสินค้า', $response_data);
        break;
        
    // ดูข้อมูลตะกร้าทั้งหมด
    case 'get':
        $cart_items = $_SESSION['cart'] ?? [];
        $response_data = [
            'cart_items' => $cart_items,
            'cart_count' => getCartCount(),
            'cart_total' => calculateCartTotal(),
            'formatted_total' => number_format(calculateCartTotal(), 2) . ' บาท'
        ];
        
        sendResponse(true, 'ข้อมูลตะกร้าสินค้า', $response_data);
        break;
        
    default:
        sendResponse(false, 'Action ไม่ถูกต้อง', null, 400);
        break;
}
?>