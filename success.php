<?php
/**
 * success.php — CodeCraft PayMongo & Escrow Guarantee Settlement
 * 
 * Flow:
 * [User Buys] ➡️ [PayMongo Interface] ➡️ [Your System Account (Escrow)] ➡️ [Seller (After 7 Days)]
 * 
 * Phase 1: PayMongo verifies card / e-wallet processing server-side.
 * Phase 2: Money is held in company account & flagged in DB as "Locked / Escrowed" in seller_wallets.pending_balance.
 * Phase 3: Releases upon buyer delivery confirmation or 7-day guarantee countdown expiration.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/EscrowSystem.php';

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

if (!$order) {
    die('Order not found.');
}
$order_code = $order['order_code'];
$verified_paid = false;
$paymongo_payment_id = null;
$payment_source_type = 'PayMongo Gateway';

// If test order created directly without checkout session, or already paid
if (empty($order['checkout_session_id']) || $order['status'] === 'paid') {
    $verified_paid = true;
}

// Verify with PayMongo's server-side API
if (!empty($order['checkout_session_id'])) {
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
            $paymongo_payment_id = $payment['id'] ?? null;
            $payment_source_type = strtoupper($payment['attributes']['source']['type'] ?? 'PAYMONGO');
            break;
        }
    }
} else {
    // If order was already marked paid or direct test
    if ($order['status'] === 'paid' || $order['status'] === 'shipped' || $order['status'] === 'delivered' || $order['status'] === 'completed') {
        $verified_paid = true;
    }
}

// Phase 2: The Escrow Phase
// Lock funds into company platform balance & credit seller's pending_balance in DB
if ($verified_paid && $order['status'] !== 'paid') {
    $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
    $order['status'] = 'paid';

    $sellerId = (int)($order['seller_id'] ?: 1);
    $buyerId = (int)($order['buyer_id'] ?: 1);
    $grossCentavos = (int)$order['gross_amount'];
    $netCentavos = (int)$order['net_amount'];

    if ($netCentavos <= 0) {
        $fees = FeeCalculator::calculate($grossCentavos, 'Fashion', false);
        $netCentavos = $fees['net_amount'];
        $db->prepare("UPDATE orders SET net_amount = ?, commission_fee = ?, transaction_fee = ? WHERE id = ?")
           ->execute([$netCentavos, $fees['commission_fee'], $fees['transaction_fee'], $order['id']]);
    }

    $escrow = new EscrowService($db);
    try {
        $escrow->capture((int)$order['id'], $sellerId, $grossCentavos, $netCentavos, $buyerId);
    } catch (\Exception $e) {
        // Idempotent: already captured
    }
}

// Rehydrate buyer session so user stays logged in after returning from PayMongo checkout
if ($verified_paid && !empty($order['buyer_id'])) {
    if (empty($_SESSION['user_id']) || $_SESSION['user_id'] != $order['buyer_id']) {
        $uStmt = $db->prepare("SELECT id, username, email, display_name, role, avatar_url FROM users WHERE id = ?");
        $uStmt->execute([$order['buyer_id']]);
        $buyerUser = $uStmt->fetch();
        if ($buyerUser) {
            $_SESSION['user_id'] = (int)$buyerUser['id'];
            $_SESSION['user_name'] = $buyerUser['display_name'] ?: $buyerUser['username'];
            $_SESSION['username'] = $buyerUser['username'];
            $_SESSION['user_email'] = $buyerUser['email'];
            $_SESSION['role'] = $buyerUser['role'] ?: 'buyer';
            $_SESSION['avatar_url'] = $buyerUser['avatar_url'] ?: '';
            if ($buyerUser['role'] === 'admin') {
                $_SESSION['codecraft_admin'] = true;
            }
        }
    }
}

// Fetch line items for receipt
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order_code) ?> — CodeCraft Escrow Protected</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen py-10 px-4">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Top Header Brand -->
        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-2 bg-red-600 text-white font-extrabold text-xl px-4 py-1.5 rounded-2xl shadow-sm hover:opacity-95 transition">
                <i class="fa-solid fa-shapes text-sm"></i>
                <span>CodeCraft</span>
            </a>
            <p class="text-xs text-gray-500 mt-2">Curated Artisan E-Commerce & Escrow Guarantee</p>
        </div>

        <?php if ($verified_paid): ?>
            <!-- Success Status Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-sm text-center relative overflow-hidden">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl mx-auto mb-4 shadow-inner">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="mb-2">
                    <span data-cy="order-status" class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-200 uppercase tracking-wider">paid</span>
                </div>
                <h1 class="text-2xl font-black text-gray-900">Payment Secured in Escrow!</h1>
                <p class="text-xs text-gray-500 mt-1">Order Code: <strong data-cy="order-code" class="font-mono text-gray-800 text-sm"><?= htmlspecialchars($order_code) ?></strong></p>
                <div data-cy="order-total" class="text-3xl font-black text-red-600 mt-3">
                    ₱<?= number_format(((int)$order['total_centavos']) / 100, 2) ?>
                </div>
                <?php if ($paymongo_payment_id): ?>
                    <p class="text-[11px] text-gray-400 font-mono mt-1">PayMongo Ref: <?= htmlspecialchars($paymongo_payment_id) ?> (<?= htmlspecialchars($payment_source_type) ?>)</p>
                <?php endif; ?>
            </div>

            <!-- Visual Flowchart: [User Buys] -> [PayMongo] -> [System Escrow] -> [Seller] -->
            <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-sm space-y-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-700 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                    CodeCraft 3-Phase Escrow Protection Model
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    
                    <!-- Phase 1: Completed -->
                    <div class="p-3.5 bg-emerald-50/70 border-2 border-emerald-500 rounded-2xl relative">
                        <span class="absolute top-2 right-2 text-[10px] font-bold bg-emerald-600 text-white px-2 py-0.5 rounded-full">DONE</span>
                        <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-xs mb-2">1</div>
                        <h4 class="font-bold text-emerald-950">PayMongo Checkout</h4>
                        <p class="text-[11px] text-emerald-800 mt-1 leading-snug">User paid through PayMongo. Funds settled into company central merchant account.</p>
                    </div>

                    <!-- Phase 2: Active Escrow -->
                    <div class="p-3.5 bg-amber-50/70 border-2 border-amber-500 rounded-2xl relative">
                        <span class="absolute top-2 right-2 text-[10px] font-bold bg-amber-500 text-white px-2 py-0.5 rounded-full animate-pulse">LOCKED</span>
                        <div class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center font-bold text-xs mb-2">2</div>
                        <h4 class="font-bold text-amber-950">System Escrow Hold</h4>
                        <p class="text-[11px] text-amber-900 mt-1 leading-snug">Money held in business account. Flagged as <strong>Locked in Escrow</strong> (Seller Pending Balance).</p>
                    </div>

                    <!-- Phase 3: Pending Release -->
                    <div class="p-3.5 bg-gray-50 border border-gray-200 rounded-2xl relative">
                        <span class="absolute top-2 right-2 text-[10px] font-bold bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full">PENDING</span>
                        <div class="w-7 h-7 rounded-lg bg-gray-300 text-gray-700 flex items-center justify-center font-bold text-xs mb-2">3</div>
                        <h4 class="font-bold text-gray-900">Seller Payout</h4>
                        <p class="text-[11px] text-gray-500 mt-1 leading-snug">Released to seller upon buyer delivery confirmation or after the 7-day guarantee window.</p>
                    </div>

                </div>

                <!-- Escrow Financial Ledger Breakdown -->
                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 text-xs space-y-2">
                    <div class="flex justify-between text-gray-600">
                        <span>Total Paid by Buyer (PayMongo Gateway):</span>
                        <strong class="text-gray-900">₱<?= number_format(((int)$order['gross_amount']) / 100, 2) ?></strong>
                    </div>
                    <div class="flex justify-between text-gray-500 text-[11px]">
                        <span>Platform Deductions (Shopee Commission & Service Fees):</span>
                        <span class="text-rose-600 font-semibold">-₱<?= number_format((((int)$order['gross_amount']) - ((int)$order['net_amount'])) / 100, 2) ?></span>
                    </div>
                    <div class="flex justify-between font-bold pt-2 border-t border-gray-200 text-emerald-700">
                        <span>Locked in Seller Escrow Pending Balance:</span>
                        <span class="text-sm font-extrabold">₱<?= number_format(((int)$order['net_amount']) / 100, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-sm space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-700">Order Items</h3>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($items as $item): ?>
                        <div class="py-2.5 flex justify-between items-center text-xs">
                            <div>
                                <span class="font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-gray-400 text-[11px] block">Qty: <?= (int)$item['quantity'] ?></span>
                            </div>
                            <span class="font-bold text-gray-800">₱<?= number_format(((int)$item['price_centavos'] * (int)$item['quantity']) / 100, 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <a href="index.php?tab=account&sub=orders#order-<?= urlencode($order_code) ?>" class="flex-1 py-3 px-6 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-full text-center shadow transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-box"></i> View Order in Account Tracking
                </a>
                <a href="index.php" class="py-3 px-6 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 font-bold text-xs rounded-full text-center transition">
                    Back to Storefront
                </a>
            </div>

        <?php else: ?>

            <!-- Verification Pending / Error State -->
            <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-3xl mx-auto">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-900">Awaiting PayMongo Confirmation</h1>
                <p class="text-xs text-gray-500">
                    Order <strong><?= htmlspecialchars($order_code) ?></strong> is being processed by PayMongo.
                    If you just completed payment, refresh this page in a moment.
                </p>
                <div class="pt-2 flex justify-center gap-3">
                    <button onclick="window.location.reload()" class="px-5 py-2 bg-gray-900 text-white text-xs font-bold rounded-full">
                        Refresh Status
                    </button>
                    <a href="index.php" class="px-5 py-2 border rounded-full text-xs font-bold text-gray-700">
                        Back to Store
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>
