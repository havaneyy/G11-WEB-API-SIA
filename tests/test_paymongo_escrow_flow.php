<?php
/**
 * test_paymongo_escrow_flow.php
 * Verifies the end-to-end 3-phase flow:
 * [User Buys] ➡️ [PayMongo Interface] ➡️ [System Escrow Locked] ➡️ [Seller Payout (After 7 Days / Confirmation)]
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/EscrowSystem.php';

$db = get_db();
$baseUrl = 'http://localhost/codecraft-shop/api.php';

function assert_test($cond, $msg) {
    if (!$cond) {
        echo " [FAIL] $msg\n";
        exit(1);
    }
    echo " [PASS] $msg\n";
}

echo "========================================================\n";
echo "   PAYMONGO & ESCROW 3-PHASE LIFECYCLE TEST SUITE       \n";
echo "========================================================\n\n";

// --- Phase 1: Checkout Session with PayMongo ---
echo "--> Phase 1: User Buys -> Redirect to PayMongo Interface\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'pm_cookie_');

// 1. Login as buyer
$ch = curl_init("$baseUrl?action=login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['username' => 'lin_design', 'password' => 'password']
]);
$loginRes = json_decode(curl_exec($ch), true);
curl_close($ch);
assert_test(!empty($loginRes['success']), "Logged in as buyer (Lin_Aesthetic)");

// 2. Call process_checkout
$payload = [
    'items' => [
        [
            'id' => 1,
            'title' => 'Artisanal Ceramic Vase',
            'price' => 750.00,
            'qty' => 1,
            'user_id' => 1
        ]
    ],
    'shipping_name' => 'Maria Santos',
    'shipping_phone' => '+63 917 555 1234',
    'shipping_address' => 'Unit 12B, Salcedo Village, Makati City',
    'payment_method' => 'gcash'
];

$ch = curl_init("$baseUrl?action=process_checkout");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$checkoutRes = json_decode(curl_exec($ch), true);
curl_close($ch);

assert_test(!empty($checkoutRes['success']), "Order created successfully: " . ($checkoutRes['order_code'] ?? ''));
assert_test(!empty($checkoutRes['checkout_url']), "PayMongo Checkout Session generated: " . ($checkoutRes['checkout_url'] ?? ''));
assert_test(strpos($checkoutRes['checkout_url'], 'checkout.paymongo.com') !== false, "Checkout URL redirects to PayMongo domain");
assert_test($checkoutRes['status'] === 'pending', "Order initial status is 'pending' (awaiting PayMongo charge)");

$orderCode = $checkoutRes['order_code'];
$orderId = (int)$checkoutRes['order_id'];

// Check database state
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
assert_test(!empty($order['checkout_session_id']), "Checkout Session ID saved on Order record: " . $order['checkout_session_id']);

// --- Phase 2: The Escrow Phase (Locked in Company Account) ---
echo "\n--> Phase 2: Escrow Phase -> Locked in System Account\n";

// Get seller initial wallet
$repo = new EscrowRepository($db);
$sellerId = (int)$order['seller_id'];
$initialWallet = $repo->getWallet($sellerId);

// Simulate payment verified on PayMongo & callback to success.php
$db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$orderId]);

$escrow = new EscrowService($db);
$escrow->capture($orderId, $sellerId, (int)$order['gross_amount'], (int)$order['net_amount'], (int)$order['buyer_id']);

$walletAfterEscrow = $repo->getWallet($sellerId);
$expectedPending = (int)$initialWallet['pending_balance'] + (int)$order['net_amount'];

if ((int)$walletAfterEscrow['pending_balance'] !== (int)$expectedPending) {
    echo "Actual pending: " . $walletAfterEscrow['pending_balance'] . ", Expected: " . $expectedPending . "\n";
}
assert_test((int)$walletAfterEscrow['pending_balance'] === (int)$expectedPending, "Money is flagged as 'Locked / Escrowed' in seller pending_balance (₱" . number_format($expectedPending/100, 2) . ")");
assert_test((int)$walletAfterEscrow['available_balance'] === (int)$initialWallet['available_balance'], "Money has NOT gone to seller's available balance yet");

// --- Phase 3: The Release Phase (Buyer Confirms Delivery / 7 Days) ---
echo "\n--> Phase 3: The Release Phase -> Delivery Confirmed / Guarantee Expiry\n";

// Buyer marks order delivered and confirms receipt
$escrow->markDelivered($orderId, (int)$order['buyer_id'], 7);

$releaseRes = $escrow->release($orderId, (int)$order['buyer_id'], 'Buyer confirmed order received');
assert_test(!empty($releaseRes['success']), "Escrow released to seller wallet: " . $releaseRes['message']);

$walletFinal = $repo->getWallet($sellerId);
$expectedAvailable = (int)$initialWallet['available_balance'] + (int)$order['net_amount'];
if ((int)$walletFinal['available_balance'] !== (int)$expectedAvailable) {
    echo "Actual available: " . $walletFinal['available_balance'] . ", Expected: " . $expectedAvailable . "\n";
}
assert_test((int)$walletFinal['available_balance'] === (int)$expectedAvailable, "Net funds released to seller available_balance (ready for bank payout)");
assert_test((int)$walletFinal['pending_balance'] === (int)$initialWallet['pending_balance'], "Pending balance cleared back (no lingering holds)");

@unlink($cookieJar);

echo "\n========================================================\n";
echo " ALL 3 PHASES OF PAYMONGO & ESCROW VERIFIED 100%!\n";
echo "========================================================\n";
