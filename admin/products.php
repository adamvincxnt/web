<?php
session_start();
$page_title = 'จัดการสินค้า - ระบบแอดมิน';
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

// ประมวลผลการเพิ่ม/แก้ไขสินค้า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action == 'add' || $action == 'edit') {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = (float)sanitize($_POST['price']);
        $category = sanitize($_POST['category']);
        $stock = (int)sanitize($_POST['stock']);
        $image_url = sanitize($_POST['image_url']);
        $status = sanitize($_POST['status']);
        
        // Validation
        if (empty($name)) {
            $error = "กรุณากรอกชื่อสินค้า";
        } elseif ($price <= 0) {
            $error = "ราคาต้องมากกว่า 0";
        } elseif ($stock < 0) {
            $error = "จำนวนสต็อกไม่ถูกต้อง";
        } else {
            try {
                if ($action == 'add') {
                    // เพิ่มสินค้าใหม่
                    $stmt = $db->prepare("
                        INSERT INTO products (name, description, price, image_url, category, stock, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $price, $image_url, $category, $stock, $status]);
                    
                    $success = "เพิ่มสินค้าเรียบร้อยแล้ว";
                    
                    // ส่งแจ้งเตือน Discord
                    $message = "✅ **เพิ่มสินค้าใหม่**\n";
                    $message .= "📦 **ชื่อสินค้า:** " . $name . "\n";
                    $message .= "💰 **ราคา:** ฿" . number_format($price, 2) . "\n";
                    $message .= "📊 **สต็อก:** " . $stock . " ชิ้น\n";
                    $message .= "👤 **โดย:** " . $_SESSION['admin_username'];
                    
                    sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
                        'content' => $message
                    ]);
                    
                } else {
                    // แก้ไขสินค้า
                    $stmt = $db->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, image_url = ?, category = ?, stock = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $price, $image_url, $category, $stock, $status, $product_id]);
                    
                    $success = "แก้ไขสินค้าเรียบร้อยแล้ว";
                    
                    // ส่งแจ้งเตือน Discord
                    $message = "✏️ **แก้ไขสินค้า**\n";
                    $message .= "📦 **ชื่อสินค้า:** " . $name . "\n";
                    $message .= "💰 **ราคา:** ฿" . number_format($price, 2) . "\n";
                    $message .= "📊 **สต็อก:** " . $stock . " ชิ้น\n";
                    $message .= "👤 **โดย:** " . $_SESSION['admin_username'];
                    
                    sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
                        'content' => $message
                    ]);
                }
            } catch(PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
    
    if ($action == 'delete') {
        $product_id = (int)sanitize($_POST['product_id']);
        
        try {
            // ดึงข้อมูลสินค้าก่อนลบ
            $stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product_name = $stmt->fetch()['name'];
            
            // ลบสินค้า
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            
            $success = "ลบสินค้าเรียบร้อยแล้ว";
            
            // ส่งแจ้งเตือน Discord
            $message = "🗑️ **ลบสินค้า**\n";
            $message .= "📦 **ชื่อสินค้า:** " . $product_name . "\n";
            $message .= "👤 **โดย:** " . $_SESSION['admin_username'];
            
            sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
                'content' => $message
            ]);
            
        } catch(PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
        }
    }
}

