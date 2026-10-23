<?php
require_once __DIR__ . '/auth.php';
require_admin();
$db = get_db();

// Fetch summary metrics
$metrics = [
    'total_orders' => (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'gross_cents'  => (int)$db->query("SELECT COALESCE(SUM(gross_amount), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn(),
    'escrow_cents' => (int)$db->query("SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE status IN ('paid', 'shipped', 'delivered')")->fetchColumn(),
    'released_cents' => (int)$db->query("SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
];

// Fetch orders with buyer and seller information
$query = "
    SELECT 
        o.*,
        b.username AS buyer_username,
        b.display_name AS buyer_name,
        b.email AS buyer_email,
        s.username AS seller_username,
        s.display_name AS seller_name
    FROM orders o
    LEFT JOIN users b ON o.buyer_id = b.id
    LEFT JOIN users s ON o.seller_id = s.id
    ORDER BY o.id DESC
";
$orders = $db->query($query)->fetchAll();

// Pre-fetch all order items grouped by order_id
$itemsStmt = $db->query("SELECT * FROM order_items ORDER BY id ASC");
$itemsByOrder = [];
while ($row = $itemsStmt->fetch()) {
    $itemsByOrder[$row['order_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Order Monitor & Escrow Control — CodeCraft</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <!-- TOP ADMIN NAVIGATION BAR -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="../index.php" class="flex items-center gap-2 bg-red-600 text-white font-extrabold text-base px-3.5 py-1.5 rounded-xl shadow-sm hover:opacity-95 transition">
                    <i class="fa-solid fa-shapes text-xs"></i>
                    <span>CodeCraft</span>
                </a>
                <span class="text-xs font-bold uppercase tracking-wider bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg border border-slate-200">
                    <i class="fa-solid fa-shield-halved text-red-600 mr-1"></i> Admin Portal
                </span>
            </div>

            <nav class="flex items-center gap-2 sm:gap-3 text-xs font-semibold">
                <a href="../index.php" class="px-3 py-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-store"></i> <span class="hidden sm:inline">Storefront</span>
                </a>
                <a href="../index.php?tab=database" class="px-3 py-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-database"></i> <span class="hidden sm:inline">XAMPP DB Explorer</span>
                </a>
                <a href="products.php" class="px-3 py-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-box"></i> <span class="hidden sm:inline">Products</span>
                </a>
                <a href="logout.php" class="px-3 py-2 text-rose-600 hover:bg-rose-50 rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <span class="hidden sm:inline">Log out</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- TITLE & SUBTITLE -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check text-red-600"></i>
                    <span>Order Monitoring & Escrow Management</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Supervise customer orders, shipping logistics, PayMongo payment verification, and manual escrow releases.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.location.reload()" class="px-3.5 py-2 bg-white hover:bg-slate-100 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </button>
                <a href="../index.php?tab=database" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-table-cells"></i> Open Database CRUD
                </a>
            </div>
        </div>

        <!-- KPI METRICS SUMMARY CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Orders</span>
                    <span class="text-xl font-black text-slate-900"><?= number_format($metrics['total_orders']) ?></span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Gross Sales</span>
                    <span class="text-xl font-black text-slate-900">₱<?= number_format($metrics['gross_cents'] / 100, 2) ?></span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Active Escrow Locked</span>
                    <span class="text-xl font-black text-amber-600">₱<?= number_format($metrics['escrow_cents'] / 100, 2) ?></span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Disbursed to Sellers</span>
                    <span class="text-xl font-black text-emerald-600">₱<?= number_format($metrics['released_cents'] / 100, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- FILTER & SEARCH TOOLBAR -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 flex-wrap w-full md:w-auto text-xs font-semibold">
                <button onclick="filterStatus('all')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-900 text-white shadow-sm" data-status="all">All</button>
                <button onclick="filterStatus('paid')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="paid">Paid (Escrow Held)</button>
                <button onclick="filterStatus('shipped')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="shipped">Shipped</button>
                <button onclick="filterStatus('delivered')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="delivered">Delivered</button>
                <button onclick="filterStatus('completed')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="completed">Completed</button>
                <button onclick="filterStatus('refund_requested')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="refund_requested">Refund Requested</button>
                <button onclick="filterStatus('refunded')" class="status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200" data-status="refunded">Refunded</button>
            </div>

            <div class="relative w-full md:w-72">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                <input type="text" id="orderSearchInput" oninput="applyOrderSearch()" placeholder="Search code, buyer, address..." 
                       class="w-full pl-8 pr-3 py-2 text-xs rounded-xl border border-slate-200 focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none bg-slate-50">
            </div>
        </div>

        <!-- ORDERS DATA TABLE -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 border-collapse">
                    <thead class="bg-slate-100 text-[11px] text-slate-600 uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 font-bold">Order Details</th>
                            <th class="py-3 px-4 font-bold">Customer / Shipping</th>
                            <th class="py-3 px-4 font-bold">Items & Seller</th>
                            <th class="py-3 px-4 font-bold">Financials & Escrow</th>
                            <th class="py-3 px-4 font-bold">Status</th>
                            <th class="py-3 px-4 font-bold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="ordersTableBody">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 block"></i>
                                    No orders found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): 
                                $orderItems = $itemsByOrder[$o['id']] ?? [];
                                $statusBadgeClass = match($o['status']) {
                                    'paid' => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'shipped' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'delivered' => 'bg-purple-100 text-purple-800 border-purple-200',
                                    'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'refund_requested' => 'bg-rose-100 text-rose-800 border-rose-200',
                                    'refunded' => 'bg-gray-100 text-gray-700 border-gray-300',
                                    'cancelled' => 'bg-red-100 text-red-800 border-red-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200'
                                };
                            ?>
                                <tr class="hover:bg-slate-50/80 transition order-row" 
                                    data-id="<?= (int)$o['id'] ?>"
                                    data-status="<?= htmlspecialchars($o['status']) ?>"
                                    data-code="<?= htmlspecialchars($o['order_code']) ?>"
                                    data-buyer="<?= htmlspecialchars($o['buyer_name'] ?: $o['shipping_name'] ?: '') ?>"
                                    data-email="<?= htmlspecialchars($o['buyer_email'] ?: '') ?>"
                                    data-phone="<?= htmlspecialchars($o['shipping_phone'] ?: '') ?>"
                                    data-address="<?= htmlspecialchars($o['shipping_address'] ?: '') ?>"
                                    data-gross="<?= (int)$o['gross_amount'] ?>"
                                    data-net="<?= (int)$o['net_amount'] ?>"
                                    data-commission="<?= (int)($o['commission_fee'] ?? 0) ?>"
                                    data-transaction="<?= (int)($o['transaction_fee'] ?? 0) ?>"
                                    data-seller="<?= htmlspecialchars($o['seller_name'] ?: 'Seller #' . $o['seller_id']) ?>"
                                    data-checkout-id="<?= htmlspecialchars($o['checkout_session_id'] ?? '') ?>">
                                    
                                    <!-- Order Code & Timestamp -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-mono font-bold text-slate-900 text-xs">
                                            #<?= htmlspecialchars($o['order_code']) ?>
                                        </div>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">
                                            <?= date('M d, Y • h:i A', strtotime($o['created_at'])) ?>
                                        </span>
                                        <?php if (!empty($o['checkout_session_id'])): ?>
                                            <span class="text-[10px] text-slate-400 font-mono block truncate max-w-[140px]" title="PayMongo ID: <?= htmlspecialchars($o['checkout_session_id']) ?>">
                                                <?= htmlspecialchars(substr($o['checkout_session_id'], 0, 14)) ?>...
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Customer / Shipping Details -->
                                    <td class="py-3.5 px-4 max-w-xs">
                                        <div class="font-semibold text-slate-900 truncate">
                                            <?= htmlspecialchars($o['shipping_name'] ?: ($o['buyer_name'] ?: 'Customer')) ?>
                                        </div>
                                        <?php if (!empty($o['shipping_phone'])): ?>
                                            <span class="text-[11px] text-slate-500 block">
                                                <i class="fa-solid fa-phone text-[9px] mr-1 text-slate-400"></i><?= htmlspecialchars($o['shipping_phone']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <p class="text-[11px] text-slate-500 truncate mt-0.5" title="<?= htmlspecialchars($o['shipping_address'] ?: 'N/A') ?>">
                                            <i class="fa-solid fa-location-dot text-[9px] mr-1 text-slate-400"></i><?= htmlspecialchars($o['shipping_address'] ?: 'No address provided') ?>
                                        </p>
                                    </td>

                                    <!-- Items & Seller -->
                                    <td class="py-3.5 px-4 max-w-xs">
                                        <div class="text-[11px] text-slate-500 mb-1">
                                            Seller: <strong class="text-slate-800"><?= htmlspecialchars($o['seller_name'] ?: 'Artisan Seller') ?></strong>
                                        </div>
                                        <div class="space-y-1">
                                            <?php foreach ($orderItems as $item): ?>
                                                <div class="flex items-center justify-between text-[11px] bg-slate-50 px-2 py-1 rounded border border-slate-100">
                                                    <span class="truncate max-w-[150px] font-medium text-slate-700"><?= htmlspecialchars($item['name']) ?></span>
                                                    <span class="text-slate-500 shrink-0 font-mono">x<?= (int)$item['quantity'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>

                                    <!-- Financials & Escrow -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="text-xs font-bold text-slate-900">
                                            Total Paid: ₱<?= number_format(((int)$o['gross_amount']) / 100, 2) ?>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            Platform Fees: <span class="text-rose-600">-₱<?= number_format((((int)$o['gross_amount']) - ((int)$o['net_amount'])) / 100, 2) ?></span>
                                        </div>
                                        <div class="text-[11px] font-bold text-emerald-700 mt-0.5">
                                            Net Escrow: ₱<?= number_format(((int)$o['net_amount']) / 100, 2) ?>
                                        </div>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $statusBadgeClass ?>">
                                            <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $o['status']))) ?>
                                        </span>
                                        <?php if ($o['status'] === 'delivered' && !empty($o['guarantee_expires_at'])): ?>
                                            <span class="text-[10px] text-amber-600 font-semibold block mt-1">
                                                <i class="fa-solid fa-clock mr-0.5"></i> 7-day guarantee
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Action Buttons -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <button onclick="openEditOrderModal(<?= htmlspecialchars(json_encode([
                                            'id' => (int)$o['id'],
                                            'order_code' => $o['order_code'],
                                            'status' => $o['status'],
                                            'shipping_name' => $o['shipping_name'] ?: '',
                                            'shipping_phone' => $o['shipping_phone'] ?: '',
                                            'shipping_address' => $o['shipping_address'] ?: '',
                                            'gross_amount' => (int)$o['gross_amount'],
                                            'net_amount' => (int)$o['net_amount'],
                                            'seller_name' => $o['seller_name'] ?: '',
                                            'buyer_name' => $o['buyer_name'] ?: '',
                                            'items' => $orderItems
                                        ])) ?>)" 
                                        class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 font-bold rounded-xl text-xs border border-red-200 shadow-sm transition flex items-center justify-center gap-1.5 mx-auto">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit / Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ADMIN EDIT ORDER & ESCROW MODAL -->
    <div id="editOrderModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto space-y-5">
            <button onclick="closeEditOrderModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 transition">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <!-- Modal Header -->
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-lg shrink-0">
                    <i class="fa-solid fa-pen-ruler"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-slate-900" id="modalOrderTitle">Edit Order</h3>
                    <p class="text-xs text-slate-500">Update order status, shipping coordinates, or trigger escrow payouts.</p>
                </div>
            </div>

            <!-- Modal Form -->
            <form id="editOrderForm" onsubmit="handleOrderUpdateSubmit(event)" class="space-y-4 text-xs">
                <input type="hidden" id="editOrderId" value="">

                <!-- Status Selector & Escrow Controls -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                    <label class="block font-bold text-slate-800 uppercase tracking-wider text-[11px]">
                        Order Fulfillment & Escrow Lifecycle
                    </label>
                    <select id="editOrderStatus" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white font-semibold text-slate-800 focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none">
                        <option value="pending">pending — Awaiting Payment</option>
                        <option value="paid">paid — Payment Confirmed (Locked in Escrow)</option>
                        <option value="shipped">shipped — In Transit with Courier</option>
                        <option value="delivered">delivered — Delivered (7-Day Guarantee Active)</option>
                        <option value="completed">completed — Completed (Escrow Released to Seller)</option>
                        <option value="refund_requested">refund_requested — Buyer Disputed (Escrow Frozen)</option>
                        <option value="refunded">refunded — Refunded to Buyer (Escrow Cancelled)</option>
                        <option value="cancelled">cancelled — Order Cancelled</option>
                    </select>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" onclick="setQuickStatus('completed')" class="flex-1 py-2 px-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-xl border border-emerald-200 transition text-[11px] text-center">
                            <i class="fa-solid fa-vault mr-1"></i> Quick Release Escrow
                        </button>
                        <button type="button" onclick="setQuickStatus('refunded')" class="py-2 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl border border-rose-200 transition text-[11px] text-center">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Issue Refund
                        </button>
                    </div>
                </div>

                <!-- Shipping Destination Details -->
                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Shipping & Recipient Information</h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Recipient Name</label>
                            <input type="text" id="editShippingName" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-red-600 outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Contact Phone</label>
                            <input type="text" id="editShippingPhone" placeholder="+63 9..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-red-600 outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Complete Delivery Address</label>
                        <textarea id="editShippingAddress" rows="2.5" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-red-600 outline-none"></textarea>
                    </div>
                </div>

                <!-- Financial Ledger Breakdown Box -->
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-1.5 text-[11px]">
                    <div class="flex justify-between text-slate-600">
                        <span>Total Paid (PayMongo):</span>
                        <strong class="text-slate-900" id="modalGrossAmount">₱0.00</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Seller Net Escrow Take-Home:</span>
                        <strong class="text-emerald-700" id="modalNetAmount">₱0.00</strong>
                    </div>
                </div>

                <!-- Line Items Container -->
                <div>
                    <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px] mb-2">Order Items</h4>
                    <div id="modalOrderItems" class="space-y-1.5 max-h-36 overflow-y-auto"></div>
                </div>

                <!-- Status Feedback -->
                <div id="modalStatusFeedback" class="hidden p-3 rounded-xl text-center font-bold"></div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeEditOrderModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" id="saveOrderBtn" class="px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Order Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        let currentStatusFilter = 'all';

        function filterStatus(status) {
            currentStatusFilter = status;
            document.querySelectorAll('.status-tab-btn').forEach(btn => {
                if (btn.dataset.status === status) {
                    btn.className = "status-tab-btn px-3 py-1.5 rounded-xl bg-slate-900 text-white shadow-sm";
                } else {
                    btn.className = "status-tab-btn px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200";
                }
            });
            applyOrderSearch();
        }

        function applyOrderSearch() {
            const query = (document.getElementById('orderSearchInput').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.order-row');

            rows.forEach(row => {
                const status = row.dataset.status;
                const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);
                
                const code = (row.dataset.code || '').toLowerCase();
                const buyer = (row.dataset.buyer || '').toLowerCase();
                const email = (row.dataset.email || '').toLowerCase();
                const phone = (row.dataset.phone || '').toLowerCase();
                const address = (row.dataset.address || '').toLowerCase();

                const matchesQuery = !query || code.includes(query) || buyer.includes(query) || email.includes(query) || phone.includes(query) || address.includes(query);

                if (matchesStatus && matchesQuery) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        function openEditOrderModal(order) {
            document.getElementById('editOrderId').value = order.id;
            document.getElementById('modalOrderTitle').innerText = `Manage Order #${order.order_code}`;
            document.getElementById('editOrderStatus').value = order.status;
            document.getElementById('editShippingName').value = order.shipping_name;
            document.getElementById('editShippingPhone').value = order.shipping_phone;
            document.getElementById('editShippingAddress').value = order.shipping_address;
            document.getElementById('modalGrossAmount').innerText = `₱${(order.gross_amount / 100).toFixed(2)}`;
            document.getElementById('modalNetAmount').innerText = `₱${(order.net_amount / 100).toFixed(2)}`;

            const itemsBox = document.getElementById('modalOrderItems');
            if (order.items && order.items.length) {
                itemsBox.innerHTML = order.items.map(it => `
                    <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                        <span class="font-semibold text-slate-800">${it.name}</span>
                        <div class="text-right">
                            <span class="text-slate-500 font-mono">x${it.quantity}</span>
                            <span class="font-bold text-slate-900 ml-2">₱${((it.price_centavos * it.quantity) / 100).toFixed(2)}</span>
                        </div>
                    </div>
                `).join('');
            } else {
                itemsBox.innerHTML = `<p class="text-xs text-slate-400 italic">No line items recorded.</p>`;
            }

            document.getElementById('modalStatusFeedback').classList.add('hidden');
            document.getElementById('editOrderModal').classList.remove('hidden');
        }

        function setQuickStatus(status) {
            document.getElementById('editOrderStatus').value = status;
        }

        function closeEditOrderModal() {
            document.getElementById('editOrderModal').classList.add('hidden');
        }

        async function handleOrderUpdateSubmit(e) {
            e.preventDefault();
            const orderId = document.getElementById('editOrderId').value;
            const status = document.getElementById('editOrderStatus').value;
            const shippingName = document.getElementById('editShippingName').value;
            const shippingPhone = document.getElementById('editShippingPhone').value;
            const shippingAddress = document.getElementById('editShippingAddress').value;

            const feedback = document.getElementById('modalStatusFeedback');
            const saveBtn = document.getElementById('saveOrderBtn');

            feedback.className = "p-3 rounded-xl text-center font-bold text-xs bg-blue-50 text-blue-700 border border-blue-200";
            feedback.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Updating order & escrow status...`;
            feedback.classList.remove('hidden');
            saveBtn.disabled = true;

            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('status', status);
            fd.append('shipping_name', shippingName);
            fd.append('shipping_phone', shippingPhone);
            fd.append('shipping_address', shippingAddress);

            try {
                const res = await fetch('../api.php?action=admin_update_order', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    feedback.className = "p-3 rounded-xl text-center font-bold text-xs bg-emerald-50 text-emerald-700 border border-emerald-200";
                    feedback.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    feedback.className = "p-3 rounded-xl text-center font-bold text-xs bg-rose-50 text-rose-700 border border-rose-200";
                    feedback.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error || 'Failed to update order'}`;
                    saveBtn.disabled = false;
                }
            } catch (err) {
                feedback.className = "p-3 rounded-xl text-center font-bold text-xs bg-rose-50 text-rose-700 border border-rose-200";
                feedback.innerHTML = `Network error: ${err.message}`;
                saveBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
