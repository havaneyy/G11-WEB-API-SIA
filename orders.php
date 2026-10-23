<?php
/**
 * orders.php — Order Service API
 * Provides endpoints for Order Creation & Status Verification in Cypress E2E tests
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/EscrowSystem.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// Parse path info e.g. /orders.php/123 or /orders/123
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$requestedId = null;
if ($pathInfo) {
    $segments = array_values(array_filter(explode('/', trim($pathInfo, '/'))));
    if (!empty($segments[0]) && is_numeric($segments[0])) {
        $requestedId = (int)$segments[0];
    }
}
if (!$requestedId) {
    $requestedId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
}
$orderCode = $_GET['order'] ?? $_GET['order_code'] ?? null;

if ($method === 'GET') {
    if (!$requestedId && !$orderCode) {
        // Return latest orders or list
        $stmt = $db->query("SELECT id, order_code, buyer_id, seller_id, status, gross_amount, net_amount, total_centavos, created_at FROM orders ORDER BY id DESC LIMIT 50");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($requestedId) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$requestedId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$orderCode]);
    }
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Fetch order items
    $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$order['id']]);
    $items = $itemStmt->fetchAll();

    $response = [
        'id' => (int)$order['id'],
        'order_code' => $order['order_code'],
        'status' => $order['status'],
        'gross_amount' => (int)$order['gross_amount'],
        'net_amount' => (int)$order['net_amount'],
        'total' => (int)($order['total_centavos'] ?: $order['gross_amount']),
        'total_centavos' => (int)($order['total_centavos'] ?: $order['gross_amount']),
        'buyer_id' => (int)$order['buyer_id'],
        'seller_id' => (int)$order['seller_id'],
        'items' => $items,
        'created_at' => $order['created_at'],
        'success' => true
    ];
    echo json_encode($response);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?: $_POST;

    $items = $body['items'] ?? [];
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot create order with an empty cart or items list']);
        exit;
    }

    $buyerId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
    $primarySellerId = 1;
    $totalCentavos = 0;
    $lineItems = [];

    foreach ($items as $it) {
        $pid = (int)($it['productId'] ?? ($it['product_id'] ?? ($it['id'] ?? 0)));
        $qty = (int)($it['quantity'] ?? ($it['qty'] ?? 1));
        if ($qty <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid quantity']);
            exit;
        }

        $pStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $pStmt->execute([$pid]);
        $prod = $pStmt->fetch();

        if ($prod) {
            $price = (int)$prod['price_centavos'];
            $name = $prod['name'];
            $seller = (int)($prod['user_id'] ?: 1);
        } else {
            // Fallback for custom or direct item
            $price = (int)($it['price_centavos'] ?? ($it['amount'] ?? 35000));
            $name = $it['name'] ?? ('Product #' . $pid);
            $seller = 1;
        }

        $primarySellerId = $seller;
        $totalCentavos += ($price * $qty);
        $lineItems[] = [
            'product_id' => $pid,
            'name' => $name,
            'price_centavos' => $price,
            'quantity' => $qty
        ];
    }

    if ($totalCentavos <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid order total']);
        exit;
    }

    $fees = FeeCalculator::calculate($totalCentavos, 'Fashion', false);
    $orderCode = 'ORD-' . strtoupper(substr(uniqid(), -6));

    $orderStmt = $db->prepare(
        "INSERT INTO orders (
            order_code, buyer_id, seller_id, gross_amount, commission_fee, transaction_fee, shipping_fee,
            growth_support_fee, processing_fee, program_fee, net_amount, total_centavos, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $orderStmt->execute([
        $orderCode, $buyerId, $primarySellerId, $fees['gross_amount'], $fees['commission_fee'],
        $fees['transaction_fee'], $fees['shipping_fee'], $fees['growth_support_fee'],
        $fees['processing_fee'], $fees['program_fee'], $fees['net_amount'], $totalCentavos
    ]);
    $orderId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, price_centavos, quantity) VALUES (?, ?, ?, ?, ?)");
    foreach ($lineItems as $li) {
        $itemStmt->execute([$orderId, $li['product_id'], $li['name'], $li['price_centavos'], $li['quantity']]);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $orderId,
        'order_code' => $orderCode,
        'status' => 'pending',
        'total' => $totalCentavos,
        'total_centavos' => $totalCentavos,
        'gross_amount' => $fees['gross_amount'],
        'net_amount' => $fees['net_amount']
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
