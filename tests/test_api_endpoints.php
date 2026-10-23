<?php
// tests/test_api_endpoints.php

// 1. Test storefront HTML
$html = file_get_contents('http://localhost/codecraft-shop/');
if (strpos($html, 'CodeCraft') === false) {
    die("FAIL: Storefront missing CodeCraft branding.\n");
}
echo "[PASS] Storefront loads with CodeCraft branding.\n";

// 2. Test get_products
$prods = json_decode(file_get_contents('http://localhost/codecraft-shop/api.php?action=get_products'), true);
if (empty($prods['success']) || count($prods['products']) === 0) {
    die("FAIL: Products API did not return products.\n");
}
echo "[PASS] Products API returns " . count($prods['products']) . " active crafts.\n";

// 3. Test checkout without auth (must fail with 401)
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['items' => [['id' => 1, 'price' => 100, 'qty' => 1]]]),
        'ignore_errors' => true
    ]
]);
$res = file_get_contents('http://localhost/codecraft-shop/api.php?action=process_checkout', false, $ctx);
$data = json_decode($res, true);
if (empty($data['error']) || strpos($data['error'], 'signed in or registered') === false) {
    die("FAIL: Unauthenticated checkout was not blocked properly: " . json_encode($data) . "\n");
}
echo "[PASS] Guest checkout strictly blocked: " . $data['error'] . "\n";

// 3b. Test craft creation without auth (must fail with 401)
$ctxCraft = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['title' => 'Unauthorized Craft', 'price' => '100', 'category' => 'Art']),
        'ignore_errors' => true
    ]
]);
$resCraft = file_get_contents('http://localhost/codecraft-shop/api.php?action=create_product', false, $ctxCraft);
$dataCraft = json_decode($resCraft, true);
if (empty($dataCraft['error']) || strpos($dataCraft['error'], 'Authentication required') === false) {
    die("FAIL: Unauthenticated craft creation was not blocked: " . json_encode($dataCraft) . "\n");
}
echo "[PASS] Guest craft creation strictly blocked: " . $dataCraft['error'] . "\n";

// 3c. Test craft photo upload without auth (must fail with 401)
$resUpload = file_get_contents('http://localhost/codecraft-shop/api.php?action=upload_image', false, $ctxCraft);
$dataUpload = json_decode($resUpload, true);
if (empty($dataUpload['error']) || strpos($dataUpload['error'], 'Authentication required') === false) {
    die("FAIL: Unauthenticated craft photo upload was not blocked: " . json_encode($dataUpload) . "\n");
}
echo "[PASS] Guest craft photo upload strictly blocked: " . $dataUpload['error'] . "\n";

// 4. Test seller income without auth (must fail with 401)
$resWallet = file_get_contents('http://localhost/codecraft-shop/api.php?action=get_seller_income', false, $ctx);
$dataWallet = json_decode($resWallet, true);
if (empty($dataWallet['error'])) {
    die("FAIL: Guest wallet access was not blocked.\n");
}
echo "[PASS] Guest wallet access strictly blocked: " . $dataWallet['error'] . "\n";

// 5. Test db_stats without admin auth (must fail with 403)
$resDb = file_get_contents('http://localhost/codecraft-shop/api.php?action=db_stats', false, $ctx);
$dataDb = json_decode($resDb, true);
if (empty($dataDb['error'])) {
    die("FAIL: Unauthorized DB stats access was not blocked.\n");
}
echo "[PASS] Guest XAMPP DB access strictly blocked: " . $dataDb['error'] . "\n";

// 6. Test get_order_history for authenticated user
$cookieJar = tempnam(sys_get_temp_dir(), 'api_test_cookie_');
$ch = curl_init('http://localhost/codecraft-shop/api.php?action=login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['username' => 'lin_design', 'password' => 'password']
]);
curl_exec($ch);
curl_close($ch);

$ch = curl_init('http://localhost/codecraft-shop/api.php?action=get_order_history');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar
]);
$orderRes = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($orderRes['success']) || !is_array($orderRes['orders'])) {
    die("FAIL: get_order_history failed: " . json_encode($orderRes) . "\n");
}
echo "[PASS] get_order_history succeeded! Loaded " . count($orderRes['orders']) . " orders without JSON_ARRAYAGG errors.\n";

// 7. Test get_notifications for authenticated user
$ch = curl_init('http://localhost/codecraft-shop/api.php?action=get_notifications');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar
]);
$notifRes = json_decode(curl_exec($ch), true);
curl_close($ch);
@unlink($cookieJar);

if (empty($notifRes['success']) || !isset($notifRes['unread_count'])) {
    die("FAIL: get_notifications failed: " . json_encode($notifRes) . "\n");
}
echo "[PASS] get_notifications succeeded! User has " . count($notifRes['notifications']) . " notifications (" . $notifRes['unread_count'] . " unread).\n";

echo "\n>>> ALL ENDPOINT SECURITY & RESTRICTIONS PASSED SUCCESSFULLY! <<<\n";
