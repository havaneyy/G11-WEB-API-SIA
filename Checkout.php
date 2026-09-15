<?php


require_once __DIR__ . '/config.php';
$db = get_db();


$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    header('Location: cart.php');
    exit;
}


$ids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();


if (!$products) {
    die('Cart items no longer exist.');
}


$line_items = [];
$total = 0;
foreach ($products as $p) {
    $qty = $cart[$p['id']];
    $total += $qty * $p['price_centavos'];
    $line_items[] = [
        'name' => $p['name'],
        'quantity' => $qty,
        'amount' => (int)$p['price_centavos'],
        'currency' => 'PHP',
        'description' => $p['description'] ?: $p['name'],
    ];
}


$order_code = 'ORD-' . strtoupper(substr(uniqid(), -6));
$db->prepare("INSERT INTO orders (order_code, status, total_centavos) VALUES (?, 'pending', ?)")
   ->execute([$order_code, $total]);
$order_id = $db->lastInsertId();


$itemStmt = $db->prepare(
    "INSERT INTO order_items (order_id, product_id, name, price_centavos, quantity) VALUES (?,?,?,?,?)"
);
foreach ($products as $p) {
    $qty = $cart[$p['id']];
    $itemStmt->execute([$order_id, $p['id'], $p['name'], $p['price_centavos'], $qty]);
}




$success_url = APP_BASE_URL . '/success.php?order=' . urlencode($order_code);
$cancel_url  = APP_BASE_URL . '/cancel.php?order=' . urlencode($order_code);


$payload = [
    'data' => [
        'attributes' => [
            'line_items' => $line_items,
            'payment_method_types' => ['card', 'gcash', 'paymaya'],
            'name' => $name,
            'description' => 'Order #' . $order_code,
            'send_email_receipt' => false,
            'show_line_items' => true,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
        ],
    ],
];


$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);


if ($curl_error) {
    die('Could not reach PayMongo: ' . htmlspecialchars($curl_error));
}


$result = json_decode($response, true);


if ($http_code !== 200 || empty($result['data']['attributes']['checkout_url'])) {


    $errMsg = $result['errors'][0]['detail'] ?? 'Unknown error creating checkout session.';
    die('PayMongo error: ' . htmlspecialchars($errMsg) .
        '<br><br>Check that PAYMONGO_SECRET_KEY in config.php is a valid sk_test_ key.');
}


$checkout_url = $result['data']['attributes']['checkout_url'];
$session_id = $result['data']['id'];


$db->prepare("UPDATE orders SET checkout_session_id = ? WHERE id = ?")
   ->execute([$session_id, $order_id]);


unset($_SESSION['cart']);




header('Location: ' . $checkout_url);
exit;
