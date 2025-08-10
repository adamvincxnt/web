<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// เริ่ม session
session_start();

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isLoggedIn()) {
    // ถ้าไม่ได้ล็อกอิน ให้ redirect ไปหน้าแรก
    header('Location: ' . SITE_URL);
    exit;
}

// เก็บข้อมูลผู้ใช้ก่อนล็อกเอาต์ (สำหรับส่งแจ้งเตือน)
$user_name = $_SESSION['user_name'] ?? 'ผู้ใช้';
$user_phone = $_SESSION['user_phone'] ?? '';

// ส่งแจ้งเตือนการออกจากระบบ (ถ้าต้องการ)
if (defined('DISCORD_WEBHOOK_MAIN') && !empty($user_name)) {
    $discordData = [
        'content' => "🚪 **ผู้ใช้ออกจากระบบ**\n" .
                   "👤 ชื่อ: {$user_name}\n" .
                   "📱 เบอร์: {$user_phone}\n" .
                   "🕐 เวลา: " . date('Y-m-d H:i:s')
    ];
    sendToDiscord(DISCORD_WEBHOOK_MAIN, $discordData);
}

// ทำลาย session ทั้งหมด
$_SESSION = array();

// ทำลาย session cookie ถ้ามี
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// ตั้งค่าหน้าที่จะ redirect ไป
$redirect_to = $_GET['redirect'] ?? SITE_URL;
$redirect_to = filter_var($redirect_to, FILTER_SANITIZE_URL);

// เริ่ม session ใหม่เพื่อแสดงข้อความ
session_start();
$_SESSION['logout_message'] = 'ออกจากระบบเรียบร้อยแล้ว';

$page_title = 'ออกจากระบบ - ร้านอาหารออนไลน์';
require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</h4>
            </div>
            <div class="card-body text-center">
                <div class="my-4">
                    <div class="spinner-border text-warning mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-success">
                        <i class="fas fa-check-circle me-2"></i>ออกจากระบบเรียบร้อยแล้ว
                    </h5>
                    <p class="text-muted">กำลังนำคุณกลับไปยังหน้าแรก...</p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="<?php echo htmlspecialchars($redirect_to); ?>" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>กลับหน้าแรก
                    </a>
                    <a href="login.php" class="btn btn-outline-success">
                        <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบอีกครั้ง
                    </a>
                </div>
            </div>
            <div class="card-footer text-center">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    ข้อมูลของคุณถูกลบออกจากระบบเรียบร้อยแล้ว
                </small>
            </div>
        </div>
    </div>
</div>

<script>
// Auto redirect หลังจาก 3 วินาที
setTimeout(function() {
    window.location.href = '<?php echo htmlspecialchars($redirect_to); ?>';
}, 3000);

// แสดงข้อความแจ้งเตือนด้วย SweetAlert2 (ถ้ามี)
document.addEventListener('DOMContentLoaded', function() {
    // ถ้ามี SweetAlert2
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'ออกจากระบบแล้ว',
            text: 'ขอบคุณที่ใช้บริการ!',
            timer: 2500,
            showConfirmButton: false
        });
    } else {
        // ใช้ Alert ธรรมดา
        showAlert('ออกจากระบบเรียบร้อยแล้ว ขอบคุณที่ใช้บริการ!', 'success');
    }
});

// ป้องกันการกลับมาหน้านี้ด้วย Browser Back Button
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};