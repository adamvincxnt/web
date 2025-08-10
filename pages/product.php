<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// รับ Product ID
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: ' . SITE_URL);
    exit;
}

// ดึงข้อมูลสินค้า
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ' . SITE_URL);
        exit;
    }
    
    // ดึงสินค้าที่เกี่ยวข้อง (หมวดเดียวกัน)
    $related_stmt = $db->prepare("SELECT * FROM products WHERE category = ? AND id != ? AND status = 'active' LIMIT 4");
    $related_stmt->execute([$product['category'], $product_id]);
    $related_products = $related_stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ' . SITE_URL);
    exit;
}

$page_title = htmlspecialchars($product['name']) . ' - ร้านอาหารออนไลน์';
require_once '../includes/header.php';
?>

<div class="row">
    <!-- เส้นทาง (Breadcrumb) -->
    <div class="col-12 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home me-1"></i>หน้าแรก</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>?category=<?php echo urlencode($product['category']); ?>">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- รูปภาพสินค้า -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="position-relative">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="height: 400px; object-fit: cover;">
                
                <!-- Badge หมวดหมู่ -->
                <div class="position-absolute top-0 start-0 m-3">
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category']); ?>
                    </span>
                </div>
                
                <!-- Badge สต็อก -->
                <div class="position-absolute top-0 end-0 m-3">
                    <?php if ($product['stock'] > 10): ?>
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-check me-1"></i>พร้อมส่ง
                        </span>
                    <?php elseif ($product['stock'] > 0): ?>
                        <span class="badge bg-warning fs-6">
                            <i class="fas fa-exclamation me-1"></i>เหลือน้อย
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6">
                            <i class="fas fa-times me-1"></i>หมด
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ข้อมูลสินค้า -->
    <div class="col-lg-6">
        <div class="card shadow h-100">
            <div class="card-body">
                <h1 class="card-title h3 mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- ราคา -->
                <div class="mb-4">
                    <span class="h2 text-primary fw-bold">
                        ฿<?php echo number_format($product['price'], 2); ?>
                    </span>
                </div>
                
                <!-- รายละเอียด -->
                <div class="mb-4">
                    <h5><i class="fas fa-info-circle me-2"></i>รายละเอียดสินค้า</h5>
                    <p class="text-muted">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>
                </div>
                
                <!-- ข้อมูลเพิ่มเติม -->
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-layer-group me-2 text-muted"></i>
                            <span class="text-muted">หมวดหมู่:</span>
                        </div>
                        <span class="fw-bold"><?php echo htmlspecialchars($product['category']); ?></span>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-boxes me-2 text-muted"></i>
                            <span class="text-muted">คงเหลือ:</span>
                        </div>
                        <span class="fw-bold <?php echo $product['stock'] <= 5 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $product['stock']; ?> ชิ้น
                        </span>
                    </div>
                </div>
                
                <!-- ฟอร์มเพิ่มในตะกร้า -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="border-top pt-4">
                        <div class="row align-items-center mb-3">
                            <div class="col-4">
                                <label for="quantity" class="form-label fw-bold">จำนวน:</label>
                            </div>
                            <div class="col-8">
                                <div class="input-group" style="max-width: 150px;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center" id="quantity" 
                                           value="1" min="1" max="<?php echo $product['stock']; ?>" 
                                           onchange="validateQuantity()">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg" onclick="addToCart()" id="add-to-cart-btn">
                                <i class="fas fa-cart-plus me-2"></i>เพิ่มในตะกร้า
                            </button>
                            
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-success btn-lg" onclick="buyNow()">
                                    <i class="fas fa-bolt me-2"></i>สั่งซื้อทันที
                                </button>
                            <?php else: ?>
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบเพื่อสั่งซื้อ
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="border-top pt-4">
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>สินค้าหมดชั่วคราว</strong><br>
                            กรุณาติดต่อเจ้าหน้าที่หรือลองใหม่อีกครั้งในภายหลัง
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- สินค้าที่เกี่ยวข้อง -->
<?php if (!empty($related_products)): ?>
<div class="row mt-5">
    <div class="col-12">
        <h3><i class="fas fa-heart me-2"></i>สินค้าที่คุณอาจชอบ</h3>
        <hr>
    </div>
