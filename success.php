<?php
require_once __DIR__ . '/config.php';
$db = get_db();


$order_code = $_GET['order'] ?? '';
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch();


if (!$order) {
    die('Order not found.');
}


$verified_paid = false;


if ($order['checkout_session_id']) {
    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $order['checkout_session_id']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);


    $payments = $result['data']['attributes']['payments'] ?? [];
    foreach ($payments as $payment) {
        if (($payment['attributes']['status'] ?? '') === 'paid') {
            $verified_paid = true;
            break;
        }
    }
}


if ($verified_paid && $order['status'] !== 'paid') {
    $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
    $order['status'] = 'paid';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order <?= htmlspecialchars($order_code) ?> — Success</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="result-page">
<?php if ($order['status'] === 'paid'): ?>
    <h1>✅ Payment received</h1>
    <p>Order <strong><?= htmlspecialchars($order_code) ?></strong> is now marked as <strong>paid</strong>.</p>
<?php else: ?>
    <h1>⚠️ Payment not yet confirmed</h1>
    <p>We couldn't verify payment for order <strong><?= htmlspecialchars($order_code) ?></strong> yet.
    If you completed a test payment, this can happen if PayMongo hasn't processed it yet — refresh in a moment.</p>
<?php endif; ?>
    <p><a href="index.php">&larr; Back to store</a> · <a href="admin/orders.php">View orders (admin)</a></p>
</main>
</body>
</html>



