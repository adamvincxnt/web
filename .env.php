<?php
// การตั้งค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'reach177_web');
define('DB_USER', 'reach177_web');
define('DB_PASS', 'jELAxVuj9JaQevQLpkkZ');

// การตั้งค่า Discord Webhook
define('DISCORD_WEBHOOK_ORDERS', 'https://discord.com/api/webhooks/1404031094080274502/q34GxDn7EiZi_1GwEYk3b3N67DhV8jAs9cooIr7FQeMeIWpUHpsBo-KizOubFuKWZ2OS');
define('DISCORD_WEBHOOK_RECEIPTS', 'https://discord.com/api/webhooks/1404005958337826947/jT3J0st8JMwMbX3vU6-yvbC79CHAPuxP29tuIojMCXiebZfXdl1uY5cEuRluIhFU5UWO');
define('DISCORD_WEBHOOK_PRODUCTS', 'https://discord.com/api/webhooks/1404005854226808943/W-4vAUy_DKdWPnDhKNPPLa3WqLcbaFoYUnScu6WmDD-tDyXwGb0PnT33OiPOS-efy4vH');

// การตั้งค่า LINE OA
define('LINE_CHANNEL_ACCESS_TOKEN', 'BhL1qq3mNlayhJ+u5rtrpihybNyi6vBuocK3kV81/nozT7V3qoJgH6BYHJ3Gn83gdEyaz0kr/ZzMlK8M52BdqFCOOHJ+DN23chGtVJe0rodYZ3QvJT/QLWwWVcjiQYJ3xqvY1qMjmIOqxGVRJq44GQdB04t89/1O/w1cDnyilFU=');
define('LINE_ADMIN_USER_ID', 'Ucb80d37b2994d539617ecca828b96af3');  // LINE User ID ของแอดมิน

// การตั้งค่า PromptPay
define('PROMPTPAY_PHONE', '0628293559');  // เบอร์ร้านค้า

// การตั้งค่าระบบ
define('SITE_URL', 'https://web1.reach1.shop/');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
?>