</div>

<div class="row">
    <?php foreach ($related_products as $related): ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow-sm h-100 product-card">
                <div class="position-relative">
                    <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($related['name']); ?>"
                         style="height: 200px; object-fit: cover;">
                    
                    <?php if ($related['stock'] <= 0): ?>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <span class="badge bg-danger fs-6">หมด</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <h6 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h6>
                    <p class="card-text text-muted small flex-grow-1">
                        <?php echo mb_substr(htmlspecialchars($related['description']), 0, 80) . '...'; ?>
                    </p>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h6 text-primary mb-0">
                                ฿<?php echo number_format($related['price'], 2); ?>
                            </span>
                            <small class="text-muted">คงเหลือ: <?php echo $related['stock']; ?></small>
                        </div>
                        <div class="d-grid mt-2">
                            <a href="product.php?id=<?php echo $related['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// ข้อมูลสินค้า
const product = {
    id: <?php echo $product['id']; ?>,
    name: '<?php echo addslashes($product['name']); ?>',
    price: <?php echo $product['price']; ?>,
    stock: <?php echo $product['stock']; ?>
};

// เปลี่ยนจำนวนสินค้า
function changeQuantity(change) {
    const quantityInput = document.getElementById('quantity');
    let newQuantity = parseInt(quantityInput.value) + change;
    
    if (newQuantity < 1) newQuantity = 1;
    if (newQuantity > product.stock) newQuantity = product.stock;
    
    quantityInput.value = newQuantity;
    validateQuantity();
}

// ตรวจสอบจำนวนสินค้า
function validateQuantity() {
    const quantityInput = document.getElementById('quantity');
    let quantity = parseInt(quantityInput.value);
    
    if (isNaN(quantity) || quantity < 1) {
        quantity = 1;
    } else if (quantity > product.stock) {
        quantity = product.stock;
        showAlert('จำนวนสินค้าเกินที่มีในสต็อก', 'warning');
    }
    
    quantityInput.value = quantity;
}

// เพิ่มสินค้าในตะกร้า
function addToCart() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const button = document.getElementById('add-to-cart-btn');
    
    // ปิดการใช้งานปุ่มชั่วคราว
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังเพิ่ม...';
    
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: product.id,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            
            // อัปเดต Cart Badge
            updateCartBadge(data.data.cart_count);
            
            // แสดงปุ่มไปตะกร้า
            button.innerHTML = '<i class="fas fa-check me-2"></i>เพิ่มแล้ว';
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-cart-plus me-2"></i>เพิ่มในตะกร้า';
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                button.disabled = false;
            }, 2000);
            
        } else {
            showAlert(data.message, 'error');
            button.innerHTML = '<i class="fas fa-cart-plus me-2"></i>เพิ่มในตะกร้า';
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('เกิดข้อผิดพลาดในการเพิ่มสินค้า', 'error');
        button.innerHTML = '<i class="fas fa-cart-plus me-2"></i>เพิ่มในตะกร้า';
        button.disabled = false;
    });
}

// สั่งซื้อทันที
function buyNow() {
    const quantity = parseInt(document.getElementById('quantity').value);
    
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: product.id,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // ไปหน้าชำระเงินทันที
            window.location.href = 'checkout.php';
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
    });
}

// เมื่อโหลดหน้าเสร็จ
document.addEventListener('DOMContentLoaded', function() {
    // Focus ที่ช่องจำนวน
    document.getElementById('quantity').focus();
    
    // Lazy loading สำหรับรูปภาพ
    if ('loading' in HTMLImageElement.prototype) {
        const images = document.querySelectorAll('img[loading="lazy"]');
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
});
</script>

<style>
.product-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}

.input-group .btn {
    border-radius: 0;
}

.input-group .form-control {
    border-left: none;
    border-right: none;
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .h2 {
        font-size: 1.5rem;
    }
    
    .btn-lg {
        font-size: 1rem;
        padding: 0.5rem 1rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>