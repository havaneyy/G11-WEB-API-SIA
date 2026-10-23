<?php
require_once __DIR__ . '/auth.php';
require_admin();
$db = get_db();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $image = trim($_POST['image_url'] ?? '');
        if ($name === '' || $price === false || $price < 0) $error = 'Enter a product name and valid non-negative price.';
        else {
            $stmt = $db->prepare('INSERT INTO products (name, description, price_centavos, image_url) VALUES (?,?,?,?)');
            $stmt->execute([$name, $description, (int)round($price * 100), $image ?: null]);
            header('Location: products.php'); exit;
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE products SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
        header('Location: products.php'); exit;
    }
}
$products = $db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Manage Products — CodeCraft Admin</title><link rel="stylesheet" href="../assets/style.css"></head><body>
<header class="topbar"><h1>CodeCraft Admin</h1><nav class="admin-nav"><a href="../index.php">Store</a><a href="orders.php">Orders</a><a href="logout.php">Log out</a></nav></header>
<main class="admin-page"><h2>Add product</h2><?php if($error): ?><p><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="panel"><input type="hidden" name="action" value="add">
<p><label>Name<br><input name="name" required maxlength="150"></label></p>
<p><label>Description<br><textarea name="description"></textarea></label></p>
<p><label>Price in pesos (e.g. 350.00)<br><input type="number" name="price" min="0" step="0.01" required></label></p>
<p><label>Image URL (optional)<br><input name="image_url" maxlength="255" style="width:100%"></label></p><button>Add product</button></form>
<h2>Products</h2><table class="admin-table"><tr><th>ID</th><th>Name</th><th>Price</th><th>Active</th><th>Action</th></tr>
<?php foreach($products as $p): ?><tr><td><?= (int)$p['id'] ?></td><td><?= htmlspecialchars($p['name']) ?></td><td><?= format_pesos($p['price_centavos']) ?></td><td><?= $p['is_active'] ? 'Yes':'No' ?></td><td><form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button><?= $p['is_active'] ? 'Deactivate':'Activate' ?></button></form></td></tr><?php endforeach; ?>
</table></main></body></html>
