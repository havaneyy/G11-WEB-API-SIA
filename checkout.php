<?php
/**
 * checkout.php  — the "Payment Service"
 *
 * This is the ONLY file in the app that talks to PayMongo.
 * Flow:
 *   1. Read the cart (Order data) from JSON request or session.
 *   2. Create a `pending` order + order_items row in the DB (Order Service responsibility).
 *   3. Call PayMongo's Checkout Session API with the line items.
 *   4. Return JSON with checkout_url (for fetch/Cypress) or redirect the customer's browser.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/EscrowSystem.php';
$db = get_db();

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
$isJsonRequest = !empty($jsonBody) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

// Authenticated or guest/test buyer ID
$buyerId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$cart = [];
if (!empty($jsonBody['items']) && is_array($jsonBody['items'])) {
    foreach ($jsonBody['items'] as $item) {
        $pid = (int)($item['productId'] ?? ($item['product_id'] ?? ($item['id'] ?? 0)));
        $qty = (int)($item['quantity'] ?? ($item['qty'] ?? 1));
        if ($pid > 0 && $qty > 0) {
            $cart[$pid] = ($cart[$pid] ?? 0) + $qty;
        }
    }
} elseif (!empty($_SESSION['cart'])) {
    $cart = $_SESSION['cart'];
}

if (empty($cart)) {
    if ($isJsonRequest) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cart is empty or invalid quantity.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

// ---- 1. Load product data for the items in the cart ----
$ids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();

if (!$products) {
    if ($isJsonRequest) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cart items no longer exist.']);
        exit;
    }
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
        'amount' => (int)$p['price_centavos'], // centavos, per PayMongo's requirement
        'currency' => 'PHP',
        'description' => $p['description'] ?: $p['name'],
    ];
}

// ---- 2. Create the order record (status: pending) ----
$primarySellerId = !empty($products[0]['user_id']) ? (int)$products[0]['user_id'] : 1;
$fees = FeeCalculator::calculate($total, 'Fashion', false);
$order_code = 'ORD-' . strtoupper(substr(uniqid(), -6));

$orderStmt = $db->prepare(
    "INSERT INTO orders (
        order_code, buyer_id, seller_id, gross_amount, commission_fee, transaction_fee, shipping_fee,
        growth_support_fee, processing_fee, program_fee, net_amount, total_centavos, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
);
$orderStmt->execute([
    $order_code, $buyerId, $primarySellerId, $fees['gross_amount'], $fees['commission_fee'],
    $fees['transaction_fee'], $fees['shipping_fee'], $fees['growth_support_fee'],
    $fees['processing_fee'], $fees['program_fee'], $fees['net_amount'], $total
]);
$order_id = (int)$db->lastInsertId();

$itemStmt = $db->prepare(
    "INSERT INTO order_items (order_id, product_id, name, price_centavos, quantity) VALUES (?,?,?,?,?)"
);
foreach ($products as $p) {
    $qty = $cart[$p['id']];
    $itemStmt->execute([$order_id, $p['id'], $p['name'], $p['price_centavos'], $qty]);
}

// ---- 3. Call PayMongo's Checkout API ----
$success_url = APP_BASE_URL . '/success.php?order=' . urlencode($order_code);
$cancel_url  = APP_BASE_URL . '/cancel.php?order=' . urlencode($order_code);

$payload = [
    'data' => [
        'attributes' => [
            'line_items' => $line_items,
            'payment_method_types' => ['card', 'gcash', 'paymaya'],
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
    if ($isJsonRequest) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Could not reach PayMongo: ' . $curl_error]);
        exit;
    }
    http_response_code(500);
    die('Could not reach PayMongo: ' . htmlspecialchars($curl_error));
}

$result = json_decode($response, true);

if ($http_code !== 200 || empty($result['data']['attributes']['checkout_url'])) {
    $errMsg = $result['errors'][0]['detail'] ?? 'Payment unavailable';
    if ($isJsonRequest) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $errMsg]);
        exit;
    }
    http_response_code(500);
    die('PayMongo error: ' . htmlspecialchars($errMsg));
}

$checkout_url = $result['data']['attributes']['checkout_url'];
$session_id = $result['data']['id'];

// Save the session id on the order so success.php can verify it later
$db->prepare("UPDATE orders SET checkout_session_id = ? WHERE id = ?")
   ->execute([$session_id, $order_id]);

// Cart has been converted into an order — clear it
unset($_SESSION['cart']);

// ---- 4. Return JSON or redirect to PayMongo hosted checkout ----
if ($isJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $order_id,
        'order_id' => $order_id,
        'order_code' => $order_code,
        'status' => 'pending',
        'total' => $total,
        'total_centavos' => $total,
        'checkout_url' => $checkout_url
    ]);
    exit;
}

header('Location: ' . $checkout_url);
exit;
