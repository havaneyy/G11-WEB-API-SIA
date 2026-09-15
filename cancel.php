<?php
require_once __DIR__ . '/config.php';
$db = get_db();
$order_code = $_GET['order'] ?? '';


$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch();


if ($order && $order['status'] === 'pending') {
    $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$order['id']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout Cancelled</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="result-page">
    <h1>Checkout cancelled</h1>
    <p>Order <strong><?= htmlspecialchars($order_code) ?></strong> was cancelled. No payment was made.</p>
    <p><a href="index.php">&larr; Back to store</a></p>
</main>
</body>
</html>
