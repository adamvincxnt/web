<?php
session_start();
$page_title = 'เข้าสู่ระบบแอดมิน - ร้านอาหารออนไลน์';
require_once '../config/config.php';
require_once '../includes/functions.php';

// ถ้าล็อกอินแล้วให้ไปหน้า dashboard
if (isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    
    if (empty($username)) {
        $error = "กรุณากรอกชื่อผู้ใช้";
    } elseif (empty($password)) {
        $error = "กรุณากรอกรหัสผ่าน";
    } else {
        // ตรวจสอบข้อมูลแอดมิน (ใช้ข้อมูลจาก config)
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_username'] = $username;
            $_SESSION['is_admin'] = true;
            
            // ส่งแจ้งเตือนการเข้าสู่ระบบ
            $login_message = "🔐 **แอดมินเข้าสู่ระบบ**\n";
            $login_message .= "🐀𩀠ผู้ใช้: " . $username . "\n";
            $login_message .= "🕐 เวลา: " . date('d/m/Y H:i:s') . "\n";
            $login_message .= "🌐 IP: " . $_SERVER['REMOTE_ADDR'];
            
            sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
                'content' => $login_message
            ]);
            
            // Redirect ไปหน้าที่ต้องการหรือ dashboard
            $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : SITE_URL . '/admin/';
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            
            // ส่งแจ้งเตือนการล็อกอินผิดพลาด
            $failed_message = "⚠️ **พยายามเข้าสู่ระบบแอดมินผิดพลาด**\n";
            $failed_message .= "👤 ผู้ใช้: " . $username . "\n";
            $failed_message .= "🕐 เวลา: " . date('d/m/Y H:i:s') . "\n";
            $failed_message .= "🌐 IP: " . $_SERVER['REMOTE_ADDR'];
            
            sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
                'content' => $failed_message
            ]);
        }
    }
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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .admin-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .btn-admin {
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="admin-login">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="card login-card border-0">
                        <div class="login-header">
                            <i class="fas fa-shield-alt fa-3x mb-3"></i>
                            <h3>ระบบแอดมิน</h3>
                            <p class="mb-0">เข้าสู่ระบบจัดการร้านอาหาร</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" id="loginForm">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>ชื่อผู้ใช้
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           id="username" 
                                           name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                           placeholder="กรอกชื่อผู้ใช้" 
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>รหัสผ่าน
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control form-control-lg" 
                                               id="password" 
                                               name="password" 
                                               placeholder="กรอกรหัสผ่าน" 
                                               required>
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                id="togglePassword"
                                                title="แสดง/ซ่อนรหัสผ่าน">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe">
                                    <label class="form-check-label" for="rememberMe">
                                        จดจำการเข้าสู่ระบบ
                                    </label>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-admin btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <a href="<?php echo SITE_URL; ?>" class="text-white text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>กลับหน้าแรก
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle แสดง/ซ่อนรหัสผ่าน
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });

        // Focus ช่องแรก
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // เข้ารหัสการส่งฟอร์ม
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username) {
                alert('กรุณากรอกชื่อผู้ใช้');
                e.preventDefault();
                return false;
            }
            
            if (!password) {
                alert('กรุณากรอกรหัสผ่าน');
                e.preventDefault();
                return false;
            }
            
            if (password.length < 6) {
                alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>