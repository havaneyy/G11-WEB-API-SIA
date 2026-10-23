<?php
require_once __DIR__ . '/config.php';
$db = get_db();

$order_id = !empty($_GET['order_id']) ? (int)$_GET['order_id'] : (!empty($_GET['id']) ? (int)$_GET['id'] : null);
$order_code = $_GET['order'] ?? $_GET['order_code'] ?? '';

if ($order_id) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
    $stmt->execute([$order_code]);
}
$order = $stmt->fetch();

$status = 'cancelled';
if ($order) {
    $order_code = $order['order_code'];
    // Cancellation must never mark an order as paid
    if ($order['status'] === 'pending') {
        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$order['id']]);
        $status = 'cancelled';
    } else {
        $status = $order['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout Cancelled — CodeCraft</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="result-page" style="max-width: 600px; margin: 40px auto; padding: 24px; text-align: center; font-family: sans-serif;">
    <h1 style="color: #e11d48;">Checkout Cancelled</h1>
    <p>Order <strong data-cy="order-code"><?= htmlspecialchars($order_code) ?></strong> was cancelled. No payment was captured.</p>
    <p style="font-size: 14px; margin-top: 16px;">
        Order Status: <span data-cy="order-status" style="font-weight: bold; color: #dc2626;"><?= htmlspecialchars($status) ?></span>
    </p>
    <p style="margin-top: 24px;"><a href="index.php">&larr; Back to store</a></p>
</main>
</body>
</html>
