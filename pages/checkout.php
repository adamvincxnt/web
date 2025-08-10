<?php require_once __DIR__ . '/../init.php'; ?>
<?php
$page_title = 'ชำระเงิน - ร้านอาหารออนไลน์';
require_once '../includes/header.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/login');
    exit;
}

// ตรวจสอบตะกร้าสินค้า
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: ' . SITE_URL . '/pages/cart');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้";
}

// คำนวณยอดรวม
$cart_total = 0;
$cart_items = [];

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    try {
        $stmt = $db->prepare("SELECT id, name, price, image_url FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $item_total = $product['price'] * $quantity;
            $cart_total += $item_total;
            
            $cart_items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'total' => $item_total,
                'image_url' => $product['image_url']
            ];
        }
    } catch(PDOException $e) {
        // ข้ามสินค้าที่มีปัญหา
        continue;
    }
}

if (empty($cart_items)) {
    header('Location: ' . SITE_URL . '/pages/cart');
    exit;
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header">
                <h4><i class="fas fa-credit-card me-2"></i>ชำระเงิน</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- รายการสินค้า -->
                    <div class="col-md-7">
                        <h5><i class="fas fa-shopping-cart me-2"></i>รายการสินค้า</h5>
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="row align-items-center mb-3">
                                        <div class="col-3">
                                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                                 class="img-fluid rounded">
                                        </div>
                                        <div class="col-6">
                                            <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                            <small class="text-muted">จำนวน: <?= $item['quantity'] ?></small>
                                        </div>
                                        <div class="col-3 text-end">
                                            <strong>฿<?= number_format($item['total'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endforeach; ?>
                                
                                <div class="row">
                                    <div class="col-8">
                                        <h5>ยอดรวมทั้งหมด:</h5>
                                    </div>
                                    <div class="col-4 text-end">
                                        <h4 class="text-primary">฿<?= number_format($cart_total, 2) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ฟอร์มข้อมูลลูกค้า -->
                    <div class="col-md-5">
                        <h5><i class="fas fa-user me-2"></i>ข้อมูลการจัดส่ง</h5>
                        \1\2>
                            <div class="mb-3">
                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" 
                                       value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" class="form-control" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                       pattern="[0-9]{10}" maxlength="10" required>
                                <div class="form-text">กรอกเบอร์โทร 10 หลัก</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">LINE ID <span class="text-danger">*</span></label>
                                <input type="text" name="customer_line_id" class="form-control" 
                                       value="<?= htmlspecialchars($user['line_id'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ตึก/หอพัก <span class="text-danger">*</span></label>
                                <input type="text" name="customer_building" class="form-control" 
                                       value="<?= htmlspecialchars($user['building'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">หมายเหตุ (ไม่บังคับ)</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="ระบุรายละเอียดเพิ่มเติม เช่น ห้อง, ชั้น"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">วิธีการชำระเงิน</label>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-qrcode fa-2x text-primary mb-2"></i>
                                        <h6>PromptPay QR Code</h6>
                                        <small class="text-muted">สแกน QR Code เพื่อจ่ายเงิน</small>
                                        <input type="hidden" name="payment_method" value="promptpay">
                                        <input type="hidden" name="total_amount" value="<?= $cart_total ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>
                                    สั่งซื้อและชำระเงิน ฿<?= number_format($cart_total, 2) ?>
                                </button>
                                <a href="../pages/cart" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>กลับไปตะกร้า
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังประมวลผล...';
    
    fetch('../api/checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // เปลี่ยนไปหน้าแสดง QR Code
            window.location.href = '../pages/status?order=' + data.order_number;
        } else {
            showAlert(data.message || 'เกิดข้อผิดพลาด', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>สั่งซื้อและชำระเงิน ฿<?= number_format($cart_total, 2) ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>สั่งซื้อและชำระเงิน ฿<?= number_format($cart_total, 2) ?>';
    });
});

// ตรวจสอบเบอร์โทรศัพท์
document.querySelector('input[name="customer_phone"]').addEventListener('input', function() {
    const phone = this.value;
    if (phone.length === 10 && /^[0-9]+$/.test(phone)) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>