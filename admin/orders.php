<?php
session_start();
$page_title = 'จัดการคำสั่งซื้อ - ระบบแอดมิน';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// ประมวลผลการอัปเดตสถานะ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action == 'update_status') {
        $order_id = (int)sanitize($_POST['order_id']);
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes']);
        
        try {
            // ดึงข้อมูลออเดอร์
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                // อัปเดตสถานะ
                $stmt = $db->prepare("UPDATE orders SET status = ?, notes = ? WHERE id = ?");
                $stmt->execute([$status, $notes, $order_id]);
                
                $success = "อัปเดตสถานะออเดอร์เรียบร้อยแล้ว";
                
                // ส่งแจ้งเตือน Discord
                $status_text = '';
                switch ($status) {
                    case 'pending': $status_text = 'รอตรวจสอบ'; break;
                    case 'confirmed': $status_text = 'ยืนยันแล้ว'; break;
                    case 'completed': $status_text = 'เสร็จสิ้น'; break;
                    case 'cancelled': $status_text = 'ยกเลิก'; break;
                }
                
                $message = "📋 **อัปเดตสถานะออเดอร์**\n";
                $message .= "🔢 **รหัส:** " . $order['order_number'] . "\n";
                $message .= "👤 **ลูกค้า:** " . $order['customer_name'] . "\n";
                $message .= "📊 **สถานะ:** " . $status_text . "\n";
                $message .= "💰 **ยอดรวม:** ฿" . number_format($order['total_amount'], 2) . "\n";
                if (!empty($notes)) {
                    $message .= "📝 **หมายเหตุ:** " . $notes . "\n";
                }
                $message .= "👤 **โดย:** " . $_SESSION['admin_username'];
                
                sendToDiscord(DISCORD_ORDER_WEBHOOK, [
                    'content' => $message
                ]);
                
                // ส่งแจ้งเตือน LINE ให้ลูกค้า (ถ้ามี LINE ID)
                if (!empty($order['customer_line_id']) && in_array($status, ['confirmed', 'completed', 'cancelled'])) {
                    $line_message = "📋 สถานะออเดอร์ของคุณอัปเดตแล้ว\n";
                    $line_message .= "รหัสออเดอร์: " . $order['order_number'] . "\n";
                    $line_message .= "สถานะ: " . $status_text . "\n";
                    if (!empty($notes)) {
                        $line_message .= "หมายเหตุ: " . $notes;
                    }
                    
                    sendLineMessage($line_message);
                }
            }
        } catch(PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงข้อมูลออเดอร์
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $db->prepare("SELECT * FROM orders $where_clause ORDER BY created_at DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(10px);
        }
        .table-modern {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .order-detail {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
        }
        .qr-image {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <div class="text-center text-white mb-4">
                        <i class="fas fa-utensils fa-3x mb-2"></i>
                        <h5>แอดมินระบบ</h5>
                        <small>ร้านอาหารออนไลน์</small>
                    </div>
                    
                    <div class="text-white mb-3">
                        <small>ยินดีต้อนรับ</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
                    </div>

                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/">
                            <i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด
                        </a>
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/products.php">
                            <i class="fas fa-box me-2"></i>จัดการสินค้า
                        </a>
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/admin/orders.php">
                            <i class="fas fa-shopping-cart me-2"></i>จัดการคำสั่งซื้อ
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>ดูเว็บไซต์
                        </a>
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col">
                            <h2><i class="fas fa-shopping-cart me-2"></i>จัดการคำสั่งซื้อ</h2>
                            <p class="text-muted">ตรวจสอบและจัดการออเดอร์ของลูกค้า</p>
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">ค้นหา</label>
                                    <input type="text" name="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="รหัสออเดอร์, ชื่อ, เบอร์">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">สถานะ</label>
                                    <select name="status" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>ยืนยันแล้ว</option>
                                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">วันที่เริ่ม</label>
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">วันที่สิ้นสุด</label>
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="fas fa-search"></i> ค้นหา
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card table-modern">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>รายการคำสั่งซื้อ (<?php echo count($orders); ?> รายการ)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($orders)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>รหัสออเดอร์</th>
                                                <th>ลูกค้า</th>
                                                <th>ติดต่อ</th>
                                                <th>ยอดรวม</th>
                                                <th>สถานะ</th>
                                                <th>วันที่สั่ง</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                        <button class="btn btn-sm btn-outline-info ms-2" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#detail_<?php echo $order['id']; ?>"
                                                                title="ดูรายละเอียด">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                        <br><small class="text-muted">ตึก <?php echo htmlspecialchars($order['customer_building']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            📞 <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                                            <?php if (!empty($order['customer_line_id'])): ?>
                                                                LINE: <?php echo htmlspecialchars($order['customer_line_id']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-success">฿<?php echo number_format($order['total_amount'], 2); ?></span>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['payment_method']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'secondary';
                                                        $status_text = $order['status'];
                                                        switch ($order['status']) {
                                                            case 'pending':
                                                                $status_class = 'warning';
                                                                $status_text = 'รอตรวจสอบ';
                                                                break;
                                                            case 'confirmed':
                                                                $status_class = 'info';
                                                                $status_text = 'ยืนยันแล้ว';
                                                                break;
                                                            case 'completed':
                                                                $status_class = 'success';
                                                                $status_text = 'เสร็จสิ้น';
                                                                break;
                                                            case 'cancelled':
                                                                $status_class = 'danger';
                                                                $status_text = 'ยกเลิก';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="updateStatus(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', '<?php echo $order['status']; ?>')"
                                                                title="อัปเดตสถานะ">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr class="collapse" id="detail_<?php echo $order['id']; ?>">
                                                    <td colspan="7">
                                                        <div class="order-detail">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6><i class="fas fa-list me-2"></i>รายการสินค้า:</h6>
                                                                    <?php
                                                                    try {
                                                                        $stmt = $db->prepare("
                                                                            SELECT oi.*, p.name, p.image_url 
                                                                            FROM order_items oi 
                                                                            JOIN products p ON oi.product_id = p.id 
                                                                            WHERE oi.order_id = ?
                                                                        ");
                                                                        $stmt->execute([$order['id']]);
                                                                        $items = $stmt->fetchAll();
                                                                        
                                                                        foreach ($items as $item):
                                                                    ?>
                                                                        <div class="d-flex align-items-center mb-2">
                                                                            <?php if (!empty($item['image_url'])): ?>
                                                                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                                                     class="me-3" style="width: 40px; height: 40px; object-fit: cover; border-radius: 5px;">
                                                                            <?php endif; ?>
                                                                            <div class="flex-grow-1">
                                                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                                                <br><small>จำนวน: <?php echo $item['quantity']; ?> x ฿<?php echo number_format($item['price'], 2); ?></small>
                                                                            </div>
                                                                            <div class="text-end">
                                                                                <span class="fw-bold">฿<?php echo number_format($item['quantity'] * $item['price'], 2); ?></span>
                                                                            </div>
                                                                        </div>
                                                                    <?php 
                                                                        endforeach;
                                                                    } catch(PDOException $e) {
                                                                        echo '<p class="text-danger">ไม่สามารถดึงข้อมูลสินค้าได้</p>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <?php if (!empty($order['qr_code_url'])): ?>
                                                                        <h6><i class="fas fa-qrcode me-2"></i>QR Code การชำระเงิน:</h6>
                                                                        <img src="<?php echo htmlspecialchars($order['qr_code_url']); ?>" 
                                                                             alt="QR Code" class="qr-image mb-3">
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if (!empty($order['notes'])): ?>
                                                                        <h6><i class="fas fa-sticky-note me-2"></i>หมายเหตุ:</h6>
                                                                        <p class="small"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">ไม่พบคำสั่งซื้อ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">อัปเดตสถานะออเดอร์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" id="statusOrderId">
                        
                        <div class="mb-3">
                            <label class="form-label">รหัสออเดอร์:</label>
                            <div class="fw-bold" id="statusOrderNumber"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">สถานะใหม่:</label>
                            <select class="form-select" id="statusSelect" name="status" required>
                                <option value="pending">รอตรวจสอบ</option>
                                <option value="confirmed">ยืนยันแล้ว</option>
                                <option value="completed">เสร็จสิ้น</option>
                                <option value="cancelled">ยกเลิก</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="statusNotes" class="form-label">หมายเหตุ:</label>
                            <textarea class="form-control" id="statusNotes" name="notes" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>อัปเดต
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        function updateStatus(orderId, orderNumber, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('statusOrderNumber').textContent = orderNumber;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('statusNotes').value = '';
            
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        // Auto refresh ทุก 2 นาที
        setTimeout(function() {
            location.reload();
        }, 120000);
    </script>
</body>
</html>