// ดึงข้อมูลสินค้าทั้งหมด
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $db->prepare("SELECT * FROM products $where_clause ORDER BY created_at DESC");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // ดึงหมวดหมู่ทั้งหมด
    $stmt = $db->query("SELECT DISTINCT category FROM products ORDER BY category");
    $categories = $stmt->fetchAll();

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
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .table-modern {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/admin/products.php">
                            <i class="fas fa-box me-2"></i>จัดการสินค้า
                        </a>
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/orders.php">
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
                            <h2><i class="fas fa-box me-2"></i>จัดการสินค้า</h2>
                            <p class="text-muted">เพิ่ม แก้ไข และลบสินค้า</p>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="clearForm()">
                                <i class="fas fa-plus me-2"></i>เพิ่มสินค้าใหม่
                            </button>
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
                                <div class="col-md-4">
                                    <label class="form-label">ค้นหาสินค้า</label>
                                    <input type="text" name="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="ชื่อสินค้า...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">หมวดหมู่</label>
                                    <select name="category" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">สถานะ</label>
                                    <select name="status" class="form-select">
                                        <option value="">ทั้งหมด</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>เปิดใช้งาน</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>ปิดใช้งาน</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
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

                    <!-- Products Table -->
                    <div class="card table-modern">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>รายการสินค้า (<?php echo count($products); ?> รายการ)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($products)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>รูปภาพ</th>
                                                <th>ชื่อสินค้า</th>
                                                <th>หมวดหมู่</th>
                                                <th>ราคา</th>
                                                <th>สต็อก</th>
                                                <th>สถานะ</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 class="product-image">
                                                        <?php else: ?>
                                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if (!empty($product['description'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-success">฿<?php echo number_format($product['price'], 2); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['stock'] <= 0): ?>
                                                            <span class="badge bg-danger">หมดสต็อก</span>
                                                        <?php elseif ($product['stock'] <= 5): ?>
                                                            <span class="badge bg-warning"><?php echo $product['stock']; ?> ชิ้น</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?php echo $product['stock']; ?> ชิ้น</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['status'] == 'active'): ?>
                                                            <span class="badge bg-success">เปิดใช้งาน</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">ปิดใช้งาน</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-warning" 
                                                                    onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)"
                                                                    title="แก้ไข">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                                                    title="ลบ">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">ไม่พบสินค้า</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="clearForm()">
                                        <i class="fas fa-plus me-2"></i>เพิ่มสินค้าใหม่
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มสินค้าใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="productForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="product_id" id="productId">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="productName" class="form-label">ชื่อสินค้า *</label>
                                <input type="text" class="form-control" id="productName" name="name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="productPrice" class="form-label">ราคา (บาท) *</label>
                                <input type="number" class="form-control" id="productPrice" name="price" min="0" step="0.01" required>
                            </div>
                            <div class="col-12">
                                <label for="productDescription" class="form-label">รายละเอียด</label>
                                <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="productImage" class="form-label">URL รูปภาพ</label>
                                <input type="url" class="form-control" id="productImage" name="image_url" placeholder="https://...">
                            </div>
                            <div class="col-md-4">
                                <label for="productCategory" class="form-label">หมวดหมู่</label>
                                <select class="form-select" id="productCategory" name="category">
                                    <option value="อาหารจานหลัก">อาหารจานหลัก</option>
                                    <option value="ขนมและของหวาน">ขนมและของหวาน</option>
                                    <option value="เครื่องดื่ม">เครื่องดื่ม</option>
                                    <option value="ผลไม้">ผลไม้</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="productStock" class="form-label">จำนวนสต็อก</label>
                                <input type="number" class="form-control" id="productStock" name="stock" min="0" value="0">
                            </div>
                            <div class="col-md-4">
                                <label for="productStatus" class="form-label">สถานะ</label>
                                <select class="form-select" id="productStatus" name="status">
                                    <option value="active">เปิดใช้งาน</option>
                                    <option value="inactive">ปิดใช้งาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-2"></i>บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="product_id" id="deleteProductId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        function clearForm() {
            document.getElementById('productForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('productId').value = '';
            document.getElementById('modalTitle').textContent = 'เพิ่มสินค้าใหม่';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-2"></i>บันทึก';
        }

        function editProduct(product) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productDescription').value = product.description;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productImage').value = product.image_url;
            document.getElementById('productCategory').value = product.category;
            document.getElementById('productStock').value = product.stock;
            document.getElementById('productStatus').value = product.status;
            
            document.getElementById('modalTitle').textContent = 'แก้ไขสินค้า';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-2"></i>อัปเดต';
            
            new bootstrap.Modal(document.getElementById('productModal')).show();
        }

        function deleteProduct(id, name) {
            if (confirm('คุณต้องการลบสินค้า "' + name + '" ใช่หรือไม่?')) {
                document.getElementById('deleteProductId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>