<?php
$page_title = 'ตะกร้าสินค้า - ร้านอาหารออนไลน์';
require_once '../includes/header.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// ดึงข้อมูลตะกร้าจาก Session
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
$cart_total = 0;

// คำนวณยอดรวมและจำนวน
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
    $cart_total += $item['price'] * $item['quantity'];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shopping-cart me-2"></i>ตะกร้าสินค้า</h2>
            <div>
                <span class="badge bg-primary fs-6 me-2"><?php echo $cart_count; ?> รายการ</span>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>เลือกสินค้าต่อ
                </a>
            </div>
        </div>
    </div>
</div>

<div id="cart-container">
    <?php if (empty($cart_items)): ?>
        <!-- ตะกร้าว่าง -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow text-center">
                    <div class="card-body py-5">
                        <i class="fas fa-shopping-cart fa-5x text-muted mb-3"></i>
                        <h4 class="text-muted">ตะกร้าสินค้าว่างเปล่า</h4>
                        <p class="text-muted mb-4">คุณยังไม่มีสินค้าในตะกร้า</p>
                        <a href="<?php echo SITE_URL; ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-utensils me-2"></i>เลือกสินค้า
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- รายการสินค้าในตะกร้า -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">รายการสินค้า</h5>
                            <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                                <i class="fas fa-trash me-1"></i>ล้างตะกร้า
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="cart-items-list">
                            <?php foreach ($cart_items as $index => $item): ?>
                                <div class="cart-item border-bottom" data-product-id="<?php echo $item['id']; ?>">
                                    <div class="row g-0 p-3">
                                        <div class="col-md-3">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 style="height: 100px; object-fit: cover; width: 100%;">
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card-body py-0">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category']); ?>
                                                    </small>
                                                </p>
                                                <p class="text-primary fw-bold mb-2">
                                                    ฿<?php echo number_format($item['price'], 2); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 d-flex flex-column justify-content-center align-items-end">
                                            <div class="input-group mb-2" style="max-width: 120px;">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                                        onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity']; ?> - 1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control form-control-sm text-center" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" max="99"
                                                       onchange="updateQuantity(<?php echo $item['id']; ?>, this.value)">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                                        onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity']; ?> + 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-primary mb-1">
                                                    ฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                </div>
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>ลบ
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- สรุปยอดชำระ -->
            <div class="col-lg-4">
                <div class="card shadow sticky-top" style="top: 1rem;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>สรุปการสั่งซื้อ</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>จำนวนสินค้า:</span>
                            <span id="summary-count"><?php echo $cart_count; ?> รายการ</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>ค่าอาหาร:</span>
                            <span id="summary-subtotal">฿<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>ค่าจัดส่ง:</span>
                            <span class="text-success">ฟรี</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>ยอดรวมทั้งสิ้น:</strong>
                            <strong class="text-primary fs-5" id="summary-total">฿<?php echo number_format($cart_total, 2); ?></strong>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-lg" onclick="proceedToCheckout()">
                                <i class="fas fa-credit-card me-2"></i>ดำเนินการสั่งซื้อ
                            </button>
                            <button class="btn btn-outline-primary" onclick="window.location.href='<?php echo SITE_URL; ?>'">
                                <i class="fas fa-arrow-left me-1"></i>เลือกสินค้าเพิ่ม
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            การสั่งซื้อของคุณปลอดภัย 100%
                        </small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="d-none">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">กำลังอัปเดตตะกร้า...</p>
    </div>
</div>

<script>
// แสดง Loading
function showLoading() {
    document.getElementById('loading-overlay').classList.remove('d-none');
}

// ซ่อน Loading
function hideLoading() {
    document.getElementById('loading-overlay').classList.add('d-none');
}

// อัปเดตจำนวนสินค้า
function updateQuantity(productId, newQuantity) {
    if (newQuantity < 0) return;
    
    showLoading();
    
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update',
            product_id: productId,
            quantity: parseInt(newQuantity)
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            if (parseInt(newQuantity) === 0) {
                // ลบรายการถ้าจำนวนเป็น 0
                location.reload();
            } else {
                // อัปเดตหน้าเว็บ
                updateCartSummary(data.data);
                showAlert(data.message, 'success');
            }
        } else {
            showAlert(data.message, 'error');
            location.reload(); // Reload เพื่อให้แสดงข้อมูลที่ถูกต้อง
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('เกิดข้อผิดพลาดในการอัปเดต', 'error');
        console.error('Error:', error);
    });
}

// ลบสินค้าออกจากตะกร้า
function removeFromCart(productId) {
    if (!confirm('คุณต้องการลบสินค้านี้ออกจากตะกร้าหรือไม่?')) {
        return;
    }
    
    showLoading();
    
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove',
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showAlert(data.message, 'success');
            // รีโหลดหน้าเพื่อแสดงผลใหม่
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('เกิดข้อผิดพลาดในการลบสินค้า', 'error');
        console.error('Error:', error);
    });
}

// ล้างตะกร้าทั้งหมด
function clearCart() {
    if (!confirm('คุณต้องการล้างสินค้าทั้งหมดในตะกร้าหรือไม่?')) {
        return;
    }
    
    showLoading();
    
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'clear'
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('เกิดข้อผิดพลาดในการล้างตะกร้า', 'error');
        console.error('Error:', error);
    });
}

// อัปเดตสรุปตะกร้า
function updateCartSummary(data) {
    document.getElementById('summary-count').textContent = data.cart_count + ' รายการ';
    document.getElementById('summary-subtotal').textContent = '฿' + data.cart_total.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('summary-total').textContent = '฿' + data.cart_total.toLocaleString('th-TH', {minimumFractionDigits: 2});
    
    // อัปเดต Badge ที่หัวเว็บ
    updateCartBadge(data.cart_count);
}

// ไปหน้าชำระเงิน
function proceedToCheckout() {
    window.location.href = 'checkout.php';
}

// เมื่อโหลดหน้าเสร็จ
document.addEventListener('DOMContentLoaded', function() {
    // อัปเดต Cart Badge
    updateCartBadge(<?php echo $cart_count; ?>);
});
</script>

<style>
#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    text-align: center;
}

.cart-item:hover {
    background-color: #f8f9fa;
}

.input-group .btn {
    border-radius: 0;
}

.input-group .form-control {
    border-left: none;
    border-right: none;
}

.sticky-top {
    position: sticky;
}

@media (max-width: 768px) {
    .sticky-top {
        position: relative;
        top: auto !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>