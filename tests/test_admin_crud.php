<?php
// tests/test_admin_crud.php
$cookieJar = tempnam(sys_get_temp_dir(), 'cc_cookie_');

function http_req($url, $method = 'GET', $data = [], $cookies = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

echo "1. Logging in as Admin (admin / admin123)...\n";
$loginRes = http_req('http://localhost/codecraft-shop/api.php?action=login', 'POST', [
    'username' => 'admin',
    'password' => 'admin123'
], $cookieJar);
if (empty($loginRes['success'])) {
    die("FAIL: Admin login failed: " . json_encode($loginRes) . "\n");
}
echo "   [PASS] Logged in as Admin: " . $loginRes['user']['display_name'] . "\n";

echo "2. Testing admin_get_schema for table 'products'...\n";
$schemaRes = http_req('http://localhost/codecraft-shop/api.php?action=admin_get_schema&table=products', 'GET', [], $cookieJar);
if (empty($schemaRes['success']) || count($schemaRes['columns']) === 0) {
    die("FAIL: Failed to get schema: " . json_encode($schemaRes) . "\n");
}
echo "   [PASS] Schema returned " . count($schemaRes['columns']) . " columns for products.\n";

echo "3. Testing admin_create_row (CREATE)...\n";
$createRes = http_req('http://localhost/codecraft-shop/api.php?action=admin_create_row', 'POST', [
    'table' => 'products',
    'fields' => json_encode([
        'name' => 'Automated Test Handcrafted Mug',
        'description' => 'Created via Admin XAMPP CRUD interface',
        'price_centavos' => 85000,
        'image_url' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600',
        'category' => 'Home & Living',
        'likes_count' => 5,
        'user_id' => 1,
        'is_active' => 1
    ])
], $cookieJar);
if (empty($createRes['success']) || empty($createRes['id'])) {
    die("FAIL: Create row failed: " . json_encode($createRes) . "\n");
}
$newId = $createRes['id'];
echo "   [PASS] Created product row with ID #$newId: " . $createRes['message'] . "\n";

echo "4. Testing admin_update_row (UPDATE)...\n";
$updateRes = http_req('http://localhost/codecraft-shop/api.php?action=admin_update_row', 'POST', [
    'table' => 'products',
    'id' => $newId,
    'fields' => json_encode([
        'name' => 'Automated Test Handcrafted Mug (Updated Edition)',
        'price_centavos' => 95000
    ])
], $cookieJar);
if (empty($updateRes['success'])) {
    die("FAIL: Update row failed: " . json_encode($updateRes) . "\n");
}
echo "   [PASS] " . $updateRes['message'] . "\n";

echo "5. Testing admin_delete_row (DELETE)...\n";
$deleteRes = http_req('http://localhost/codecraft-shop/api.php?action=admin_delete_row', 'POST', [
    'table' => 'products',
    'id' => $newId
], $cookieJar);
if (empty($deleteRes['success'])) {
    die("FAIL: Delete row failed: " . json_encode($deleteRes) . "\n");
}
echo "   [PASS] " . $deleteRes['message'] . "\n";

@unlink($cookieJar);
echo "\n>>> FULL ADMIN CRUD LIFECYCLE (CREATE, READ, UPDATE, DELETE) VERIFIED 100%! <<<\n";
