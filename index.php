<?php
$page_title = 'หน้าแรก - ร้านอาหารออนไลน์';
require_once 'includes/header.php';

// ดึงข้อมูลสินค้าจากฐานข้อมูล
$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC");
$stmt->execute();
$products = $stmt->fetchAll();
?>

<div class="hero-section bg-primary text-white py-5 rounded mb-4">
    <div class="container text-center">
        <h1 class="display-4"><i class="fas fa-utensils me-3"></i>ยินดีต้อนรับ</h1>
        <p class="lead">สั่งอาหารอร่อย ๆ ง่าย ๆ ผ่านระบบออนไลน์</p>
        <a href="#products" class="btn btn-light btn-lg">
            <i class="fas fa-arrow-down me-2"></i>ดูเมนูอาหาร
        </a>
    </div>
</div>

<section id="products">
    <h2 class="text-center mb-4">
        <i class="fas fa-fire me-2 text-danger"></i>เมนูแนะนำ
    </h2>
    
    <?php if (!empty($products)): ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                 style="height: 200px;">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo htmlspecialchars($product['description']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 text-primary mb-0">
                                    ฿<?php echo number_format($product['price'], 2); ?>
                                </span>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>/pages/product/<?php echo $product['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm me-2">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-primary btn-sm add-to-cart" 
                                            data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
            <h4>ยังไม่มีสินค้า</h4>
            <p class="text-muted">กรุณากลับมาใหม่ภายหลัง</p>
        </div>
    <?php endif; ?>
</section>

<?php 
$extra_js = ['cart.js'];
require_once 'includes/footer.php'; 
?>