<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/NotificationService.php';

$db = get_db();
$notifService = new NotificationService($db);

$orders = $db->query("SELECT * FROM orders ORDER BY id ASC")->fetchAll();
echo "Found " . count($orders) . " orders to generate notifications for...\n";

foreach ($orders as $o) {
    $orderCode = $o['order_code'];
    $buyerId = (int)$o['buyer_id'];
    $sellerId = (int)$o['seller_id'];
    $totalPesos = number_format(((int)$o['total_centavos']) / 100, 2);
    $netPesos = number_format(((int)$o['net_amount']) / 100, 2);

    if ($buyerId) {
        $notifService->create(
            $buyerId,
            "Order Placed #$orderCode",
            "Your order of ₱$totalPesos is confirmed. Payment is held in Escrow under CodeCraft Guarantee.",
            'order',
            "#order-$orderCode"
        );

        if ($o['status'] === 'completed') {
            $notifService->create(
                $buyerId,
                "Delivery Confirmed #$orderCode",
                "You confirmed receipt of order #$orderCode. Thank you for supporting artisan creators!",
                'order',
                "#order-$orderCode"
            );
        }
    }

    if ($sellerId) {
        $notifService->create(
            $sellerId,
            "New Craft Sale! #$orderCode",
            "A buyer purchased your craft! ₱$netPesos net earnings will release to your wallet upon delivery.",
            'escrow',
            "#order-$orderCode"
        );

        if ($o['status'] === 'completed') {
            $notifService->create(
                $sellerId,
                "Escrow Released! #$orderCode",
                "₱$netPesos has been transferred to your Available Wallet balance ready for bank withdrawal.",
                'escrow',
                "#order-$orderCode"
            );
        }
    }
}

// Also add a welcome notification for user 4 (ranz) and user 1 (lin_design)
$notifService->create(
    4,
    "Welcome to CodeCraft Shop!",
    "Your artisan account is fully verified. Track orders and enjoy 100% Escrow Protection on every transaction.",
    'system',
    null
);

$notifService->create(
    1,
    "Welcome to CodeCraft Shop!",
    "Your artisan storefront is active. View your wallet and track craft orders in real time.",
    'system',
    null
);

$totalNotifs = $db->query("SELECT count(*) FROM notifications")->fetchColumn();
echo "Successfully populated! Total notifications in database: $totalNotifs\n";
