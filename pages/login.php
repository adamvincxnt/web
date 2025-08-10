<?php
$page_title = 'เข้าสู่ระบบ - ร้านอาหารออนไลน์';
require_once '../includes/header.php';

// ถ้าล็อกอินอยู่แล้ว ให้ redirect ไปหน้าแรก
if (isLoggedIn()) {
    header('Location: ' . SITE_URL);
    exit;
}

$error = '';
$success = '';

// ตรวจสอบว่ามี redirect URL หรือไม่
$redirect_to = $_GET['redirect'] ?? SITE_URL;
$redirect_to = filter_var($redirect_to, FILTER_SANITIZE_URL);

// รับข้อความสำเร็จจาก URL parameter
if (isset($_GET['registered'])) {
    $success = 'สมัครสมาชิกเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ';
}

// ประมวลผลข้อมูลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($_POST['phone'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    
    // ตรวจสอบข้อมูล
    if (empty($phone)) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
    } elseif (empty($name)) {
        $error = 'กรุณากรอกชื่อ';
    } else {
        // ตรวจสอบข้อมูลในฐานข้อมูล
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT * FROM users WHERE phone = ? AND name = ? AND status = 'active'");
            $stmt->execute([$phone, $name]);
            $user = $stmt->fetch();
            
            if ($user) {
                // บันทึก session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['user_line_id'] = $user['line_id'];
                $_SESSION['user_building'] = $user['building'];
                $_SESSION['logged_in'] = true;
                
                // ส่งแจ้งเตือนการล็อกอิน (ถ้าต้องการ)
                if (defined('DISCORD_WEBHOOK_MAIN')) {
                    $discordData = [
                        'content' => "🔐 **ผู้ใช้เข้าสู่ระบบ**\n" .
                                   "👤 ชื่อ: {$user['name']}\n" .
                                   "📱 เบอร์: {$user['phone']}\n" .
                                   "🕐 เวลา: " . date('Y-m-d H:i:s')
                    ];
                    sendToDiscord(DISCORD_WEBHOOK_MAIN, $discordData);
                }
                
                // อัปเดตเวลาล็อกอินล่าสุด
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Redirect ไปหน้าที่ต้องการ
                header('Location: ' . $redirect_to);
                exit;
            } else {
                $error = 'ไม่พบข้อมูลผู้ใช้ หรือข้อมูลไม่ถูกต้อง';
            }
        } catch(PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($redirect_to != SITE_URL): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_to); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone me-1"></i>เบอร์โทรศัพท์ <span class="text-danger">*</span>
                        </label>
                        <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="08xxxxxxxx" pattern="[0-9]{10}" maxlength="10" required>
                        <div class="form-text">เบอร์โทรที่ใช้สมัครสมาชิก</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-user me-1"></i>ชื่อ <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control form-control-lg" id="name" name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               placeholder="กรอกชื่อของคุณ" required>
                        <div class="form-text">ชื่อที่ใช้สมัครสมาชิก</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                
                <div class="text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>วิธีการเข้าสู่ระบบ:</strong><br>
                        ใช้เบอร์โทรและชื่อที่ใช้ตอนสมัครสมาชิก
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                <span class="text-muted">ยังไม่มีบัญชี?</span>
                <a href="register.php" class="text-decoration-none">
                    <i class="fas fa-user-plus me-1"></i>สมัครสมาชิก
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ตรวจสอบเบอร์โทรแบบ Real-time
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, ''); // เอาตัวอักษรที่ไม่ใช่ตัวเลขออก
    
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    
    e.target.value = value;
    
    // เปลี่ยนสีขอบตามความถูกต้อง
    if (value.length === 10 && value.match(/^[0-9]{10}$/)) {
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else if (value.length > 0) {
        e.target.classList.remove('is-valid');
        e.target.classList.add('is-invalid');
    } else {
        e.target.classList.remove('is-valid', 'is-invalid');
    }
});

// ตรวจสอบชื่อ
document.getElementById('name').addEventListener('input', function(e) {
    let value = e.target.value.trim();
    
    if (value.length >= 2) {
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else if (value.length > 0) {
        e.target.classList.remove('is-valid');
        e.target.classList.add('is-invalid');
    } else {
        e.target.classList.remove('is-valid', 'is-invalid');
    }
});

// Focus ที่ช่องเบอร์โทรเมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('phone').focus();
});
</script>

<?php require_once '../includes/footer.php'; ?>