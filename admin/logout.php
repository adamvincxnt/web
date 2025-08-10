<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// ตรวจสอบว่าเป็นแอดมินหรือไม่
if (isAdmin()) {
    $username = $_SESSION['admin_username'];
    
    // ส่งแจ้งเตือนการออกจากระบบ
    $logout_message = "🚪 **แอดมินออกจากระบบ**\n";
    $logout_message .= "👤 ผู้ใช้: " . $username . "\n";
    $logout_message .= "🕐 เวลา: " . date('d/m/Y H:i:s') . "\n";
    $logout_message .= "🌐 IP: " . $_SERVER['REMOTE_ADDR'];
    
    sendToDiscord(DISCORD_ADMIN_WEBHOOK, [
        'content' => $logout_message
    ]);
}

// ลบ session ทั้งหมด
session_unset();
session_destroy();

// Redirect ไปหน้าล็อกอินพร้อมข้อความ
header('Location: ' . SITE_URL . '/admin/login.php?logged_out=1');
exit;
?>