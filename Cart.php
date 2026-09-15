<?php
require_once __DIR__ . '/config.php';
$db = get_db();


if (!isset($_SESSION['cart'])) $_SESSION['cart'] = []; 




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);


    if ($action === 'add' && $pid) {
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
    } elseif ($action === 'remove' && $pid) {
        unset($_SESSION['cart'][$pid]);
    } elseif ($action === 'set_qty' && $pid) {
        $qty = max(0, (int)($_POST['quantity'] ?? 1));
        if ($qty === 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = $qty;
        }
    }
    header('Location: cart.php');
    exit;
}


$cart = $_SESSION['cart'];
$items = [];
$total = 0;
if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $qty = $cart[$row['id']];
        $subtotal = $qty * $row['price_centavos'];
        $total += $subtotal;
        $items[] = $row + ['qty' => $qty, 'subtotal' => $subtotal];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Cart</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <h1><a href="index.php">SIA1 Merch Store</a></h1>
</header>


<main class="cart-page">
    <h2>Your Cart</h2>


    <?php if (!$items): ?>
        <p>Your cart is empty. <a href="index.php">Go shopping</a>.</p>
    <?php else: ?>
        <table class="cart-table">
            <tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><?= format_pesos($it['price_centavos']) ?></td>
                <td>
                    <form method="post" action="cart.php" class="qty-form">
                        <input type="hidden" name="action" value="set_qty">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <input type="number" name="quantity" value="<?= $it['qty'] ?>" min="0" style="width:60px">
                        <button type="submit">Update</button>
                    </form>
                </td>
                <td><?= format_pesos($it['subtotal']) ?></td>
                <td>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <button type="submit">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="total">Total: <?= format_pesos($total) ?></p>
        <form method="post" action="checkout.php">
            <button type="submit" class="checkout-btn">Proceed to PayMongo Checkout</button>
        </form>
    <?php endif; ?>


    <p><a href="index.php">&larr; Continue shopping</a></p>
</main>
</body>
</html>
