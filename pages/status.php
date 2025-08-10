<?php
$page_title = 'สถานะคำสั่งซื้อ - ร้านอาหารออนไลน์';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$order = null;
$order_items = [];

// รับหมายเลขออเดอร์
$order_number = sanitize($_GET['order'] ?? '');

if (empty($order_number)) {
    $error = 'ไม่พบหมายเลขคำสั่งซื้อ';
} else {
    try {
        // ดึงข้อมูลออเดอร์
        $stmt = $db->prepare("
            SELECT o.*, u.name as user_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.order_number = ?
        ");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch();

        if (!$order) {
            $error = 'ไม่พบคำสั่งซื้อนี้ในระบบ';
        } else {
            // ตรวจสอบสิทธิ์เข้าถึง (เจ้าของออเดอร์หรือแอดมิน)
            if (isLoggedIn() && 
                ($_SESSION['user_id'] != $order['user_id'] && !isAdmin())) {
                $error = 'คุณไม่มีสิทธิ์เข้าถึงคำสั่งซื้อนี้';
            } else {
                // ดึงรายการสินค้าในออเดอร์
                $stmt = $db->prepare("
                    SELECT * FROM order_items 
                    WHERE order_id = ? 
                    ORDER BY id
                ");
                $stmt->execute([$order['id']]);
                $order_items = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        $error = 'เกิดข้อผิดพลาดในการดึงข้อมูล';
        error_log("Status Page Error: " . $e->getMessage());
    }
}

// ฟังก์ชันแสดงสถานะ
function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'bg-warning text-dark', 'text' => 'รอชำระเงิน', 'icon' => 'fas fa-clock'],
        'paid' => ['class' => 'bg-info', 'text' => 'ชำระแล้ว', 'icon' => 'fas fa-credit-card'],
        'confirmed' => ['class' => 'bg-primary', 'text' => 'ยืนยันแล้ว', 'icon' => 'fas fa-check-circle'],
        'preparing' => ['class' => 'bg-secondary', 'text' => 'กำลังเตรียม', 'icon' => 'fas fa-utensils'],
        'ready' => ['class' => 'bg-success', 'text' => 'พร้อมส่ง', 'icon' => 'fas fa-box'],
        'delivered' => ['class' => 'bg-success', 'text' => 'ส่งแล้ว', 'icon' => 'fas fa-check-double'],
        'cancelled' => ['class' => 'bg-danger', 'text' => 'ยกเลิก', 'icon' => 'fas fa-times-circle']
    ];
    
    return $badges[$status] ?? ['class' => 'bg-secondary', 'text' => 'ไม่ระบุ', 'icon' => 'fas fa-question'];
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header">
                <h4><i class="fas fa-receipt me-2"></i>สถานะคำสั่งซื้อ</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                        <h5><?= $error ?></h5>
                        <p class="mb-0">
                            <a href="<?= SITE_URL ?>" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>กลับหน้าแรก
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- ข้อมูลออเดอร์ -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><i class="fas fa-info-circle me-2"></i>ข้อมูลคำสั่งซื้อ</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>สถานะ:</strong></td>
                                            <td>
                                                <?php $badge = getStatusBadge($order['status']); ?>
                                                <span class="badge <?= $badge['class'] ?>">
                                                    <i class="<?= $badge['icon'] ?> me-1"></i><?= $badge['text'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>การชำระเงิน:</strong></td>
                                            <td>
                                                <?php if ($order['payment_method'] === 'promptpay'): ?>
                                                    <i class="fas fa-qrcode me-1"></i>PromptPay
                                                <?php else: ?>
                                                    <?= htmlspecialchars($order['payment_method']) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><i class="fas fa-user me-2"></i>ข้อมูลลูกค้า</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="35%"><strong>ชื่อ:</strong></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>เบอร์โทร:</strong></td>
                                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>LINE ID:</strong></td>
                                            <td><?= htmlspecialchars($order['customer_line_id']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>ที่อยู่:</strong></td>
                                            <td><?= htmlspecialchars($order['customer_building']) ?></td>
                                        </tr>
                                        <?php if (!empty($order['notes'])): ?>
                                        <tr>
                                            <td><strong>หมายเหตุ:</strong></td>
                                            <td><?= htmlspecialchars($order['notes']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code PromptPay (แสดงเฉพาะสถานะ pending) -->
                    <?php if ($order['status'] === 'pending' && !empty($order['qr_code_url'])): ?>
                        <div class="row mt-4">
                            <div class="col-md-6 mx-auto">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white text-center">
                                        <h5><i class="fas fa-qrcode me-2"></i>สแกน QR Code เพื่อชำระเงิน</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="<?= htmlspecialchars($order['qr_code_url']) ?>" 
                                             alt="PromptPay QR Code" class="img-fluid mb-3" style="max-width: 300px;">
                                        <h4 class="text-primary">฿<?= number_format($order['total_amount'], 2) ?></h4>
                                        <p class="text-muted">สแกน QR Code ด้วยแอปธนาคาร<br>หลังโอนเงินแล้ว กรุณาอัปโหลดสลิปการโอน</p>
                                        
                                        <!-- ฟอร์มอัปโหลดสลิป -->
                                        <div class="mt-3">
                                            <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#uploadSlip">
                                                <i class="fas fa-upload me-2"></i>อัปโหลดสลิปการโอน
                                            </button>
                                        </div>
                                        
                                        <div class="collapse mt-3" id="uploadSlip">
                                            <div class="card">
                                                <div class="card-body">
                                                    <form id="uploadForm" enctype="multipart/form-data">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        <input type="hidden" name="order_number" value="<?= $order['order_number'] ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">เลือกไฟล์สลิปการโอน</label>
                                                            <input type="file" name="slip_image" class="form-control" 
                                                                   accept="image/*" required>
                                                            <div class="form-text">รองรับไฟล์ JPG, PNG (ขนาดไม่เกิน 5MB)</div>
                                                        </div>
                                                        
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-upload me-2"></i>อัปโหลดสลิป
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- รายการสินค้า -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-shopping-cart me-2"></i>รายการสินค้า</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>รายการ</th>
                                                    <th class="text-center">ราคา/หน่วย</th>
                                                    <th class="text-center">จำนวน</th>
                                                    <th class="text-end">รวม</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        ฿<?= number_format($item['price'], 2) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $item['quantity'] ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong>฿<?= number_format($item['total'], 2) ?></strong>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-active">
                                                    <th colspan="3" class="text-end">ยอดรวมทั้งหมด:</th>
                                                    <th class="text-end text-primary">
                                                        ฿<?= number_format($order['total_amount'], 2) ?>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline สถานะ -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-history me-2"></i>ติดตามสถานะ</h5>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php
                                        $statuses = [
                                            'pending' => 'รอชำระเงิน',
                                            'paid' => 'ชำระแล้ว',
                                            'confirmed' => 'ยืนยันแล้ว',
                                            'preparing' => 'กำลังเตรียม',
                                            'ready' => 'พร้อมส่ง',
                                            'delivered' => 'ส่งแล้ว'
                                        ];
                                        
                                        $current_status = $order['status'];
                                        $status_keys = array_keys($statuses);
                                        $current_index = array_search($current_status, $status_keys);
                                        ?>
                                        
                                        <?php foreach ($statuses as $key => $text): ?>
                                            <?php 
                                            $index = array_search($key, $status_keys);
                                            $is_current = ($key === $current_status);
                                            $is_completed = ($index <= $current_index && $current_status !== 'cancelled');
                                            $is_cancelled = ($current_status === 'cancelled');
                                            ?>
                                            
                                            <div class="timeline-item <?= $is_completed ? 'completed' : '' ?> <?= $is_current ? 'current' : '' ?>">
                                                <div class="timeline-marker">
                                                    <?php if ($is_completed): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php elseif ($is_current && !$is_cancelled): ?>
                                                        <i class="fas fa-clock text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-circle text-muted"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="timeline-content">
                                                    <h6 class="<?= $is_completed ? 'text-success' : ($is_current ? 'text-warning' : 'text-muted') ?>">
                                                        <?= $text ?>
                                                    </h6>
                                                    <?php if ($is_current): ?>
                                                        <small class="text-muted">สถานะปัจจุบัน</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($current_status === 'cancelled'): ?>
                                            <div class="timeline-item current">
                                                <div class="timeline-marker">
                                                    <i class="fas fa-times-circle text-danger"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <h6 class="text-danger">ยกเลิกคำสั่งซื้อ</h6>
                                                    <small class="text-muted">คำสั่งซื้อถูกยกเลิก</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ปุ่มดำเนินการ -->
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <a href="<?= SITE_URL ?>" class="btn btn-primary me-2">
                                <i class="fas fa-home me-2"></i>กลับหน้าแรก
                            </a>
                            
                            <?php if (isLoggedIn() && $_SESSION['user_id'] == $order['user_id']): ?>
                                <a href="../pages/cart" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-shopping-cart me-2"></i>สั่งซื้อเพิ่ม
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="window.print()" class="btn btn-outline-info">
                                <i class="fas fa-print me-2"></i>พิมพ์ใบเสร็จ
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 80px;
}

.timeline-marker {
    position: absolute;
    left: 22px;
    top: 0;
    font-size: 16px;
}

.timeline-content h6 {
    margin-bottom: 5px;
}

@media print {
    .btn, .card-header, nav, footer {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<script>
// อัปโหลดสลิปการโอน
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // ตรวจสอบไฟล์
    const fileInput = form.querySelector('input[type="file"]');
    if (!fileInput.files.length) {
        showAlert('กรุณาเลือกไฟล์สลิป', 'danger');
        return;
    }
    
    const file = fileInput.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (file.size > maxSize) {
        showAlert('ไฟล์มีขนาดใหญ่เกิน 5MB', 'danger');
        return;
    }
    
    if (!file.type.startsWith('image/')) {
        showAlert('กรุณาเลือกไฟล์รูปภาพเท่านั้น', 'danger');
        return;
    }
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัปโหลด...';
    
    fetch('../api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message || 'อัปโหลดสลิปสำเร็จ', 'success');
            // รีเฟรชหน้าหลังจาก 2 วินาที
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showAlert(data.message || 'เกิดข้อผิดพลาด', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload me-2"></i>อัปโหลดสลิป';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-upload me-2"></i>อัปโหลดสลิป';
    });
});

// Auto refresh สำหรับสถานะ pending
<?php if ($order && $order['status'] === 'pending'): ?>
    setInterval(function() {
        // รีเฟรชหน้าทุก 30 วินาทีสำหรับตรวจสอบการอัปเดตสถานะ
        location.reload();
    }, 30000);
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>d width="40%"><strong>เลขที่คำสั่งซื้อ:</strong></td>
                                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>วันที่สั่ง:</strong></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>ยอดรวม:</strong></td>
                                            <td class="text-primary"><strong>฿<?= number_format($order['total_amount'], 2) ?></strong></td>
                                        </tr>
                                        <tr>
                                            <t