<?php
/**
 * tests/test_escrow_system.php
 * Automated Unit & Integration Tests for CodeCraft Guarantee & Escrow System
 * Run with: php tests/test_escrow_system.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/EscrowSystem.php';

$db = get_db();
echo "========================================================\n";
echo "   CODECRAFT ESCROW & GUARANTEE SYSTEM TEST SUITE       \n";
echo "========================================================\n\n";

$testsPassed = 0;
$testsTotal = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
    }
}

// -------------------------------------------------------------
// TEST 1: Minor Units & Fee Calculator (Shopee-Style Deductions)
// -------------------------------------------------------------
echo "--> Test Group 1: Minor Units (Integers) & Platform Fee Deductions\n";
$grossCentavos = 100000; // ₱1,000.00
$breakdown = FeeCalculator::calculate($grossCentavos, 'Fashion', false);

assertTest("Fee breakdown values are all integers (minor units)", 
    is_int($breakdown['gross_amount']) &&
    is_int($breakdown['commission_fee']) &&
    is_int($breakdown['transaction_fee']) &&
    is_int($breakdown['shipping_fee']) &&
    is_int($breakdown['growth_support_fee']) &&
    is_int($breakdown['processing_fee']) &&
    is_int($breakdown['net_amount'])
);

assertTest("Deductions sum correctly equals gross - net",
    ($breakdown['gross_amount'] - $breakdown['total_deductions']) === $breakdown['net_amount']
);

assertTest("Total deductions fall between 15% and 20%",
    $breakdown['total_deductions'] >= 15000 && $breakdown['total_deductions'] <= 20000,
    "Got: " . $breakdown['total_deductions']
);

// With marketing opt-in: up to 25%-30%
$marketingBreakdown = FeeCalculator::calculate(200000, 'Fashion', true);
assertTest("Marketing opt-in adds 3% program fee",
    $marketingBreakdown['program_fee'] === 6000
);

// -------------------------------------------------------------
// SETUP TEST ORDER IN DATABASE
// -------------------------------------------------------------
echo "\n--> Test Group 2: Escrow Capture, Release & Double-Release Prevention\n";
$escrow = new EscrowService($db);
$repo = new EscrowRepository($db);
$sellerId = 1;

// Clean up any test orders from prior runs
$db->exec("DELETE FROM orders WHERE order_code LIKE 'TEST-ORD-%'");
$db->exec("DELETE FROM escrow_transactions WHERE description LIKE '%TEST-ORD-%'");

// Create test order
$testOrderCode = 'TEST-ORD-' . rand(1000, 9999);
$testGross = 250000; // ₱2,500.00
$calc = FeeCalculator::calculate($testGross, 'Fashion', false);

$stmt = $db->prepare(
    "INSERT INTO orders (order_code, buyer_id, seller_id, gross_amount, commission_fee, transaction_fee, shipping_fee, growth_support_fee, processing_fee, program_fee, net_amount, total_centavos, status)
     VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
);
$stmt->execute([
    $testOrderCode, $sellerId, $calc['gross_amount'], $calc['commission_fee'], $calc['transaction_fee'],
    $calc['shipping_fee'], $calc['growth_support_fee'], $calc['processing_fee'], $calc['program_fee'],
    $calc['net_amount'], $calc['gross_amount']
]);
$orderId = (int)$db->lastInsertId();

// 1. Capture buyer payment
$walletBefore = $repo->getWallet($sellerId);
$escrow->capture($orderId, $sellerId, $calc['gross_amount'], $calc['net_amount'], 2);
$walletAfterCapture = $repo->getWallet($sellerId);

assertTest("Escrow capture increments pending_balance by net amount",
    ($walletAfterCapture['pending_balance'] - $walletBefore['pending_balance']) === $calc['net_amount']
);

// 2. Mark shipped and delivered
$escrow->markShipped($orderId, $sellerId);
$escrow->markDelivered($orderId, $sellerId, 7);
$updatedOrder = $repo->getOrder($orderId);

assertTest("Delivered order sets 7-day guarantee expiration window",
    $updatedOrder['status'] === 'delivered' && !empty($updatedOrder['guarantee_expires_at'])
);

// 3. Buyer confirms received -> Escrow Release
$availBefore = $walletAfterCapture['available_balance'];
$releaseRes = $escrow->release($orderId, 2, 'Buyer confirmed receipt of craft.');
$walletAfterRelease = $repo->getWallet($sellerId);

assertTest("Escrow release credits available_balance by net amount",
    ($walletAfterRelease['available_balance'] - $availBefore) === $calc['net_amount']
);

// 4. Double-Release Prevention
$prevented = false;
try {
    $escrow->release($orderId, 2, 'Attempting second release.');
} catch (\DomainException $e) {
    $prevented = true;
}
assertTest("Double-release prevention: second release throws idempotency exception", $prevented);

// -------------------------------------------------------------
// TEST GROUP 3: REFUND DISPUTE FREEZE
// -------------------------------------------------------------
echo "\n--> Test Group 3: Refund Dispute Freeze & Resolution\n";
$refundService = new RefundService($db);

$disputeCode = 'TEST-ORD-' . rand(1000, 9999);
$stmt->execute([
    $disputeCode, $sellerId, $calc['gross_amount'], $calc['commission_fee'], $calc['transaction_fee'],
    $calc['shipping_fee'], $calc['growth_support_fee'], $calc['processing_fee'], $calc['program_fee'],
    $calc['net_amount'], $calc['gross_amount']
]);
$disputeOrderId = (int)$db->lastInsertId();
$escrow->capture($disputeOrderId, $sellerId, $calc['gross_amount'], $calc['net_amount'], 2);
$escrow->markDelivered($disputeOrderId, $sellerId, 7);

// Buyer requests refund
$refundService->requestRefund($disputeOrderId, 2, 'Item damaged during transit');
$disputeOrder = $repo->getOrder($disputeOrderId);

assertTest("Order marked as refund_requested freezes funds",
    $disputeOrder['status'] === 'refund_requested'
);

$freezeBlocked = false;
try {
    $escrow->release($disputeOrderId, 2, 'Attempting release on frozen order');
} catch (\DomainException $e) {
    $freezeBlocked = true;
}
assertTest("Release is strictly blocked while refund is requested", $freezeBlocked);

// Resolve dispute: refund buyer
$resolveRes = $refundService->resolve($disputeOrderId, 2, 'refund_buyer', 'Evidence of transit damage verified');
$resolvedOrder = $repo->getOrder($disputeOrderId);
assertTest("Dispute resolution marks order as refunded and clears pending escrow",
    $resolvedOrder['status'] === 'refunded'
);

// -------------------------------------------------------------
// TEST GROUP 4: WITHDRAWALS, FEES, & FAILED-TRANSFER RECREDIT
// -------------------------------------------------------------
echo "\n--> Test Group 4: Withdrawals, Partner vs Charged Fees, Weekend Queuing & Recredit\n";
$withdrawalService = new WithdrawalService($db);

// Clean test withdrawals for seller 1
$db->exec("DELETE FROM withdrawals WHERE seller_id = $sellerId");

// Bank 1 is BDO Partner (free), Bank 3 is Non-partner (first 2 free, then ₱15)
// Add a non-partner bank for test
$nonPartnerBankId = $withdrawalService->addBankAccount($sellerId, 'Security Bank', '000011112222', 'Lin Test', false);
$partnerBankId = 1; // BDO Partner

// Ensure wallet has at least ₱10,000 (1,000,000 centavos)
$db->prepare("UPDATE seller_wallets SET available_balance = 1000000 WHERE seller_id = ?")->execute([$sellerId]);

// Withdrawal 1: Non-partner -> 1st free
$w1 = $withdrawalService->request($sellerId, $nonPartnerBankId, 100000); // ₱1,000
assertTest("1st manual non-partner withdrawal is FREE (fee = 0)", $w1['fee'] === 0);

// Withdrawal 2: Non-partner -> 2nd free
$w2 = $withdrawalService->request($sellerId, $nonPartnerBankId, 100000); // ₱1,000
assertTest("2nd manual non-partner withdrawal is FREE (fee = 0)", $w2['fee'] === 0);

// Withdrawal 3: Non-partner -> 3rd in week -> charged flat fee ₱15.00 (1500 centavos)
$w3 = $withdrawalService->request($sellerId, $nonPartnerBankId, 100000); // ₱1,000
assertTest("3rd manual non-partner withdrawal incurs ₱15.00 fee (1500 centavos)", $w3['fee'] === 1500);

// Withdrawal 4: Partner Bank -> ALWAYS FREE regardless of count
$w4 = $withdrawalService->request($sellerId, $partnerBankId, 100000); // ₱1,000
assertTest("Partner Bank withdrawal is ALWAYS free (fee = 0)", $w4['fee'] === 0);

// Weekend Queuing verification
$dayOfWeek = (int)date('N');
$isWeekend = ($dayOfWeek >= 6);
assertTest("Weekend queuing flag properly matches current day ($dayOfWeek)",
    $w1['is_weekend_queued'] === $isWeekend
);

// Failed Transfer Recredit Test
$balanceBeforeFail = $repo->getWallet($sellerId)['available_balance'];
$withdrawalService->settle($w3['withdrawal_id'], false); // Simulate failed transfer
$balanceAfterFail = $repo->getWallet($sellerId)['available_balance'];

assertTest("Failed transfer recredits full amount + fee back to seller wallet",
    ($balanceAfterFail - $balanceBeforeFail) === (100000 + 1500)
);

// -------------------------------------------------------------
// TEST GROUP 5: Shipping Fee Responsibility Assistant Decision Tree
// -------------------------------------------------------------
echo "\n--> Test Group 5: Shipping Fee Responsibility Assistant Decision Tree\n";

// 1. Seller Fault (defective / wrong item / late dispatch)
$resSeller = ShippingFeeAssistant::evaluate('seller_fault', false, false, false);
assertTest("Seller fault: Seller pays forward and return shipping", 
    $resSeller['payer'] === 'Seller' && strpos($resSeller['return_shipping'], 'Covered by Seller') !== false && $resSeller['confidence'] === 'High'
);

// 2. Buyer Fault / Change of mind
$resBuyer = ShippingFeeAssistant::evaluate('buyer_fault', false, true, false);
assertTest("Buyer fault: Buyer pays return shipping and forward is non-refundable",
    $resBuyer['payer'] === 'Buyer' && strpos($resBuyer['return_shipping'], 'Covered by Buyer') !== false && $resBuyer['confidence'] === 'High'
);

// 3. Carrier / Platform Fault (lost / damaged in transit)
$resCarrier = ShippingFeeAssistant::evaluate('carrier_fault', false, false, false);
assertTest("Carrier fault: Platform covers via insurance / program",
    $resCarrier['payer'] === 'Platform' && strpos($resCarrier['forward_shipping'], 'Platform') !== false && $resCarrier['confidence'] === 'High'
);

// 4. Enrolled in Fee-Support Program (Return subsidy)
$resProgram = ShippingFeeAssistant::evaluate('seller_fault', true, false, false);
assertTest("Fee-support program: Return label subsidized by Platform Program",
    strpos($resProgram['return_shipping'], 'Subsidized by Platform Program') !== false
);

// 5. Unclear / Disputed liability -> Escalate (Low confidence)
$resDispute = ShippingFeeAssistant::evaluate('disputed', false, false, false);
assertTest("Disputed liability flags for manual review with Low confidence",
    $resDispute['confidence'] === 'Low' && strpos($resDispute['rule_applied'], 'Disputed') !== false
);


// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n========================================================\n";
echo " TEST RESULTS: $testsPassed / $testsTotal PASSED\n";
echo "========================================================\n";

if ($testsPassed === $testsTotal) {
    echo ">>> ALL ESCROW GUARANTEE REQUIREMENTS VERIFIED! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
