<?php
session_start();
$page_title = 'ระบบจัดการแอดมิน - ร้านอาหารออนไลน์';
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

// ดึงสถิติต่างๆ
try {
    // นับจำนวนสินค้าทั้งหมด
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];

    // นับจำนวนสินค้าที่หมด
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stock <= 0");
    $out_of_stock = $stmt->fetch()['total'];

    // นับจำนวนออเดอร์วันนี้
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_orders = $stmt->fetch()['total'];

    // นับจำนวนออเดอร์ที่รอการตรวจสอบ
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetch()['total'];

    // ยอดขายวันนี้
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
    $today_sales = $stmt->fetch()['total'];

    // ยอดขายเดือนนี้
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND status != 'cancelled'");
    $monthly_sales = $stmt->fetch()['total'];

    // ออเดอร์ล่าสุด 5 รายการ
    $stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    // สินค้าที่ขายดีที่สุด 5 อันดับ
    $stmt = $db->query("
        SELECT p.name, p.price, p.image_url, SUM(oi.quantity) as total_sold
        FROM products p 
        JOIN order_items oi ON p.id = oi.product_id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.status != 'cancelled' 
        GROUP BY p.id 
        ORDER BY total_sold DESC 
        LIMIT 5
    ");
    $best_sellers = $stmt->fetchAll();

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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
            color: white;
            border-radius: 15px;
            border: none;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #56ab2f, #a8e6cf);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #fa709a, #fee140);
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
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/admin/">
                            <i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด
                        </a>
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/products.php">
                            <i class="fas fa-box me-2"></i>จัดการสินค้า
                        </a>
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/orders.php">
                            <i class="fas fa-shopping-cart me-2"></i>จัดการคำสั่งซื้อ
                            <?php if ($pending_orders > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $pending_orders; ?></span>
                            <?php endif; ?>
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
                            <h2><i class="fas fa-tachometer-alt me-2"></i>แดशบอร์ด</h2>
                            <p class="text-muted">ภาพรวมของร้านอาหารออนไลน์</p>
                        </div>
                        <div class="col-auto">
                            <div class="text-muted">
                                <i class="fas fa-calendar me-2"></i><?php echo date('d/m/Y H:i'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card info h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-2x mb-2"></i>
                                    <h3><?php echo number_format($total_products); ?></h3>
                                    <p class="mb-0">สินค้าทั้งหมด</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card warning h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h3><?php echo number_format($out_of_stock); ?></h3>
                                    <p class="mb-0">สินค้าหมดสต็อก</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card success h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                    <h3><?php echo number_format($today_orders); ?></h3>
                                    <p class="mb-0">ออเดอร์วันนี้</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card danger h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h3><?php echo number_format($pending_orders); ?></h3>
                                    <p class="mb-0">รอตรวจสอบ</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Stats -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card chart-container">
                                <h5><i class="fas fa-chart-line me-2 text-success"></i>ยอดขายวันนี้</h5>
                                <h2 class="text-success">฿<?php echo number_format($today_sales, 2); ?></h2>
                                <small class="text-muted">จากออเดอร์ <?php echo $today_orders; ?> รายการ</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card chart-container">
                                <h5><i class="fas fa-chart-bar me-2 text-info"></i>ยอดขายเดือนนี้</h5>
                                <h2 class="text-info">฿<?php echo number_format($monthly_sales, 2); ?></h2>
                                <small class="text-muted">เดือน <?php echo date('F Y'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Orders -->
                        <div class="col-md-8">
                            <div class="card table-modern">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>ออเดอร์ล่าสุด</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($recent_orders)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>รหัสออเดอร์</th>
                                                        <th>ลูกค้า</th>
                                                        <th>ยอดรวม</th>
                                                        <th>สถานะ</th>
                                                        <th>วันที่</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_orders as $order): ?>
                                                        <tr>
                                                            <td>
                                                                <code><?php echo htmlspecialchars($order['order_number']); ?></code>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                            <td class="fw-bold text-success">
                                                                ฿<?php echo number_format($order['total_amount'], 2); ?>
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
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer text-center">
                                            <a href="<?php echo SITE_URL; ?>/admin/orders.php" class="btn btn-primary">
                                                ดูทั้งหมด <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center p-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">ยังไม่มีออเดอร์</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Best Sellers -->
                        <div class="col-md-4">
                            <div class="card table-modern">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>สินค้าขายดี</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($best_sellers)): ?>
                                        <?php foreach ($best_sellers as $index => $product): ?>
                                            <div class="d-flex align-items-center mb-3 <?php echo $index < count($best_sellers) - 1 ? 'border-bottom pb-3' : ''; ?>">
                                                <div class="me-3">
                                                    <span class="badge bg-warning rounded-circle p-2">
                                                        <?php echo $index + 1; ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    <small class="text-muted">
                                                        ขายแล้ว <?php echo $product['total_sold']; ?> ชิ้น
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-success">
                                                        ฿<?php echo number_format($product['price'], 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center mt-3">
                                            <a href="<?php echo SITE_URL; ?>/admin/products.php" class="btn btn-success btn-sm">
                                                ดูทั้งหมด <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">ยังไม่มีข้อมูลการขาย</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        // Auto refresh stats every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);

        // Notification for pending orders
        <?php if ($pending_orders > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            if (<?php echo $pending_orders; ?> > 0) {
                showAlert('มีออเดอร์ใหม่ <?php echo $pending_orders; ?> รายการรอการตรวจสอบ', 'warning');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>