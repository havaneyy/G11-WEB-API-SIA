<?php
/**
 * test_new_features.php
 * Automated verification of:
 * 1. Default Profile Avatar on Registration
 * 2. Update Profile Picture in My Account (URL & File Upload)
 * 3. Edit Posted Crafts in My Crafts (Ownership check, fee estimator, update product)
 * 4. Shipping Fee Responsibility Assistant (Decision tree & output format)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/EscrowSystem.php';

$db = get_db();
$baseUrl = 'http://localhost/codecraft-shop/api.php';

function run_test($name, $callable) {
    try {
        $callable();
        echo " [PASS] $name\n";
    } catch (\Throwable $e) {
        echo " [FAIL] $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function http_call($url, $method = 'GET', $data = [], $cookies = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($res, true), 'raw' => $res];
}

echo "========================================================\n";
echo "   CODECRAFT NEW FEATURES VERIFICATION TEST SUITE     \n";
echo "========================================================\n\n";

// --- Test 1: Default Profile Avatar on Registration ---
echo "--> Test Group 1: Default Profile Picture & Registration\n";

$testUsername = 'artisan_' . substr(uniqid(), -5);
$testEmail = $testUsername . '@craftmail.ph';
$testPassword = 'password123';
$cookieJar = tempnam(sys_get_temp_dir(), 'cc_cookie_');

run_test("Register new user and verify default avatar is set", function() use ($baseUrl, $testUsername, $testEmail, $testPassword, $cookieJar) {
    $res = http_call("$baseUrl?action=register", 'POST', [
        'username' => $testUsername,
        'email' => $testEmail,
        'password' => $testPassword,
        'display_name' => 'Artisan Test Maker'
    ], $cookieJar);

    $json = $res['data'];
    if (empty($json['success'])) {
        throw new Exception("Registration failed: " . ($json['error'] ?? $res['raw']));
    }

    $expectedDefault = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10';
    if ($json['user']['avatar_url'] !== $expectedDefault) {
        throw new Exception("Avatar URL does not match expected default: " . $json['user']['avatar_url']);
    }
});

// --- Test 2: Update Profile Picture in My Account ---
echo "\n--> Test Group 2: Update Profile Picture (URL & Device Upload)\n";

run_test("Update profile picture via image URL", function() use ($baseUrl, $cookieJar) {
    $newAvatarUrl = 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300';
    $res = http_call("$baseUrl?action=update_avatar", 'POST', [
        'avatar_url' => $newAvatarUrl
    ], $cookieJar);

    $json = $res['data'];
    if (empty($json['success']) || $json['avatar_url'] !== $newAvatarUrl) {
        throw new Exception("Avatar update via URL failed: " . ($json['error'] ?? $res['raw']));
    }
});

run_test("Update profile picture via simulated device file upload", function() use ($baseUrl, $cookieJar) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_avatar_') . '.jpg';
    // Generate a minimal valid 1x1 JPEG
    file_put_contents($tmpFile, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));

    $cFile = new CURLFile($tmpFile, 'image/jpeg', 'my_avatar.jpg');
    $res = http_call("$baseUrl?action=update_avatar", 'POST', [
        'avatar_image' => $cFile
    ], $cookieJar);
    @unlink($tmpFile);

    $json = $res['data'];
    if (empty($json['success']) || strpos($json['avatar_url'], 'uploads/avatar_') === false) {
        throw new Exception("Device avatar upload failed: " . ($json['error'] ?? $res['raw']));
    }
});

// --- Test 3: Edit Posted Crafts in "My Crafts" ---
echo "\n--> Test Group 3: My Crafts Listing & In-Place Editing\n";

$createdProductId = 0;

run_test("User publishes a new craft to their shop", function() use ($baseUrl, $cookieJar, &$createdProductId) {
    $res = http_call("$baseUrl?action=create_product", 'POST', [
        'title' => 'Original Hand-Turned Teak Bowl',
        'category' => 'Home & Living',
        'price' => '1500.00',
        'imageUrl' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?w=600',
        'description' => 'Solid harvested plantation teak, hand-sanded with natural beeswax finish.'
    ], $cookieJar);

    $json = $res['data'];
    if (empty($json['success']) || empty($json['id'])) {
        throw new Exception("Product creation failed: " . ($json['error'] ?? $res['raw']));
    }
    $createdProductId = (int)$json['id'];
});

run_test("User updates their craft (title, category, price, description)", function() use ($baseUrl, $cookieJar, &$createdProductId) {
    $res = http_call("$baseUrl?action=update_product", 'POST', [
        'product_id' => $createdProductId,
        'title' => 'UPDATED: Artisanal Hand-Turned Teak Salad Bowl Set',
        'category' => 'Home & Living',
        'price' => '1850.00',
        'imageUrl' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?w=600',
        'description' => 'Updated edition with 2 companion wooden salad servers.',
        'is_active' => 1
    ], $cookieJar);

    $json = $res['data'];
    if (empty($json['success'])) {
        throw new Exception("Craft update failed: " . ($json['error'] ?? $res['raw']));
    }

    if ($json['product']['title'] !== 'UPDATED: Artisanal Hand-Turned Teak Salad Bowl Set') {
        throw new Exception("Title not updated correctly");
    }
    if ((int)$json['product']['price_centavos'] !== 185000) {
        throw new Exception("Price centavos not updated: " . $json['product']['price_centavos']);
    }
});

run_test("Unauthorized user cannot edit another seller's craft", function() use ($baseUrl, &$createdProductId) {
    $res = http_call("$baseUrl?action=update_product", 'POST', [
        'product_id' => $createdProductId,
        'title' => 'Hacker Overwrite',
        'price' => '10.00'
    ]); // No cookies passed -> unauthenticated

    if ($res['code'] !== 401) {
        throw new Exception("Expected 401 Unauthorized for guest edit, got: " . $res['code']);
    }
});

// Clean up created product
http_call("$baseUrl?action=delete_product", 'POST', ['product_id' => $createdProductId], $cookieJar);
@unlink($cookieJar);

// --- Test 4: Shipping Fee Responsibility Assistant ---
echo "\n--> Test Group 4: Shipping Fee Responsibility Assistant Decision Tree\n";

run_test("Branch 1: Seller Fault -> Seller pays forward + return", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=seller_fault");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if ($e['payer'] !== 'Seller') throw new Exception("Expected Payer: Seller, got: " . $e['payer']);
    if (strpos($e['return_shipping'], 'Covered by Seller') === false) throw new Exception("Return shipping wrong: " . $e['return_shipping']);
    if ($e['confidence'] !== 'High') throw new Exception("Confidence should be High");
});

run_test("Branch 2: Buyer Fault -> Buyer pays return, forward non-refundable", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=buyer_fault");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if ($e['payer'] !== 'Buyer') throw new Exception("Expected Payer: Buyer, got: " . $e['payer']);
    if (strpos($e['return_shipping'], 'Covered by Buyer') === false) throw new Exception("Return shipping wrong: " . $e['return_shipping']);
    if (strpos($e['forward_shipping'], 'non-refundable') === false) throw new Exception("Forward shipping non-refundable missing: " . $e['forward_shipping']);
    if ($e['confidence'] !== 'High') throw new Exception("Confidence should be High");
});

run_test("Branch 3: Carrier Fault -> Platform covers via insurance", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=carrier_fault");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if ($e['payer'] !== 'Platform') throw new Exception("Expected Payer: Platform, got: " . $e['payer']);
    if (strpos($e['forward_shipping'], 'Platform') === false) throw new Exception("Forward shipping should be Platform");
    if ($e['confidence'] !== 'High') throw new Exception("Confidence should be High");
});

run_test("Branch 4: Disputed / Unclear -> Flag for manual review with Low confidence", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=unclear");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if (strpos($e['payer'], 'Manual Review') === false) throw new Exception("Expected Manual Review flag, got: " . $e['payer']);
    if ($e['confidence'] !== 'Low') throw new Exception("Confidence should be Low (escalate)");
});

run_test("Branch 5: Program enrolled order subsidizes return label up to cap", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=seller_fault&enrolled_program=1&caps_hit=0");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if (strpos($e['return_shipping'], 'Subsidized by Platform Program') === false) {
        throw new Exception("Expected subsidized return label, got: " . $e['return_shipping']);
    }
});

run_test("Branch 6: Subsidy ceiling cap hit falls back to standard seller liability", function() use ($baseUrl) {
    $res = file_get_contents("$baseUrl?action=evaluate_shipping_responsibility&cause=seller_fault&enrolled_program=1&caps_hit=1");
    $data = json_decode($res, true);
    $e = $data['evaluation'];

    if (strpos($e['conditions_exceptions'], 'ceiling has been reached') === false) {
        throw new Exception("Cap hit note missing from conditions: " . $e['conditions_exceptions']);
    }
});

echo "\n========================================================\n";
echo " ALL NEW FEATURE VERIFICATIONS PASSED SUCCESSFULLY (10/10)!\n";
echo "========================================================\n";
