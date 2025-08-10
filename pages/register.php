<?php
$page_title = 'สมัครสมาชิก - ร้านอาหารออนไลน์';
require_once '../includes/header.php';

// ถ้าล็อกอินอยู่แล้ว ให้ redirect ไปหน้าแรก
if (isLoggedIn()) {
    header('Location: ' . SITE_URL);
    exit;
}

$error = '';
$success = '';

// ประมวลผลข้อมูลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $line_id = sanitize($_POST['line_id'] ?? '');
    $building = sanitize($_POST['building'] ?? '');
    
    // ตรวจสอบข้อมูล
    if (empty($name)) {
        $error = 'กรุณากรอกชื่อ';
    } elseif (strlen($name) < 2) {
        $error = 'ชื่อต้องมีอย่างน้อย 2 ตัวอักษร';
    } elseif (empty($phone)) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
    } elseif (empty($line_id)) {
        $error = 'กรุณากรอกไอดีไลน์';
    } elseif (empty($building)) {
        $error = 'กรุณาเลือกตึก';
    } else {
        // ตรวจสอบว่าเบอร์โทรซ้ำหรือไม่
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            
            if ($stmt->fetch()) {
                $error = 'เบอร์โทรนี้ถูกใช้งานแล้ว';
            } else {
                // บันทึกข้อมูลลงฐานข้อมูล
                $stmt = $db->prepare("INSERT INTO users (name, phone, line_id, building, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                
                if ($stmt->execute([$name, $phone, $line_id, $building])) {
                    $success = 'สมัครสมาชิกเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ';
                    
                    // ส่งแจ้งเตือนไป Discord (ถ้ามี)
                    if (defined('DISCORD_WEBHOOK_MAIN')) {
                        $discordData = [
                            'content' => "🎉 **สมาชิกใหม่สมัครเข้าร่วม!**\n" .
                                       "👤 ชื่อ: {$name}\n" .
                                       "📱 เบอร์: {$phone}\n" .
                                       "💬 LINE: {$line_id}\n" .
                                       "🏢 ตึก: {$building}\n" .
                                       "📅 เวลา: " . date('Y-m-d H:i:s')
                        ];
                        sendToDiscord(DISCORD_WEBHOOK_MAIN, $discordData);
                    }
                    
                    // หน่วงเวลาเล็กน้อยแล้ว redirect
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '" . SITE_URL . "/pages/login.php';
                        }, 2000);
                    </script>";
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                }
            }
        } catch(PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>สมัครสมาชิก</h4>
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
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-user me-1"></i>ชื่อ-นามสกุล <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               placeholder="กรอกชื่อ-นามสกุล" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone me-1"></i>เบอร์โทรศัพท์ <span class="text-danger">*</span>
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="08xxxxxxxx" pattern="[0-9]{10}" maxlength="10" required>
                        <div class="form-text">เบอร์โทรศัพท์ 10 หลัก (เช่น 0812345678)</div>
                    </div>

                    <div class="mb-3">
                        <label for="line_id" class="form-label">
                            <i class="fab fa-line me-1"></i>ไอดีไลน์ <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="line_id" name="line_id" 
                               value="<?php echo htmlspecialchars($_POST['line_id'] ?? ''); ?>" 
                               placeholder="@your_line_id หรือ your_line_id" required>
                        <div class="form-text">ไอดีไลน์สำหรับติดต่อ</div>
                    </div>

                    <div class="mb-3">
                        <label for="building" class="form-label">
                            <i class="fas fa-building me-1"></i>ตึก/หอพัก <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="building" name="building" required>
                            <option value="">-- เลือกตึก/หอพัก --</option>
                            <option value="ตึกA" <?php echo ($_POST['building'] ?? '') == 'ตึกA' ? 'selected' : ''; ?>>ตึกA</option>
                            <option value="ตึกB" <?php echo ($_POST['building'] ?? '') == 'ตึกB' ? 'selected' : ''; ?>>ตึกB</option>
                            <option value="ตึกC" <?php echo ($_POST['building'] ?? '') == 'ตึกC' ? 'selected' : ''; ?>>ตึกC</option>
                            <option value="ตึกD" <?php echo ($_POST['building'] ?? '') == 'ตึกD' ? 'selected' : ''; ?>>ตึกD</option>
                            <option value="หอพักนอก" <?php echo ($_POST['building'] ?? '') == 'หอพักนอก' ? 'selected' : ''; ?>>หอพักนอก</option>
                            <option value="อื่นๆ" <?php echo ($_POST['building'] ?? '') == 'อื่นๆ' ? 'selected' : ''; ?>>อื่นๆ</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>สมัครสมาชิก
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <span class="text-muted">มีบัญชีอยู่แล้ว?</span>
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-sign-in-alt me-1"></i>เข้าสู่ระบบ
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

// ตรวจสอบไอดีไลน์
document.getElementById('line_id').addEventListener('input', function(e) {
    let value = e.target.value.trim();
    
    if (value.length > 0) {
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else {
        e.target.classList.remove('is-valid', 'is-invalid');
    }
});

// ตรวจสอบตึก
document.getElementById('building').addEventListener('change', function(e) {
    if (e.target.value) {
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else {
        e.target.classList.remove('is-valid');
        e.target.classList.add('is-invalid');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>