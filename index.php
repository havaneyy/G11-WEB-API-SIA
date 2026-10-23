<?php
require_once __DIR__ . '/config.php';
$db = get_db();

$initialUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id, username, email, display_name, avatar_url, bio, phone, location, role, opt_in_marketing, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $initialUser = $stmt->fetch();
}
$isAdmin = ($initialUser && $initialUser['role'] === 'admin');

$initialProducts = [];
$pStmt = $db->query("SELECT p.*, COALESCE(u.display_name, u.username, 'Lin_Aesthetic') as author, COALESCE(u.avatar_url, '" . DEFAULT_AVATAR_URL . "') as authorAvatar FROM products p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC");
while ($r = $pStmt->fetch()) {
    $initialProducts[] = [
        'id' => (int)$r['id'],
        'title' => $r['name'],
        'category' => $r['category'] ?: 'Fashion',
        'price' => ((int)$r['price_centavos']) / 100,
        'price_centavos' => (int)$r['price_centavos'],
        'imageUrl' => $r['image_url'],
        'author' => $r['author'],
        'authorAvatar' => $r['authorAvatar'],
        'likes' => (int)($r['likes_count'] ?? 0),
        'isLiked' => false,
        'desc' => $r['description'],
        'user_id' => (int)$r['user_id']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeCraft — Curated Artisan E-Commerce & Escrow Guarantee</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        redAccent: '#FF2442',
                        redHover: '#e01e38',
                        redLight: '#fff0f2',
                        darkText: '#111827',
                        mutedText: '#6B7280',
                        bgColor: '#f9fafb'
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #FF2442; }
        
        .masonry-grid { column-count: 2; column-gap: 1rem; }
        @media (min-width: 640px) { .masonry-grid { column-count: 3; } }
        @media (min-width: 1024px) { .masonry-grid { column-count: 4; } }
        @media (min-width: 1280px) { .masonry-grid { column-count: 5; } }
        .masonry-item { break-inside: avoid; margin-bottom: 1rem; }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-bgColor text-darkText font-sans antialiased pb-20 md:pb-6">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
            
            <!-- Brand Logo: CodeCraft -->
            <div class="flex items-center gap-2 cursor-pointer" onclick="switchTab('feed')">
                <div class="bg-redAccent text-white font-extrabold text-xl px-3 py-1 rounded-xl tracking-tight shadow-sm flex items-center gap-1.5 hover:opacity-95 transition">
                    <i class="fa-solid fa-shapes text-sm"></i>
                    <span>CodeCraft</span>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="flex-1 max-w-xl relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchInput" oninput="handleSearch()" placeholder="Search artisan crafts, handmade pottery, fashion & decor..." 
                       class="w-full bg-gray-100 text-sm pl-10 pr-4 py-2 rounded-full border border-transparent focus:border-redAccent focus:bg-white focus:outline-none transition shadow-inner">
            </div>

            <!-- Desktop Action Buttons -->
            <div class="hidden md:flex items-center gap-4 text-sm font-medium text-gray-700">
                <button onclick="switchTab('feed')" id="nav-btn-feed" class="hover:text-redAccent transition flex items-center gap-1.5 text-redAccent font-semibold">
                    <i class="fa-solid fa-compass text-base"></i> Explore
                </button>

                <!-- Add Craft -->
                <button onclick="switchTab('create')" id="nav-btn-create" class="hover:text-redAccent transition flex items-center gap-1.5 text-gray-700 hover:text-redAccent">
                    <i class="fa-solid fa-circle-plus text-lg text-redAccent"></i> Add Craft
                </button>

                <!-- Admin-Only View for XAMPP DB Explorer & Order Monitor (Hidden for normal users) -->
                <div id="adminDbNavItem" class="<?= $isAdmin ? '' : 'hidden' ?> flex items-center gap-2">
                    <button onclick="switchTab('database')" id="nav-btn-database" class="transition flex items-center gap-1.5 text-emerald-700 font-bold bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-full border border-emerald-300 text-xs shadow-sm">
                        <i class="fa-solid fa-database text-xs"></i> XAMPP DB
                        <span class="bg-emerald-600 text-white text-[9px] px-1 py-0.2 rounded font-extrabold">ADMIN CRUD</span>
                    </button>
                    <a href="admin/orders.php" target="_blank" class="transition flex items-center gap-1.5 text-blue-700 font-bold bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full border border-blue-300 text-xs shadow-sm" title="Order Monitoring & Escrow Management">
                        <i class="fa-solid fa-clipboard-check text-xs"></i> Order Monitor
                    </a>
                </div>

                <!-- Shopping Bag & Checkout Button -->
                <button onclick="openCartDrawer()" class="relative hover:text-redAccent transition p-2 rounded-full hover:bg-gray-100" title="View Shopping Bag">
                    <i class="fa-solid fa-bag-shopping text-xl"></i>
                    <span id="cartBadge" class="absolute -top-0.5 -right-0.5 bg-redAccent text-white text-[11px] w-5 h-5 rounded-full flex items-center justify-center font-bold shadow hidden">0</span>
                </button>

                <button data-cy="checkout-button" onclick="proceedToCheckoutDirect()" class="bg-redAccent hover:bg-redHover text-white px-3.5 py-1.5 rounded-full text-xs font-bold shadow-sm transition flex items-center gap-1.5" title="Proceed to Checkout">
                    <i class="fa-solid fa-cart-shopping text-xs"></i>
                    <span>Checkout</span>
                </button>

                <!-- Notifications Bell & Dropdown for All Users -->
                <div id="notifNavContainer" class="relative">
                    <button onclick="toggleNotifDropdown()" id="notifBellBtn" class="relative hover:text-redAccent transition p-2 rounded-full hover:bg-gray-100 text-gray-700" title="Notifications & Alerts">
                        <i class="fa-solid fa-bell text-xl"></i>
                        <span id="notifBadge" class="absolute -top-0.5 -right-0.5 bg-redAccent text-white text-[10px] min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center font-extrabold shadow hidden">0</span>
                    </button>

                    <!-- Notification Dropdown Popover -->
                    <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 overflow-hidden transform transition-all duration-200">
                        <div class="p-3.5 bg-gradient-to-r from-gray-50 to-redLight/30 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-bell text-redAccent text-sm"></i>
                                <h4 class="font-bold text-xs text-gray-900">Notifications</h4>
                                <span id="notifDropdownCount" class="text-[10px] bg-redAccent text-white px-2 py-0.5 rounded-full font-extrabold">0 New</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="handleMarkAllNotifsRead()" class="text-[11px] text-gray-500 hover:text-redAccent font-semibold transition" title="Mark all read">
                                    <i class="fa-solid fa-check-double text-[10px] mr-1"></i>Mark Read
                                </button>
                                <button onclick="switchTab('account'); switchAccountSubTab('notifications'); toggleNotifDropdown(false);" class="text-[11px] text-redAccent hover:underline font-bold" title="Open full center">
                                    View All
                                </button>
                            </div>
                        </div>
                        <div id="notifDropdownList" class="max-h-80 overflow-y-auto divide-y divide-gray-100 text-xs">
                            <div class="p-6 text-center text-gray-400 text-xs">
                                <i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading notifications...
                            </div>
                        </div>
                        <div class="p-2.5 bg-gray-50 border-t border-gray-100 text-center">
                            <button onclick="switchTab('account'); switchAccountSubTab('notifications'); toggleNotifDropdown(false);" class="text-xs font-bold text-gray-700 hover:text-redAccent transition w-full py-1 flex items-center justify-center gap-1.5">
                                <span>Go to Notification Center</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Auth / Direct Profile Button (No popup menu on click) -->
                <div id="authNavContainer">
                    <?php if ($initialUser): ?>
                        <button onclick="switchTab('account'); switchAccountSubTab('overview')" class="flex items-center gap-2 border border-gray-200 p-1 pr-3 rounded-full hover:border-redAccent hover:bg-redLight/30 transition bg-white shadow-sm cursor-pointer" title="Go to Account Overview">
                            <img src="<?= htmlspecialchars($initialUser['avatar_url'] ?: DEFAULT_AVATAR_URL) ?>" 
                                 class="w-7 h-7 rounded-full object-cover border border-gray-200" alt="Avatar">
                            <span class="text-xs font-semibold truncate max-w-[100px]"><?= htmlspecialchars($initialUser['display_name']) ?></span>
                        </button>
                    <?php else: ?>
                        <button onclick="openAuthModal('login')" class="px-4 py-2 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow-sm transition flex items-center gap-1.5">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In / Register
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- CHECKOUT ERROR ALERT BANNER -->
    <div class="max-w-7xl mx-auto px-4 pt-3">
        <div id="checkoutErrorBanner" data-cy="checkout-error" class="hidden p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base text-red-500"></i>
                <span id="checkoutErrorText">Cart is empty or checkout failed.</span>
            </div>
            <button onclick="dismissCheckoutError()" class="text-red-400 hover:text-red-700 font-black text-sm px-2">&times;</button>
        </div>
    </div>

    <!-- MAIN CONTAINER WITH TABS -->
    <main class="max-w-7xl mx-auto px-4 pt-2">

        <!-- TAB 1: WATERFALL / MASONRY FEED (HOME) -->
        <section id="tab-feed" class="tab-content">
            <!-- CodeCraft Guarantee Trust Banner -->
            <div class="mb-4 bg-gradient-to-r from-red-500 via-rose-500 to-red-600 text-white p-3.5 rounded-2xl shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-lg font-bold">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold flex items-center gap-1.5">
                            CodeCraft Guarantee Protected
                            <span class="text-[10px] bg-white text-redAccent px-2 py-0.5 rounded-full font-extrabold uppercase">Escrow Release</span>
                        </h3>
                        <p class="text-xs text-white/90">Buyer funds are held safely in escrow. Sellers receive payments after delivery confirmation or 7-day guarantee window.</p>
                    </div>
                </div>
                <!-- Banner Action: Hidden if not signed in, only shown to logged in users -->
                <div id="bannerWalletAction">
                    <?php if ($initialUser): ?>
                        <button onclick="switchTab('account'); switchAccountSubTab('wallet')" class="text-xs font-bold bg-white text-redAccent hover:bg-gray-100 px-4 py-2 rounded-full shadow transition whitespace-nowrap">
                            <i class="fa-solid fa-wallet mr-1"></i> View Seller Wallet & Escrow
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Chips & Sort Controls -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-2 border-b border-gray-200">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full no-scrollbar" id="categoryContainer">
                    <!-- Populated dynamically -->
                </div>

                <!-- Sort Control -->
                <div class="flex items-center gap-2 text-xs text-gray-600 bg-white px-3 py-1.5 rounded-full border border-gray-200 shadow-sm">
                    <i class="fa-solid fa-arrow-down-wide-short text-redAccent"></i>
                    <span>Sort:</span>
                    <select id="sortSelect" onchange="renderFeed()" class="bg-transparent font-semibold text-gray-800 outline-none cursor-pointer">
                        <option value="newest">Newest Crafts</option>
                        <option value="likes">Most Liked</option>
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Search Indicator -->
            <div id="filterInfo" class="hidden mb-4 flex items-center gap-2 text-xs text-gray-500 bg-redLight p-2.5 rounded-xl border border-red-100">
                <span>Filtering by: <strong id="filterTerm" class="text-redAccent font-bold"></strong></span>
                <button onclick="resetFilters()" class="text-xs text-redAccent hover:text-redHover underline font-semibold ml-2">Clear search</button>
            </div>

            <!-- Masonry Feed Container -->
            <div class="masonry-grid" id="masonryFeed">
                <!-- Posts inserted via JS from MySQL -->
            </div>
        </section>

        <!-- TAB 2: ADD CRAFT -->
        <section id="tab-create" class="tab-content hidden max-w-3xl mx-auto py-6">
            <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-2xl bg-redLight text-redAccent flex items-center justify-center font-bold text-2xl shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Add a New Craft to CodeCraft</h2>
                        <p class="text-xs text-gray-500">Upload photos directly from device or URL. Transparent Shopee-style fee deductions calculated live.</p>
                    </div>
                </div>

                <form id="createPostForm" onsubmit="handleCreatePost(event)" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Craft Title</label>
                        <input type="text" id="postTitle" required placeholder="e.g. Handmade Ceramic Matcha Bowl Set..." 
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:border-redAccent focus:ring-1 focus:ring-redAccent outline-none transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Category</label>
                            <select id="postCategory" onchange="updateLiveFeeEstimator()" required class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:border-redAccent outline-none transition bg-white font-medium">
                                <option value="Fashion">Fashion (6% commission)</option>
                                <option value="Beauty">Beauty (6% commission)</option>
                                <option value="Home & Living">Home & Living (6% commission)</option>
                                <option value="Tech">Tech (4.5% commission)</option>
                                <option value="Food & Cafe">Food & Cafe (5% commission)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Listing Price (₱ PHP)</label>
                            <input type="number" step="0.01" min="1" id="postPrice" oninput="updateLiveFeeEstimator()" required placeholder="1250.00" 
                                   class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:border-redAccent focus:ring-1 focus:ring-redAccent outline-none transition font-bold">
                        </div>
                    </div>

                    <!-- IMAGE UPLOAD: DEVICE UPLOAD + URL OPTIONS -->
                    <div class="border border-gray-200 rounded-2xl p-4 bg-gray-50/70">
                        <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider mb-2">Craft Photo (Upload from Device or URL)</label>
                        
                        <div class="flex items-center gap-2 mb-3 text-xs font-bold">
                            <button type="button" onclick="switchUploadMode('device')" id="btn-mode-device" class="px-3.5 py-1.5 rounded-lg bg-redAccent text-white shadow-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload from Device
                            </button>
                            <button type="button" onclick="switchUploadMode('url')" id="btn-mode-url" class="px-3.5 py-1.5 rounded-lg bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 flex items-center gap-1.5">
                                <i class="fa-solid fa-link"></i> Paste Image URL
                            </button>
                        </div>

                        <!-- Device File Input -->
                        <div id="deviceUploadSection" class="space-y-3">
                            <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl p-5 hover:border-redAccent bg-white cursor-pointer transition group">
                                <i class="fa-solid fa-image text-3xl text-gray-400 group-hover:text-redAccent mb-2 transition"></i>
                                <span class="text-xs font-bold text-gray-700">Click to choose image from your computer / phone</span>
                                <span class="text-[11px] text-gray-400 mt-0.5">Supports JPG, PNG, WEBP, GIF (Max 10MB)</span>
                                <input type="file" id="deviceFileInput" accept="image/*" onchange="handleDeviceFileUpload(event)" class="hidden">
                            </label>
                            <div id="uploadStatusText" class="text-xs text-center font-semibold text-gray-500 hidden"></div>
                        </div>

                        <!-- Image URL Input -->
                        <div id="urlUploadSection" class="hidden space-y-2">
                            <input type="url" id="postImageUrl" placeholder="https://images.unsplash.com/photo-..." 
                                   class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:border-redAccent bg-white outline-none">
                            <div class="flex items-center gap-1.5 text-xs text-gray-500 overflow-x-auto pb-1">
                                <span class="text-[11px] font-semibold text-gray-400">Presets:</span>
                                <button type="button" onclick="setPostImagePreset('https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600')" class="px-2 py-0.5 bg-white border rounded text-[11px] hover:border-redAccent">Teapot</button>
                                <button type="button" onclick="setPostImagePreset('https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=600')" class="px-2 py-0.5 bg-white border rounded text-[11px] hover:border-redAccent">Linen</button>
                                <button type="button" onclick="setPostImagePreset('https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600')" class="px-2 py-0.5 bg-white border rounded text-[11px] hover:border-redAccent">Keyboard</button>
                                <button type="button" onclick="setPostImagePreset('https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600')" class="px-2 py-0.5 bg-white border rounded text-[11px] hover:border-redAccent">Serum</button>
                            </div>
                        </div>

                        <!-- Preview Box -->
                        <div id="imagePreviewBox" class="mt-3 flex items-center gap-3 p-2 bg-white rounded-xl border border-gray-200 hidden">
                            <img id="previewImgElement" src="" class="w-16 h-16 rounded-lg object-cover border" alt="Preview">
                            <div class="text-xs">
                                <p class="font-bold text-gray-800">Photo Attached</p>
                                <p id="previewImgUrlText" class="text-gray-400 text-[11px] truncate max-w-xs font-mono"></p>
                            </div>
                        </div>
                    </div>

                    <!-- DESCRIPTION -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Craft Story, Details & Materials</label>
                        <textarea id="postDescription" rows="3" required placeholder="Describe hand-crafting technique, sizing, care instructions... #artisan #ceramic" 
                                  class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:border-redAccent focus:ring-1 focus:ring-redAccent outline-none transition"></textarea>
                    </div>

                    <!-- SHOPEE-STYLE SELLER DEDUCTIONS BREAKDOWN BOX -->
                    <div class="border border-red-200 bg-redLight/40 rounded-2xl p-4 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-calculator text-redAccent"></i>
                                <h4 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider">📊 Breakdown of Seller Deductions (15% to 20%)</h4>
                            </div>
                            <span id="estRateBadge" class="text-xs font-bold px-2 py-0.5 rounded-full bg-redAccent text-white">~17% Total Fees</span>
                        </div>

                        <!-- SHIPPING FEE RESPONSIBILITY ASSISTANT & SUBSIDIES INTERFACE -->
                        <div class="bg-white p-3.5 rounded-xl border border-red-200/80 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-redLight text-redAccent flex items-center justify-center text-xs font-bold">
                                        <i class="fa-solid fa-truck-fast"></i>
                                    </span>
                                    <span class="text-xs font-extrabold text-gray-900 uppercase tracking-wider">Shipping Fee Responsibility Assistant</span>
                                </div>
                                <button type="button" onclick="toggleShippingAssistantGuide()" class="text-[11px] text-redAccent hover:text-redHover font-bold flex items-center gap-1 cursor-pointer">
                                    <i class="fa-solid fa-circle-question"></i> Policy Rules
                                </button>
                            </div>

                            <p class="text-[11px] text-gray-500 leading-relaxed">
                                Select who pays courier delivery costs. Toggling checkboxes below instantly updates your <strong>Estimated Total Platform Deductions</strong> and <strong>Your Net Escrow Take-Home</strong>:
                            </p>

                            <!-- Checkboxes Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 text-xs">
                                <label class="flex items-start gap-2 p-2 rounded-lg border border-gray-200 hover:border-redAccent/60 bg-gray-50/50 cursor-pointer transition">
                                    <input type="checkbox" id="optSellerShipping" checked onchange="updateLiveFeeEstimator()" class="mt-0.5 rounded text-redAccent focus:ring-redAccent">
                                    <div>
                                        <span class="font-bold text-gray-800 text-[11px] block">Sponsor Free Shipping to Buyer</span>
                                        <span class="text-[10px] text-gray-500 block leading-tight">Seller subsidizes forward shipping (-5.6%, max ₱100.00). Uncheck if buyer pays delivery!</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2 p-2 rounded-lg border border-gray-200 hover:border-redAccent/60 bg-gray-50/50 cursor-pointer transition">
                                    <input type="checkbox" id="optInFSS" onchange="updateLiveFeeEstimator()" class="mt-0.5 rounded text-redAccent focus:ring-redAccent">
                                    <div>
                                        <span class="font-bold text-gray-800 text-[11px] block">Free Shipping Special (FSS) Program</span>
                                        <span class="text-[10px] text-gray-500 block leading-tight">Opt in for Free Shipping algorithm badge (-3.00% fee). Highly boosts craft discovery!</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Responsibility Decision Tree Guide (Expandable) -->
                            <div id="shippingAssistantGuide" class="hidden mt-2 p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-[11px] space-y-1.5 text-gray-700">
                                <div class="font-bold text-amber-900 flex items-center gap-1.5">
                                    <i class="fa-solid fa-scale-balanced"></i> CodeCraft Responsibility Logic (Who pays what?):
                                </div>
                                <ul class="space-y-1 text-[10px] pl-3 list-disc text-gray-600">
                                    <li><strong class="text-rose-700">Seller Fault</strong> (defective craft, wrong item, not as described, late dispatch): Seller pays forward + return shipping.</li>
                                    <li><strong class="text-blue-700">Buyer Fault / Change of Mind</strong>: Buyer pays return shipping. Forward shipping is non-refundable.</li>
                                    <li><strong class="text-emerald-700">Carrier / Courier Transit Fault</strong> (lost or damaged parcel in transit): CodeCraft Platform Escrow covers 100% of replacement costs.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Fee Tiles Breakdown -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                            <div class="bg-white p-2 rounded-lg border border-gray-200">
                                <span class="text-[10px] text-gray-500 block">Commission Fee (<span id="feeRateComm">6%</span>)</span>
                                <span id="feeValComm" class="font-bold text-gray-800">₱0.00</span>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-gray-200">
                                <span class="text-[10px] text-gray-500 block">Transaction Fee (2.24%)</span>
                                <span id="feeValTrans" class="font-bold text-gray-800">₱0.00</span>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-gray-200" id="boxFeeShip">
                                <span class="text-[10px] text-gray-500 block" id="feeLabelShip">Platform Shipping (5.6%)</span>
                                <span id="feeValShip" class="font-bold text-gray-800">₱0.00</span>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-gray-200">
                                <span class="text-[10px] text-gray-500 block">Growth Support Fee (1.5%)</span>
                                <span id="feeValGrowth" class="font-bold text-gray-800">₱0.00</span>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-gray-200">
                                <span class="text-[10px] text-gray-500 block">Order Processing Fee</span>
                                <span class="font-bold text-gray-800">₱5.00 flat</span>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-gray-200">
                                <label class="flex items-center gap-1 text-[10px] font-bold text-gray-600 cursor-pointer">
                                    <input type="checkbox" id="optInPrograms" onchange="updateLiveFeeEstimator()" class="rounded text-redAccent focus:ring-redAccent">
                                    <span>Marketing Program (3%)</span>
                                </label>
                                <span id="feeValProgram" class="font-bold text-gray-800">₱0.00</span>
                            </div>
                        </div>

                        <!-- Total Deductions & Net Take-Home -->
                        <div class="flex items-center justify-between pt-2 border-t border-red-200 text-xs">
                            <span class="text-gray-600">Estimated Total Platform Deductions: <strong id="feeValTotal" class="text-gray-900 font-bold">₱0.00</strong></span>
                            <div class="text-right">
                                <span class="text-[10px] text-gray-500 uppercase block font-bold">Your Net Escrow Take-Home</span>
                                <span id="feeValNet" class="text-base font-extrabold text-emerald-600">₱0.00</span>
                            </div>
                        </div>
                    </div>

                    <div id="createPostStatus" class="hidden p-3 rounded-xl text-xs font-semibold text-center"></div>

                    <div class="pt-3 flex items-center justify-end gap-3 border-t border-gray-100">
                        <button type="button" onclick="switchTab('feed')" class="px-5 py-2.5 text-xs font-semibold text-gray-600 hover:text-gray-800 transition">Cancel</button>
                        <button type="submit" id="submitPostBtn" class="px-7 py-3 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow-md transition flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Publish Craft
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- TAB 3: COMPLETE "MY ACCOUNT" ONLINE SHOP INTERFACE -->
        <section id="tab-account" class="tab-content hidden max-w-5xl mx-auto py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                
                <!-- Account Sidebar Navigation -->
                <div class="md:col-span-1 space-y-4">
                    <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm text-center">
                        <div class="relative w-20 h-20 mx-auto mb-2">
                            <img id="accSidebarAvatar" src="<?= DEFAULT_AVATAR_URL ?>" class="w-20 h-20 rounded-full mx-auto object-cover border-2 border-redAccent shadow" alt="Avatar">
                            <button onclick="openAvatarModal()" type="button" class="absolute bottom-0 right-0 w-7 h-7 bg-redAccent hover:bg-redHover text-white rounded-full flex items-center justify-center text-xs shadow-md border-2 border-white transition cursor-pointer" title="Update Profile Picture">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                        <h3 id="accSidebarName" class="font-bold text-sm text-gray-900">User Profile</h3>
                        <p id="accSidebarUsername" class="text-xs text-gray-400">@username</p>
                        <button onclick="openAvatarModal()" type="button" class="mt-2 text-[11px] font-bold text-redAccent hover:text-redHover hover:underline flex items-center justify-center gap-1 mx-auto cursor-pointer">
                            <i class="fa-solid fa-pen"></i> Update Profile Picture
                        </button>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden p-2 space-y-1 text-xs font-semibold">
                        <button onclick="switchAccountSubTab('overview')" id="acc-tab-overview" class="w-full text-left px-3.5 py-2.5 rounded-xl bg-redLight text-redAccent font-bold flex items-center gap-2.5">
                            <i class="fa-solid fa-user text-sm"></i> Account Overview
                        </button>
                        <button onclick="switchAccountSubTab('notifications')" id="acc-tab-notifications" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center justify-between">
                            <span class="flex items-center gap-2.5"><i class="fa-solid fa-bell text-sm text-amber-500"></i> Notifications</span>
                            <span id="badgeNotifCount" class="bg-redAccent text-white px-2 py-0.5 rounded-full text-[10px] font-bold hidden">0</span>
                        </button>
                        <button onclick="switchAccountSubTab('orders')" id="acc-tab-orders" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center justify-between">
                            <span class="flex items-center gap-2.5"><i class="fa-solid fa-box text-sm text-gray-400"></i> My Orders</span>
                            <span id="badgeOrderCount" class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]">0</span>
                        </button>
                        <button onclick="switchAccountSubTab('wallet')" id="acc-tab-wallet" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center gap-2.5">
                            <i class="fa-solid fa-wallet text-sm text-emerald-600"></i> Seller Escrow & Wallet
                        </button>
                        <button onclick="switchAccountSubTab('my-crafts')" id="acc-tab-my-crafts" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center gap-2.5">
                            <i class="fa-solid fa-shapes text-sm text-gray-400"></i> My Listed Crafts
                        </button>
                        <button onclick="switchAccountSubTab('settings')" id="acc-tab-settings" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center gap-2.5">
                            <i class="fa-solid fa-gear text-sm text-gray-400"></i> Edit Profile Info
                        </button>
                        <button onclick="switchAccountSubTab('security')" id="acc-tab-security" class="w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center gap-2.5">
                            <i class="fa-solid fa-lock text-sm text-gray-400"></i> Password & Security
                        </button>
                        <div class="border-t border-gray-100 pt-1 my-1"></div>
                        <button onclick="handleLogout()" class="w-full text-left px-3.5 py-2.5 rounded-xl text-red-600 hover:bg-red-50 flex items-center gap-2.5">
                            <i class="fa-solid fa-right-from-bracket text-sm"></i> Sign Out
                        </button>
                    </div>
                </div>

                <!-- Account Main Content Panel -->
                <div class="md:col-span-3">

                    <!-- SUB-TAB 1: ACCOUNT OVERVIEW -->
                    <div id="acc-view-overview" class="acc-view-section space-y-5">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                                <div>
                                    <h2 class="text-base font-bold text-gray-900">Personal Information</h2>
                                    <p class="text-xs text-gray-500">Manage your profile details, contact info, and store presence.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openAvatarModal()" class="px-3.5 py-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full transition flex items-center gap-1.5">
                                        <i class="fa-solid fa-camera"></i> Change Photo
                                    </button>
                                    <button onclick="switchAccountSubTab('settings')" class="px-3.5 py-1.5 text-xs font-bold text-redAccent bg-redLight hover:bg-red-100 rounded-full transition">
                                        Edit Info
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 text-xs">
                                <div>
                                    <span class="text-gray-400 block font-semibold">Full / Display Name</span>
                                    <span id="ovDisplayName" class="text-sm font-bold text-gray-800">-</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block font-semibold">Username</span>
                                    <span id="ovUsername" class="text-sm font-bold text-gray-800 font-mono">-</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block font-semibold">Email Address</span>
                                    <span id="ovEmail" class="text-sm font-bold text-gray-800">-</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block font-semibold">Phone Number</span>
                                    <span id="ovPhone" class="text-sm font-bold text-gray-800">-</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block font-semibold">Primary Location</span>
                                    <span id="ovLocation" class="text-sm font-bold text-gray-800">Manila, Philippines</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block font-semibold">Account Role</span>
                                    <span id="ovRole" class="text-xs font-extrabold uppercase text-redAccent">Verified Creator</span>
                                </div>
                                <div class="sm:col-span-2">
                                    <span class="text-gray-400 block font-semibold">Bio / Shop Description</span>
                                    <p id="ovBio" class="text-xs text-gray-600 mt-1 leading-relaxed">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stat Metrics -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">My Crafts</span>
                                <div class="text-xl font-black text-gray-900 mt-1" id="ovStatCrafts">0</div>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Orders</span>
                                <div class="text-xl font-black text-gray-900 mt-1" id="ovStatOrders">0</div>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
                                <span class="text-[10px] font-bold text-emerald-600 uppercase">Available Wallet</span>
                                <div class="text-xl font-black text-emerald-600 mt-1" id="ovStatWallet">₱0.00</div>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
                                <span class="text-[10px] font-bold text-amber-500 uppercase">Escrow Pending</span>
                                <div class="text-xl font-black text-amber-600 mt-1" id="ovStatEscrow">₱0.00</div>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB: NOTIFICATIONS CENTER -->
                    <div id="acc-view-notifications" class="acc-view-section hidden space-y-4">
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-base font-bold text-gray-900">Notifications & Alerts</h2>
                                        <span id="accNotifBadgePill" class="text-[10px] bg-redLight text-redAccent px-2.5 py-0.5 rounded-full font-extrabold uppercase">0 Unread</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">Stay updated on purchases, escrow releases, delivery confirmations, and platform updates.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="handleMarkAllNotifsRead()" class="px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-full transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                                        <i class="fa-solid fa-check-double text-xs text-gray-500"></i> Mark All as Read
                                    </button>
                                    <button onclick="loadNotifications()" class="px-3 py-1.5 text-xs font-semibold text-gray-500 hover:text-redAccent transition flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-rotate"></i> Refresh
                                    </button>
                                </div>
                            </div>

                            <!-- Filter tabs for notifications -->
                            <div class="flex items-center gap-2 py-3 border-b border-gray-100 overflow-x-auto text-xs font-medium text-gray-500">
                                <button onclick="filterNotifs('all')" id="notif-flt-all" class="px-3 py-1 rounded-full bg-redLight text-redAccent font-bold transition shadow-sm cursor-pointer">All Alerts</button>
                                <button onclick="filterNotifs('unread')" id="notif-flt-unread" class="px-3 py-1 rounded-full hover:bg-gray-100 transition cursor-pointer">Unread</button>
                                <button onclick="filterNotifs('order')" id="notif-flt-order" class="px-3 py-1 rounded-full hover:bg-gray-100 transition cursor-pointer">Orders & Shipping</button>
                                <button onclick="filterNotifs('escrow')" id="notif-flt-escrow" class="px-3 py-1 rounded-full hover:bg-gray-100 transition cursor-pointer">Escrow & Payouts</button>
                            </div>

                            <!-- Notifications List -->
                            <div id="accNotifListContainer" class="divide-y divide-gray-100 mt-2">
                                <div class="py-8 text-center text-gray-400 text-xs">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading notifications...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB 2: MY PURCHASES & ORDER TRACKING -->
                    <div id="acc-view-orders" class="acc-view-section hidden space-y-4">
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <h2 class="text-base font-bold text-gray-900">Purchases & Order Tracking</h2>
                                    <p class="text-xs text-gray-500">Track parcels under CodeCraft Guarantee. Tap "Order Received" to release escrow to seller.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openShippingAssistantModal()" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-full border border-blue-200 transition flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid fa-scale-balanced"></i> Shipping Fee Assistant
                                    </button>
                                    <button onclick="loadUserOrders()" class="text-xs text-gray-500 hover:text-redAccent">
                                        <i class="fa-solid fa-rotate"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div id="accOrdersContainer" class="divide-y divide-gray-100 mt-3">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB 3: SELLER ESCROW & WALLET -->
                    <div id="acc-view-wallet" class="acc-view-section hidden space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Available Balance -->
                            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 rounded-2xl shadow-sm relative overflow-hidden">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-xs uppercase font-bold text-emerald-100 tracking-wider">Available Balance (Ready to Withdraw)</span>
                                        <div class="text-3xl font-extrabold mt-1" id="walletAvailableBalance">₱0.00</div>
                                    </div>
                                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-lg">
                                        <i class="fa-solid fa-building-columns"></i>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-emerald-500/40 flex items-center justify-between">
                                    <span class="text-xs text-emerald-100">First 2 transfers/week free</span>
                                    <button onclick="openWithdrawModal()" class="px-4 py-1.5 bg-white text-emerald-700 text-xs font-bold rounded-full shadow hover:bg-gray-100 transition">
                                        Withdraw Funds
                                    </button>
                                </div>
                            </div>

                            <!-- Escrow Pending Balance -->
                            <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-5 rounded-2xl shadow-sm relative overflow-hidden">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-xs uppercase font-bold text-amber-100 tracking-wider">Escrow Held ("CodeCraft Guarantee")</span>
                                        <div class="text-3xl font-extrabold mt-1" id="walletPendingBalance">₱0.00</div>
                                    </div>
                                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-lg">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-amber-400/40 flex items-center justify-between text-xs text-amber-100">
                                    <span>Releases upon delivery confirmation or 7 days</span>
                                    <span class="font-bold">Protected</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Accounts Management -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">Linked Bank Accounts & Wallets</h3>
                                    <p class="text-xs text-gray-500">Withdrawals require a verified linked account. Partner banks enjoy instant 0-fee transfers.</p>
                                </div>
                                <button onclick="openAddBankModal()" class="px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-xs font-bold text-gray-800 rounded-full transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-xs"></i> Link Bank Account
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3" id="bankAccountsList"></div>
                        </div>

                        <!-- Escrow Transaction Ledger -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="text-sm font-bold text-gray-900 mb-1">Escrow Ledger Audit Trail (Append-Only)</h3>
                            <p class="text-xs text-gray-500 mb-3">Immutable record of every capture, release, withdrawal, and fee deduction.</p>
                            <div class="overflow-x-auto max-h-60 border border-gray-100 rounded-xl">
                                <table class="w-full text-left text-xs text-gray-700">
                                    <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-500 border-b">
                                        <tr>
                                            <th class="p-2.5">Date</th>
                                            <th class="p-2.5">Type</th>
                                            <th class="p-2.5">Description</th>
                                            <th class="p-2.5 text-right">Amount</th>
                                            <th class="p-2.5 text-right">Balance After</th>
                                        </tr>
                                    </thead>
                                    <tbody id="escrowLedgerBody" class="divide-y divide-gray-100"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB 4: MY LISTED CRAFTS -->
                    <div id="acc-view-my-crafts" class="acc-view-section hidden space-y-4">
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <h2 class="text-base font-bold text-gray-900">My Listed Crafts</h2>
                                    <p class="text-xs text-gray-500">Items currently active on the CodeCraft storefront.</p>
                                </div>
                                <button onclick="switchTab('create')" class="px-3.5 py-1.5 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-xs"></i> Add New Craft
                                </button>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4" id="accMyCraftsGrid"></div>
                        </div>
                    </div>

                    <!-- SUB-TAB 5: EDIT PROFILE INFO SETTINGS -->
                    <div id="acc-view-settings" class="acc-view-section hidden space-y-4">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h2 class="text-base font-bold text-gray-900 mb-1">Edit Account Profile</h2>
                            <p class="text-xs text-gray-500 mb-4 pb-3 border-b">Update your personal information, phone number, and seller marketing programs.</p>

                            <form onsubmit="handleAccountInfoUpdate(event)" class="space-y-4 text-xs">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Display Name / Shop Name</label>
                                        <input type="text" id="setDisplayName" required class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Email Address</label>
                                        <input type="email" id="setEmail" required class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Phone Number</label>
                                        <input type="tel" id="setPhone" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">City / Location</label>
                                        <input type="text" id="setLocation" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Avatar Image URL</label>
                                    <input type="url" id="setAvatarUrl" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Bio / Shop Tagline</label>
                                    <textarea id="setBio" rows="3" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent"></textarea>
                                </div>

                                <div class="p-3 bg-redLight/40 rounded-xl border border-red-100 flex items-start gap-2.5">
                                    <input type="checkbox" id="setOptInMarketing" class="mt-0.5 rounded text-redAccent focus:ring-redAccent">
                                    <div>
                                        <label for="setOptInMarketing" class="font-bold text-gray-800 cursor-pointer">Opt-in to CodeCraft Mega Discount & Live Voucher Campaigns</label>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Featured prominently on home with an optional 3.00% program fee.</p>
                                    </div>
                                </div>

                                <div id="accountUpdateStatus" class="hidden p-3 rounded-xl text-xs font-semibold text-center"></div>

                                <div class="flex justify-end pt-3 border-t">
                                    <button type="submit" id="saveAccountBtn" class="px-6 py-2.5 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow transition">
                                        Save Profile Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- SUB-TAB 6: PASSWORD & SECURITY -->
                    <div id="acc-view-security" class="acc-view-section hidden space-y-4">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h2 class="text-base font-bold text-gray-900 mb-1">Change Account Password</h2>
                            <p class="text-xs text-gray-500 mb-4 pb-3 border-b">Ensure your account is using a strong password of at least 6 characters.</p>

                            <form onsubmit="handlePasswordChange(event)" class="space-y-4 text-xs max-w-md">
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Current Password</label>
                                    <div class="relative password-wrapper">
                                        <input type="password" id="pwdCurrent" required placeholder="Enter current password" 
                                               class="w-full px-3 py-2 pr-10 border rounded-xl outline-none focus:border-redAccent">
                                        <button type="button" onclick="togglePasswordVisibility('pwdCurrent', this)" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                                title="Show password" aria-label="Toggle password visibility">
                                            <i class="fa-solid fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">New Password (Min 6 characters)</label>
                                    <div class="relative password-wrapper">
                                        <input type="password" id="pwdNew" required placeholder="Enter new secure password" 
                                               class="w-full px-3 py-2 pr-10 border rounded-xl outline-none focus:border-redAccent">
                                        <button type="button" onclick="togglePasswordVisibility('pwdNew', this)" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                                title="Show password" aria-label="Toggle password visibility">
                                            <i class="fa-solid fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Confirm New Password</label>
                                    <div class="relative password-wrapper">
                                        <input type="password" id="pwdConfirm" required placeholder="Re-type new password" 
                                               class="w-full px-3 py-2 pr-10 border rounded-xl outline-none focus:border-redAccent">
                                        <button type="button" onclick="togglePasswordVisibility('pwdConfirm', this)" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                                title="Show password" aria-label="Toggle password visibility">
                                            <i class="fa-solid fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <div id="passwordStatus" class="hidden p-3 rounded-xl text-xs font-semibold text-center"></div>

                                <div class="pt-2">
                                    <button type="submit" id="savePwdBtn" class="px-6 py-2.5 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow transition">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- TAB 4: INTERACTIVE XAMPP DATABASE EXPLORER WITH FULL CRUD (ADMIN-ONLY) -->
        <section id="tab-database" class="tab-content hidden py-6">
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm mb-6 space-y-5">
                
                <!-- Admin Header & PayMongo Gateway Escrow Info -->
                <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-gray-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> MySQL Connected
                            </span>
                            <span class="text-xs font-mono text-gray-500">Host: localhost:3306</span>
                            <span class="text-xs font-mono text-gray-500">Database: <strong class="text-gray-800">codecraft_shop</strong></span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mt-2 flex items-center gap-2">
                            <i class="fa-solid fa-database text-emerald-600"></i> XAMPP Real-Time DB Admin with Full CRUD
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Admin-only interface: Add, Edit, Delete, and Inspect real MySQL records and Escrow Guarantee ledgers.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button onclick="openAdminCreateRowModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                            <i class="fa-solid fa-plus text-xs"></i> + Add New Record
                        </button>
                        <button onclick="loadDatabaseView()" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700 rounded-xl border border-gray-300 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-rotate"></i> Refresh
                        </button>
                        <a href="http://localhost/phpmyadmin/index.php?route=/database/structure&db=codecraft_shop" target="_blank" class="px-3.5 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> phpMyAdmin
                        </a>
                    </div>
                </div>

                <!-- PayMongo Payment Gateway Architecture Status Card -->
                <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-blue-50 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-lg font-bold shadow-sm shrink-0">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div>
                            <h4 class="font-extrabold text-emerald-950 flex items-center gap-2">
                                Admin Central PayMongo Merchant Gateway & Escrow Model
                                <span class="bg-emerald-200 text-emerald-900 text-[10px] px-2 py-0.5 rounded font-black">ACTIVE</span>
                            </h4>
                            <p class="text-emerald-800/90 text-[11px] mt-0.5 leading-relaxed">
                                All customer payments are collected into the platform's central PayMongo merchant account. CodeCraft platform captures escrow and releases net amounts to sellers via Seller Escrow & Wallets upon delivery confirmation.
                            </p>
                        </div>
                    </div>
                    <div class="bg-white/90 p-2.5 rounded-xl border border-emerald-200 text-[11px] text-gray-600 whitespace-nowrap flex items-center justify-between gap-3">
                        <div>
                            <span class="font-bold text-gray-800">Admin PayMongo Key:</span>
                            <code class="font-mono text-[10px] text-emerald-700 bg-emerald-50 px-1 py-0.5 rounded block mt-0.5">[Configured in Server .env]</code>
                        </div>
                        <a href="admin/orders.php" target="_blank" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center gap-1.5" title="Open Admin Order Monitor & Escrow Management">
                            <i class="fa-solid fa-clipboard-check"></i>
                            <span>Order Monitor & Edit</span>
                        </a>
                    </div>
                </div>

                <!-- Table Selector Nav -->
                <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                    <div class="flex flex-wrap gap-1.5 text-xs font-semibold" id="tableSelectorButtons">
                        <button onclick="selectDbTable('orders')" id="tbl-btn-orders" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white shadow-sm font-bold">orders</button>
                        <button onclick="selectDbTable('escrow_transactions')" id="tbl-btn-escrow_transactions" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">escrow_transactions</button>
                        <button onclick="selectDbTable('seller_wallets')" id="tbl-btn-seller_wallets" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">seller_wallets</button>
                        <button onclick="selectDbTable('products')" id="tbl-btn-products" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">products</button>
                        <button onclick="selectDbTable('users')" id="tbl-btn-users" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">users</button>
                        <button onclick="selectDbTable('bank_accounts')" id="tbl-btn-bank_accounts" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">bank_accounts</button>
                        <button onclick="selectDbTable('withdrawals')" id="tbl-btn-withdrawals" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">withdrawals</button>
                    </div>

                    <div class="text-xs text-gray-500 font-semibold flex items-center gap-1.5">
                        <span id="adminTableTitle" class="font-mono text-gray-800 font-bold uppercase">orders</span>
                        <span id="adminTableStatusTag">(CRUD enabled)</span>
                    </div>
                </div>

                <!-- Live Table Grid View with Actions Column -->
                <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-inner bg-gray-50">
                    <div class="overflow-x-auto max-h-[480px]" id="dbTableWrapper">
                        <div class="p-8 text-center text-gray-400">
                            <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-500"></i>
                            <p class="text-xs">Loading database records from MySQL...</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- ADMIN CRUD RECORD MODAL (CREATE / EDIT) -->
    <div id="adminRecordModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeAdminRecordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-gray-100">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900" id="adminRecordModalTitle">Manage Record</h3>
                    <p class="text-xs text-gray-500" id="adminRecordModalSub">Schema: <strong id="adminModalTable">products</strong></p>
                </div>
            </div>

            <form id="adminRecordForm" onsubmit="handleAdminRecordSubmit(event)" class="space-y-3.5 text-xs">
                <input type="hidden" id="adminModalActionType" value="create">
                <input type="hidden" id="adminModalRowId" value="">

                <div id="adminDynamicFields" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    <!-- Dynamically populated based on table schema -->
                </div>

                <div id="adminRecordStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <div class="pt-3 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeAdminRecordModal()" class="px-4 py-2 border rounded-full font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button type="submit" id="adminSaveRecordBtn" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-full shadow transition">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- UPDATE PROFILE PICTURE MODAL -->
    <div id="avatarModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <button onclick="closeAvatarModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                <div class="w-9 h-9 rounded-xl bg-redLight text-redAccent flex items-center justify-center font-bold text-base">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900">Update Profile Picture</h3>
                    <p class="text-xs text-gray-500">Upload a photo from your device or paste an image URL.</p>
                </div>
            </div>

            <form onsubmit="handleAvatarSubmit(event)" class="space-y-4 text-xs">
                <!-- Preview current and new avatar -->
                <div class="flex flex-col items-center justify-center py-2">
                    <div class="relative">
                        <img id="avatarModalPreview" src="<?= DEFAULT_AVATAR_URL ?>" class="w-24 h-24 rounded-full object-cover border-4 border-redAccent shadow-md" alt="Avatar Preview">
                    </div>
                    <span class="text-[11px] text-gray-400 mt-2">Preview of your profile photo</span>
                </div>

                <!-- Tabs: Device Upload or URL -->
                <div class="flex border-b border-gray-200 text-xs font-bold">
                    <button type="button" onclick="switchAvatarMode('device')" id="btn-avatar-mode-device" class="flex-1 pb-2 text-center border-b-2 border-redAccent text-redAccent">
                        <i class="fa-solid fa-upload mr-1"></i> Device Upload
                    </button>
                    <button type="button" onclick="switchAvatarMode('url')" id="btn-avatar-mode-url" class="flex-1 pb-2 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-link mr-1"></i> Image URL
                    </button>
                </div>

                <!-- Device Upload Section -->
                <div id="avatarDeviceSection" class="space-y-2">
                    <label class="block font-bold text-gray-700">Choose Image File (JPG, PNG, WEBP)</label>
                    <input type="file" id="avatarFileInput" accept="image/*" onchange="previewAvatarFromFile(event)" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-redLight file:text-redAccent hover:file:bg-red-100 cursor-pointer">
                </div>

                <!-- URL Section -->
                <div id="avatarUrlSection" class="space-y-2 hidden">
                    <label class="block font-bold text-gray-700">Image Web Address (URL)</label>
                    <input type="url" id="avatarUrlInput" oninput="previewAvatarFromUrl(this.value)" placeholder="https://..." class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                    <button type="button" onclick="resetToDefaultAvatar()" class="text-[11px] text-redAccent hover:underline font-semibold block">
                        <i class="fa-solid fa-rotate-left mr-1"></i> Reset to Default CodeCraft Profile Picture
                    </button>
                </div>

                <div id="avatarUpdateStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <div class="pt-2 flex justify-end gap-2 border-t">
                    <button type="button" onclick="closeAvatarModal()" class="px-4 py-2 border rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button type="submit" id="saveAvatarBtn" class="px-5 py-2 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow transition">Save Photo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT CRAFT LISTING MODAL -->
    <div id="editCraftModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeEditCraftModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-base">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900">Edit Posted Craft</h3>
                    <p class="text-xs text-gray-500">Update craft details, price, or description in your store.</p>
                </div>
            </div>

            <form onsubmit="handleEditCraftSubmit(event)" class="space-y-4 text-xs">
                <input type="hidden" id="editCraftId" value="">

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Craft Title</label>
                    <input type="text" id="editCraftTitle" required class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Category</label>
                        <select id="editCraftCategory" onchange="updateEditFeeEstimator()" required class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent bg-white font-medium">
                            <option value="Fashion">Fashion (6% comm)</option>
                            <option value="Beauty">Beauty (6% comm)</option>
                            <option value="Home & Living">Home & Living (6% comm)</option>
                            <option value="Tech">Tech (4.5% comm)</option>
                            <option value="Food & Cafe">Food & Cafe (5% comm)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Listing Price (₱ PHP)</label>
                        <input type="number" step="0.01" min="1" id="editCraftPrice" oninput="updateEditFeeEstimator()" required class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent font-bold">
                    </div>
                </div>

                <!-- Live Fee Deduction Preview -->
                <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 space-y-1.5 text-[11px]">
                    <div class="flex justify-between items-center text-gray-600">
                        <span>Platform Deductions (15%–20%):</span>
                        <span id="editFeeTotalDeductions" class="font-bold text-rose-600">-₱0.00</span>
                    </div>
                    <div class="flex justify-between items-center font-bold text-gray-800 pt-1 border-t border-gray-200">
                        <span>Estimated Net Payout (Escrow Release):</span>
                        <span id="editFeeNetAmount" class="text-emerald-600 font-extrabold text-xs">₱0.00</span>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Craft Image</label>
                    <div class="flex items-center gap-3">
                        <img id="editCraftPreviewImg" src="" class="w-14 h-14 rounded-xl object-cover border" alt="Preview">
                        <div class="flex-1 space-y-1.5">
                            <input type="text" id="editCraftImageUrl" oninput="document.getElementById('editCraftPreviewImg').src = this.value" placeholder="Image URL..." class="w-full px-3 py-1.5 text-xs border rounded-xl outline-none focus:border-redAccent">
                            <input type="file" id="editCraftFile" accept="image/*" onchange="handleEditCraftFileUpload(event)" class="text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-gray-200 file:text-gray-800 hover:file:bg-gray-300 cursor-pointer">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Description</label>
                    <textarea id="editCraftDescription" rows="3" class="w-full px-3 py-2 text-xs border rounded-xl outline-none focus:border-redAccent"></textarea>
                </div>

                <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-xl border">
                    <div>
                        <span class="font-bold text-gray-800 block">Listing Status</span>
                        <span class="text-[10px] text-gray-500">Active crafts appear on the CodeCraft storefront</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="editCraftIsActive" checked class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <div id="editCraftStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <div class="pt-3 border-t flex items-center justify-between">
                    <button type="button" onclick="handleDeleteCraft()" class="px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 rounded-full border border-red-200 transition flex items-center gap-1">
                        <i class="fa-solid fa-trash"></i> Delete Craft
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditCraftModal()" class="px-4 py-2 border rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button type="submit" id="btnSaveCraft" class="px-5 py-2 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow transition">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- SHIPPING FEE RESPONSIBILITY ASSISTANT MODAL -->
    <div id="shippingAssistantModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeShippingAssistantModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-gray-100">
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-base">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900">Shipping Fee Responsibility Assistant</h3>
                    <p class="text-xs text-gray-500">CodeCraft policy decision tree for forward & return shipping liability.</p>
                </div>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Step 1: Who caused the issue? -->
                <div>
                    <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider">
                        1. Who caused the issue?
                    </label>
                    <select id="shipAstCause" onchange="runShippingAssistantEval()" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-blue-600 bg-white font-medium">
                        <option value="seller_fault">Seller Fault (defective, wrong item, not as described, late dispatch)</option>
                        <option value="buyer_fault">Buyer Fault (change of mind, wrong size selected, remorse)</option>
                        <option value="carrier_fault">Carrier / Platform Fault (parcel lost or damaged in transit)</option>
                        <option value="unclear">Unclear / Disputed (requires investigation & manual review)</option>
                    </select>
                </div>

                <!-- Step 2-4: Criteria check -->
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-800 block">2. Enrolled in Fee-Support Program?</span>
                            <span class="text-[11px] text-gray-500">Platform return voucher / subsidized label program</span>
                        </div>
                        <input type="checkbox" id="shipAstProgram" onchange="runShippingAssistantEval()" class="w-4 h-4 text-blue-600 rounded">
                    </div>

                    <div class="border-t border-gray-200 pt-2 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-800 block">3. Seller Free Shipping Offered?</span>
                            <span class="text-[11px] text-gray-500">Seller covers forward delivery costs initially</span>
                        </div>
                        <input type="checkbox" id="shipAstFreeShip" onchange="runShippingAssistantEval()" class="w-4 h-4 text-blue-600 rounded">
                    </div>

                    <div class="border-t border-gray-200 pt-2 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-800 block">4. Subsidy Ceiling / Cap Hit?</span>
                            <span class="text-[11px] text-gray-500">Monthly subsidy ceiling exceeded</span>
                        </div>
                        <input type="checkbox" id="shipAstCapsHit" onchange="runShippingAssistantEval()" class="w-4 h-4 text-blue-600 rounded">
                    </div>
                </div>

                <!-- Structured Policy Output Card -->
                <div class="p-4 bg-gradient-to-br from-slate-50 to-blue-50/50 rounded-2xl border border-blue-200 space-y-2.5">
                    <div class="flex items-center justify-between pb-2 border-b border-blue-200/60">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-blue-800">Policy Assessment</span>
                        <span id="shipAstConfidenceBadge" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800">
                            Confidence: High
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">Responsible Payer</span>
                            <strong id="shipAstPayer" class="text-sm font-extrabold text-blue-900">Seller</strong>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">Rule Applied</span>
                            <span id="shipAstRule" class="text-[11px] font-medium text-gray-700">Step 1: Seller fault</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-blue-100 space-y-1.5 text-[11px]">
                        <div>
                            <strong class="text-gray-700 font-bold block">Forward Shipping:</strong>
                            <span id="shipAstForward" class="text-gray-600">Covered by Seller (or refunded to buyer)</span>
                        </div>
                        <div>
                            <strong class="text-gray-700 font-bold block">Return Shipping:</strong>
                            <span id="shipAstReturn" class="text-gray-600">Covered by Seller</span>
                        </div>
                        <div>
                            <strong class="text-gray-700 font-bold block">Conditions / Exceptions:</strong>
                            <span id="shipAstConditions" class="text-gray-600">Seller is liable for all transit fees. Seller must provide return waybill or refund standard courier rates.</span>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" onclick="closeShippingAssistantModal()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-full shadow transition">
                        Done / Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- POST DETAIL MODAL -->
    <div id="postModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4 hidden">
        <div class="bg-white w-full max-w-4xl max-h-[90vh] rounded-2xl overflow-hidden shadow-2xl flex flex-col md:flex-row relative animate-fadeIn">
            <button onclick="closePostModal()" class="absolute top-3 right-3 z-10 w-9 h-9 bg-black/40 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="w-full md:w-1/2 bg-black flex items-center justify-center max-h-[40vh] md:max-h-full">
                <img id="modalImage" src="" class="max-h-full w-auto object-contain" alt="Craft detail">
            </div>

            <div class="w-full md:w-1/2 flex flex-col justify-between p-5 bg-white overflow-y-auto">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <img id="modalAvatar" src="" class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="Avatar">
                            <div>
                                <h4 id="modalAuthor" class="font-bold text-sm text-gray-900"></h4>
                                <p class="text-[11px] text-gray-400">Verified Artisan • Manila</p>
                            </div>
                        </div>
                        <span class="text-[10px] bg-redLight text-redAccent px-2.5 py-1 rounded-full font-bold">
                            <i class="fa-solid fa-shield-halved"></i> Guarantee Protected
                        </span>
                    </div>

                    <div class="py-3">
                        <span id="modalCategory" class="inline-block text-[10px] font-bold uppercase tracking-wider text-redAccent bg-redLight px-2.5 py-0.5 rounded-full mb-2">Category</span>
                        <h2 id="modalTitle" class="text-xl font-bold text-gray-900 mb-2 leading-tight"></h2>
                        <p id="modalDesc" class="text-xs text-gray-600 leading-relaxed mb-4 whitespace-pre-line"></p>
                        
                        <div class="bg-gray-50 p-3 rounded-xl flex items-center justify-between border border-gray-100">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-gray-400 block">Listing Price</span>
                                <span id="modalPrice" class="text-2xl font-black text-redAccent">₱0.00</span>
                            </div>
                            <span class="text-[11px] bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-full font-bold flex items-center gap-1">
                                <i class="fa-solid fa-truck-fast"></i> In Stock • Escrow Protected
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-3">
                    <button id="modalLikeBtn" onclick="toggleModalLike()" class="flex flex-col items-center justify-center text-gray-500 hover:text-redAccent transition px-2">
                        <i id="modalLikeIcon" class="fa-regular fa-heart text-xl"></i>
                        <span id="modalLikesCount" class="text-[10px] font-semibold mt-0.5">0</span>
                    </button>

                    <div class="flex-1 flex gap-2">
                        <button onclick="addToCartFromModal()" class="flex-1 py-2.5 bg-redLight text-redAccent hover:bg-red-100 font-bold text-xs rounded-full transition border border-red-200 flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-cart-plus"></i> Add to Bag
                        </button>
                        <button onclick="buyNowFromModal()" class="flex-1 py-2.5 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full transition shadow-md flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-bolt"></i> Buy Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CART & CHECKOUT DRAWER -->
    <div id="cartDrawer" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex justify-end">
        <div class="bg-white w-full max-w-md h-full shadow-2xl flex flex-col justify-between transform transition-transform duration-300">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-white sticky top-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-bag-shopping text-redAccent text-lg"></i>
                    <h3 class="font-bold text-base text-gray-900">Your Shopping Bag</h3>
                </div>
                <button onclick="closeCartDrawer()" class="text-gray-400 hover:text-gray-700 p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-1 space-y-3" id="cartItemsList"></div>

            <div class="p-4 border-t border-gray-200 bg-gray-50 space-y-3">
                <div class="space-y-1.5 text-xs text-gray-600">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span id="cartSubtotal" class="font-semibold text-gray-900">₱0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CodeCraft Guarantee</span>
                        <span class="font-bold text-emerald-600">INCLUDED (FREE)</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-gray-900 pt-2 border-t border-gray-200">
                        <span>Total Amount</span>
                        <span id="cartTotal" class="text-redAccent font-black text-base">₱0.00</span>
                    </div>
                </div>

                <button id="checkoutBtn" onclick="openCheckoutModal()" class="w-full py-3 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full shadow-md transition flex items-center justify-center gap-2">
                    <span>Proceed to Escrow Checkout</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ESCROW CHECKOUT MODAL (REQUIRES ACCOUNT) -->
    <div id="checkoutModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
            <button onclick="closeCheckoutModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                <div class="bg-redAccent text-white px-2.5 py-1.5 rounded-xl font-bold text-xs tracking-wider flex items-center gap-1 shadow-sm">
                    <i class="fa-solid fa-shield-halved"></i> Escrow
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900">CodeCraft Guarantee Checkout</h3>
                    <p class="text-xs text-gray-500">Funds are captured in platform escrow & released upon your confirmation</p>
                </div>
            </div>

            <form id="paymongoForm" onsubmit="processCheckout(event)" class="space-y-4 text-xs">
                <div class="bg-gray-50 p-3.5 rounded-xl text-xs space-y-1 border border-gray-100">
                    <div class="flex justify-between font-semibold">
                        <span>Order Total:</span>
                        <span id="checkoutTotalAmount" class="text-redAccent font-black text-base">₱0.00</span>
                    </div>
                    <p class="text-[11px] text-gray-400">Payment goes to Admin PayMongo gateway. CodeCraft guarantees delivery before releasing funds to seller.</p>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Full Delivery Name</label>
                    <input type="text" id="shipName" required class="w-full px-3 py-2 text-sm border rounded-xl focus:border-redAccent outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Phone Number</label>
                        <input type="tel" id="shipPhone" required value="+639171234567" 
                               class="w-full px-3 py-2 text-sm border rounded-xl focus:border-redAccent outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Payment Method</label>
                        <select id="paymentMethod" class="w-full px-3 py-2 text-sm border rounded-xl focus:border-redAccent outline-none bg-white font-medium">
                            <option value="gcash">GCash (Escrow)</option>
                            <option value="paymaya">Maya Wallet</option>
                            <option value="card">Credit / Debit Card</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase tracking-wider mb-1">Complete Delivery Address</label>
                    <textarea id="shipAddress" rows="2" required class="w-full px-3 py-2 text-sm border rounded-xl focus:border-redAccent outline-none">Unit 402, High Street Residences, BGC, Taguig City, Metro Manila</textarea>
                </div>

                <div id="checkoutStatus" class="hidden p-3 rounded-xl text-xs font-semibold text-center"></div>

                <button type="submit" id="paySubmitBtn" class="w-full py-3 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full shadow-md transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock"></i> Authorize Payment & Capture in Escrow
                </button>
            </form>
        </div>
    </div>

    <!-- WITHDRAWAL MODAL -->
    <div id="withdrawModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <button onclick="closeWithdrawModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <h3 class="font-bold text-base text-gray-900 mb-1">Withdraw Seller Earnings</h3>
            <p class="text-xs text-gray-500 mb-4 pb-2 border-b">Transfer available wallet funds to your linked bank account.</p>

            <form onsubmit="handleWithdrawalSubmit(event)" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Select Bank / Wallet</label>
                    <select id="withBankSelect" required class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent bg-white"></select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Amount to Withdraw (₱ PHP)</label>
                    <input type="number" step="0.01" min="10" id="withAmount" required placeholder="500.00" 
                           class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent font-bold">
                    <span class="text-[11px] text-gray-400 block mt-1">Available: <strong id="withMaxAvailable" class="text-emerald-600 font-bold">₱0.00</strong></span>
                </div>

                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-200 text-[11px] space-y-1">
                    <div class="flex justify-between">
                        <span>Withdrawal Fee:</span>
                        <span id="withFeeNotice" class="font-bold text-gray-700">First 2 free, partner banks free</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Schedule:</span>
                        <span class="text-gray-500">Weekdays (Weekend requests queue for Mon)</span>
                    </div>
                </div>

                <div id="withdrawStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <button type="submit" id="btnSubmitWithdraw" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-full shadow transition">
                    Submit Withdrawal Request
                </button>
            </form>
        </div>
    </div>

    <!-- LINK BANK ACCOUNT MODAL -->
    <div id="addBankModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <button onclick="closeAddBankModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <h3 class="font-bold text-base text-gray-900 mb-1">Link Bank Account or E-Wallet</h3>
            <p class="text-xs text-gray-500 mb-4 pb-2 border-b">Add a verified payout account for wallet withdrawals.</p>

            <form onsubmit="handleAddBankSubmit(event)" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Bank or Wallet Name</label>
                    <select id="newBankName" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent bg-white">
                        <option value="BDO Unibank (Partner)">BDO Unibank (Partner - Free Instant)</option>
                        <option value="GCash Partner Wallet">GCash Partner Wallet (Free Instant)</option>
                        <option value="Bank of the Philippine Islands (BPI)">Bank of the Philippine Islands (BPI)</option>
                        <option value="UnionBank of the Philippines">UnionBank of the Philippines</option>
                        <option value="Metrobank">Metrobank</option>
                        <option value="Maya Wallet">Maya Wallet</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Account Holder Full Name</label>
                    <input type="text" id="newAccName" required placeholder="e.g. Lin Santos" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Account Number / Mobile Number</label>
                    <input type="text" id="newAccNumber" required placeholder="e.g. 09171234567 or 1234567890" class="w-full px-3 py-2 border rounded-xl outline-none focus:border-redAccent">
                </div>

                <div id="addBankStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <button type="submit" id="btnSubmitAddBank" class="w-full py-2.5 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full shadow transition">
                    Verify & Link Account
                </button>
            </form>
        </div>
    </div>

    <!-- SIGN UP / REGISTER & SIGN IN MODAL -->
    <div id="authModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative animate-fadeIn">
            <button onclick="closeAuthModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="flex items-center gap-2 mb-4">
                <div class="bg-redAccent text-white font-extrabold text-sm px-2.5 py-1 rounded-lg tracking-wider flex items-center gap-1">
                    <i class="fa-solid fa-shapes text-xs"></i>
                    <span>CodeCraft</span>
                </div>
                <h3 class="font-bold text-base text-gray-900" id="authModalTitle">Welcome to CodeCraft</h3>
            </div>

            <div class="flex border-b border-gray-200 mb-5 text-sm font-bold">
                <button onclick="switchAuthTab('login')" id="auth-tab-btn-login" class="flex-1 pb-3 text-center border-b-2 border-redAccent text-redAccent">
                    Sign In
                </button>
                <button onclick="switchAuthTab('register')" id="auth-tab-btn-register" class="flex-1 pb-3 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-700">
                    Register / Sign Up
                </button>
            </div>

            <!-- SIGN IN FORM -->
            <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Username or Email</label>
                    <input type="text" id="loginUsername" required placeholder="e.g. lin_design or admin" 
                           class="w-full px-3 py-2.5 text-sm border rounded-xl outline-none focus:border-redAccent">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Password</label>
                    <div class="relative password-wrapper">
                        <input type="password" id="loginPassword" required placeholder="Enter your password" 
                               class="w-full px-3 py-2.5 pr-10 text-sm border rounded-xl outline-none focus:border-redAccent">
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', this)" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                title="Show password" aria-label="Toggle password visibility">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100 flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-gray-500">Quick Demo Accounts:</span>
                    <div class="flex gap-1.5">
                        <button type="button" onclick="fillDemoLogin('lin_design', 'password')" class="px-2 py-0.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-[11px] hover:border-redAccent">
                            User (Lin)
                        </button>
                        <button type="button" onclick="fillDemoLogin('admin', 'admin123')" class="px-2 py-0.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-[11px] hover:border-redAccent font-bold">
                            Admin (admin123)
                        </button>
                    </div>
                </div>

                <div id="loginStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <button type="submit" id="loginSubmitBtn" class="w-full py-2.5 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full shadow-md transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Account
                </button>
            </form>

            <!-- REGISTER FORM -->
            <form id="registerForm" onsubmit="handleRegisterSubmit(event)" class="space-y-3 text-xs hidden">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Display Name / Shop Name</label>
                    <input type="text" id="regDisplayName" required placeholder="e.g. Mia Craft Studio" 
                           class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Username</label>
                        <input type="text" id="regUsername" required placeholder="e.g. mia_crafts" 
                               class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent">
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" id="regEmail" required placeholder="mia@example.com" 
                               class="w-full px-3 py-2 text-sm border rounded-xl outline-none focus:border-redAccent">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Password</label>
                        <div class="relative password-wrapper">
                            <input type="password" id="regPassword" required placeholder="Min 6 characters" 
                                   class="w-full px-3 py-2 pr-9 text-sm border rounded-xl outline-none focus:border-redAccent">
                            <button type="button" onclick="togglePasswordVisibility('regPassword', this)" 
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                    title="Show password" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Confirm Password</label>
                        <div class="relative password-wrapper">
                            <input type="password" id="regPasswordConfirm" required placeholder="Re-type password" 
                                   class="w-full px-3 py-2 pr-9 text-sm border rounded-xl outline-none focus:border-redAccent">
                            <button type="button" onclick="togglePasswordVisibility('regPasswordConfirm', this)" 
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                    title="Show password" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Phone Number</label>
                    <input type="tel" id="regPhone" value="+63 917 123 4567" class="w-full px-3 py-2 text-xs border rounded-xl outline-none focus:border-redAccent">
                </div>

                <div id="registerStatus" class="hidden p-2.5 rounded-xl text-xs font-semibold text-center"></div>

                <button type="submit" id="registerSubmitBtn" class="w-full py-2.5 bg-redAccent hover:bg-redHover text-white font-bold text-xs rounded-full shadow-md transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-user-plus"></i> Create CodeCraft Account
                </button>
            </form>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION BAR -->
    <div class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-gray-200 flex items-center justify-around py-2 md:hidden">
        <button onclick="switchTab('feed')" id="mob-tab-feed" class="flex flex-col items-center text-redAccent">
            <i class="fa-solid fa-compass text-lg"></i>
            <span class="text-[10px] font-medium mt-0.5">Explore</span>
        </button>
        <button onclick="switchTab('create')" id="mob-tab-create" class="flex flex-col items-center text-gray-500">
            <i class="fa-solid fa-circle-plus text-lg"></i>
            <span class="text-[10px] font-medium mt-0.5">Add Craft</span>
        </button>
        <button onclick="openCartDrawer()" class="flex flex-col items-center text-gray-500 relative">
            <i class="fa-solid fa-bag-shopping text-lg"></i>
            <span class="text-[10px] font-medium mt-0.5">Cart</span>
            <span id="mobCartBadge" class="absolute -top-1 right-1 bg-redAccent text-white text-[9px] w-4 h-4 rounded-full flex items-center justify-center font-bold hidden">0</span>
        </button>
        
        <!-- Only visible for Admin on mobile -->
        <div id="mobAdminDbNavItem" class="<?= $isAdmin ? '' : 'hidden' ?>">
            <button onclick="switchTab('database')" id="mob-tab-database" class="flex flex-col items-center text-emerald-600 font-bold">
                <i class="fa-solid fa-database text-lg"></i>
                <span class="text-[10px] font-medium mt-0.5">DB</span>
            </button>
        </div>

        <button onclick="if(currentUser){switchTab('account'); switchAccountSubTab('notifications');}else{openAuthModal('login');}" id="mob-tab-notifs" class="flex flex-col items-center text-gray-500 relative">
            <i class="fa-solid fa-bell text-lg"></i>
            <span class="text-[10px] font-medium mt-0.5">Alerts</span>
            <span id="mobNotifBadge" class="absolute -top-1 right-1 bg-redAccent text-white text-[9px] w-4 h-4 rounded-full flex items-center justify-center font-bold hidden">0</span>
        </button>

        <button onclick="handleUserAvatarClick()" id="mob-tab-account" class="flex flex-col items-center text-gray-500">
            <i class="fa-solid fa-user text-lg"></i>
            <span class="text-[10px] font-medium mt-0.5">Account</span>
        </button>
    </div>

    <!-- MAIN JAVASCRIPT APPLICATION -->
    <script>
        // STATE
        let allPosts = <?= json_encode($initialProducts) ?>;
        let currentUser = <?= json_encode($initialUser) ?>;
        let categories = ["All", "Fashion", "Beauty", "Home & Living", "Tech", "Food & Cafe"];
        let activeCategory = "All";
        let cart = [];
        let activePostId = null;
        let currentDbTable = 'orders';
        let currentTableRows = [];
        let currentUploadMode = 'device';
        let currentUploadedUrl = '';
        let pendingRedirectTab = null;

        function initApp() {
            renderCategories();
            updateAuthUI();
            updateWalletBanner();
            renderFeed();
            fetchProducts();
            updateLiveFeeEstimator();
            loadNotifications();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initApp);
        } else {
            initApp();
        }

            // Periodic live polling for notifications and orders every 30 seconds
            setInterval(() => {
                if (currentUser) {
                    loadNotifications(true);
                }
            }, 30000);

            // Close notification dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const notifContainer = document.getElementById('notifNavContainer');
                const dropdown = document.getElementById('notifDropdown');
                if (dropdown && !dropdown.classList.contains('hidden') && notifContainer && !notifContainer.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // Auto-open register / login modal if navigated with ?auth=register or ?auth=login
            const urlParams = new URLSearchParams(window.location.search);
            const authParam = urlParams.get('auth');
            if (authParam === 'register' || authParam === 'login') {
                openAuthModal(authParam);
                if (urlParams.get('redirect') === 'checkout') {
                    const statusElem = authParam === 'register' ? document.getElementById('registerStatus') : document.getElementById('loginStatus');
                    statusElem.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                    statusElem.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> You cannot purchase without an account. Please create an account or sign in to complete checkout.`;
                    statusElem.classList.remove('hidden');
                }
            }

            // Auto-navigate to requested tab and subtab (e.g. ?tab=account&sub=orders after PayMongo checkout)
            const tabParam = urlParams.get('tab');
            const subParam = urlParams.get('sub');
            if (tabParam) {
                switchTab(tabParam);
                if (tabParam === 'account') {
                    const targetSub = subParam || 'overview';
                    switchAccountSubTab(targetSub);
                    if (targetSub === 'orders') {
                        loadUserOrders().then(() => {
                            if (window.location.hash) {
                                setTimeout(() => {
                                    try {
                                        const targetEl = document.querySelector(window.location.hash);
                                        if (targetEl) {
                                            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            targetEl.classList.add('ring-2', 'ring-redAccent', 'shadow-lg');
                                        }
                                    } catch (e) {}
                                }, 500);
                            }
                        });
                    }
                }
            }
        // ================= FETCH PRODUCTS & USER STATE =================
        async function fetchProducts() {
            try {
                const res = await fetch('api.php?action=get_products');
                const data = await res.json();
                if (data.success) {
                    allPosts = data.products;
                    renderFeed();
                    renderAccountCraftsGrid();
                }
            } catch (err) {
                console.error(err);
            }
        }

        // ================= NAVIGATION TAB SWITCHER =================
        function switchTab(tabName) {
            // Require authentication for Adding Crafts
            if (tabName === 'create' && !currentUser) {
                openAuthModal('login');
                const loginStatus = document.getElementById('loginStatus');
                loginStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                loginStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please sign in or register to add and publish crafts to CodeCraft.`;
                loginStatus.classList.remove('hidden');
                pendingRedirectTab = 'create';
                return;
            }

            // Require authentication for Account
            if (tabName === 'account' && !currentUser) {
                openAuthModal('login');
                const loginStatus = document.getElementById('loginStatus');
                loginStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                loginStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please sign in or register to view your account, orders, or seller wallet.`;
                loginStatus.classList.remove('hidden');
                return;
            }

            // Require admin for database
            if (tabName === 'database' && (!currentUser || currentUser.role !== 'admin')) {
                alert('XAMPP Database Explorer is restricted to Admin accounts. Please log in with admin credentials.');
                openAuthModal('login');
                return;
            }

            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById(`tab-${tabName}`);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('header nav button, header [id^="nav-btn-"]').forEach(b => {
                b.classList.remove('text-redAccent', 'font-bold');
            });
            const activeBtn = document.getElementById(`nav-btn-${tabName}`);
            if (activeBtn) activeBtn.classList.add('text-redAccent', 'font-bold');

            if (tabName === 'database') loadDatabaseView();
            else if (tabName === 'account') loadAccountOverview();

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateWalletBanner() {
            const bannerAction = document.getElementById('bannerWalletAction');
            if (!bannerAction) return;
            if (currentUser) {
                bannerAction.innerHTML = `
                    <button onclick="switchTab('account'); switchAccountSubTab('wallet')" class="text-xs font-bold bg-white text-redAccent hover:bg-gray-100 px-4 py-2 rounded-full shadow transition whitespace-nowrap">
                        <i class="fa-solid fa-wallet mr-1"></i> View Seller Wallet & Escrow
                    </button>
                `;
            } else {
                // If not signed in, do not show View Seller Wallet & Escrow
                bannerAction.innerHTML = '';
            }
        }

        // ================= AUTH UI & STATE =================
        function updateAuthUI() {
            const container = document.getElementById('authNavContainer');
            const adminDbItem = document.getElementById('adminDbNavItem');
            const mobAdminDbItem = document.getElementById('mobAdminDbNavItem');

            const isAdmin = currentUser && currentUser.role === 'admin';
            if (isAdmin) {
                adminDbItem.classList.remove('hidden');
                mobAdminDbItem.classList.remove('hidden');
            } else {
                adminDbItem.classList.add('hidden');
                mobAdminDbItem.classList.add('hidden');
            }

            if (currentUser) {
                // Clicking profile in top right directly goes to Account Overview (no pop-up dropdown!)
                container.innerHTML = `
                    <button onclick="switchTab('account'); switchAccountSubTab('overview')" class="flex items-center gap-2 border border-gray-200 p-1 pr-3 rounded-full hover:border-redAccent hover:bg-redLight/30 transition bg-white shadow-sm cursor-pointer" title="Go to Account Overview">
                        <img src="${currentUser.avatar_url || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10'}" 
                             class="w-7 h-7 rounded-full object-cover border border-gray-200" alt="Avatar">
                        <span class="text-xs font-semibold truncate max-w-[100px]">${currentUser.display_name}</span>
                    </button>
                `;
                document.getElementById('shipName').value = currentUser.display_name;
                loadNotifications(true);
            } else {
                container.innerHTML = `
                    <button onclick="openAuthModal('login')" class="px-4 py-2 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In / Register
                    </button>
                `;
                document.getElementById('shipName').value = 'Guest Buyer';
                const notifBadge = document.getElementById('notifBadge');
                const mobNotifBadge = document.getElementById('mobNotifBadge');
                if (notifBadge) notifBadge.classList.add('hidden');
                if (mobNotifBadge) mobNotifBadge.classList.add('hidden');
            }
            updateWalletBanner();
        }

        function handleUserAvatarClick() {
            if (currentUser) {
                switchTab('account');
                switchAccountSubTab('overview');
            } else {
                openAuthModal('login');
            }
        }

        // ================= AUTH MODAL (LOGIN / REGISTER) =================
        function openAuthModal(defaultTab = 'login') {
            document.getElementById('authModal').classList.remove('hidden');
            switchAuthTab(defaultTab);
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
            pendingRedirectTab = null;
        }

        function switchAuthTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const regForm = document.getElementById('registerForm');
            const loginBtn = document.getElementById('auth-tab-btn-login');
            const regBtn = document.getElementById('auth-tab-btn-register');

            if (tab === 'login') {
                loginForm.classList.remove('hidden');
                regForm.classList.add('hidden');
                loginBtn.className = "flex-1 pb-3 text-center border-b-2 border-redAccent text-redAccent font-bold";
                regBtn.className = "flex-1 pb-3 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-700";
            } else {
                loginForm.classList.add('hidden');
                regForm.classList.remove('hidden');
                loginBtn.className = "flex-1 pb-3 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-700";
                regBtn.className = "flex-1 pb-3 text-center border-b-2 border-redAccent text-redAccent font-bold";
            }
        }

        function togglePasswordVisibility(target, btn) {
            let input = (typeof target === 'string') ? document.getElementById(target) : target;
            if (!input && btn) {
                input = btn.closest('.password-wrapper')?.querySelector('input');
            }
            if (!input) return;
            const isNowText = (input.type === 'password');
            input.type = isNowText ? 'text' : 'password';
            const icon = btn.querySelector('i') || btn;
            if (icon) {
                if (isNowText) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    btn.setAttribute('title', 'Hide password');
                    btn.setAttribute('aria-label', 'Hide password');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    btn.setAttribute('title', 'Show password');
                    btn.setAttribute('aria-label', 'Show password');
                }
            }
        }

        function fillDemoLogin(u, p) {
            document.getElementById('loginUsername').value = u;
            document.getElementById('loginPassword').value = p;
        }

        async function handleLoginSubmit(e) {
            e.preventDefault();
            const u = document.getElementById('loginUsername').value;
            const p = document.getElementById('loginPassword').value;
            const statusDiv = document.getElementById('loginStatus');
            const submitBtn = document.getElementById('loginSubmitBtn');

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Verifying with MySQL...`;
            statusDiv.classList.remove('hidden');
            submitBtn.disabled = true;

            const fd = new FormData();
            fd.append('username', u);
            fd.append('password', p);

            try {
                const res = await fetch('api.php?action=login', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    currentUser = data.user;
                    updateAuthUI();
                    fetchProducts();
                    setTimeout(() => {
                        closeAuthModal();
                        submitBtn.disabled = false;
                        const dest = pendingRedirectTab || 'feed';
                        pendingRedirectTab = null;
                        switchTab(dest);
                    }, 800);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error || 'Login failed.'}`;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> Connection error.`;
                submitBtn.disabled = false;
            }
        }

        async function handleRegisterSubmit(e) {
            e.preventDefault();
            const displayName = document.getElementById('regDisplayName').value;
            const username = document.getElementById('regUsername').value;
            const email = document.getElementById('regEmail').value;
            const password = document.getElementById('regPassword').value;
            const passwordConfirm = document.getElementById('regPasswordConfirm').value;
            const phone = document.getElementById('regPhone').value;
            const statusDiv = document.getElementById('registerStatus');
            const submitBtn = document.getElementById('registerSubmitBtn');

            if (password !== passwordConfirm) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> Passwords do not match.`;
                statusDiv.classList.remove('hidden');
                return;
            }

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Creating user in MySQL...`;
            statusDiv.classList.remove('hidden');
            submitBtn.disabled = true;

            const fd = new FormData();
            fd.append('display_name', displayName);
            fd.append('username', username);
            fd.append('email', email);
            fd.append('password', password);
            fd.append('phone', phone);

            try {
                const res = await fetch('api.php?action=register', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    currentUser = data.user;
                    updateAuthUI();
                    fetchProducts();
                    setTimeout(() => {
                        closeAuthModal();
                        submitBtn.disabled = false;
                        const dest = pendingRedirectTab || 'account';
                        pendingRedirectTab = null;
                        switchTab(dest);
                    }, 800);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error || 'Registration failed.'}`;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> Connection error.`;
                submitBtn.disabled = false;
            }
        }

        async function handleLogout() {
            try {
                await fetch('api.php?action=logout', { method: 'POST' });
                currentUser = null;
                updateAuthUI();
                fetchProducts();
                switchTab('feed');
            } catch (err) {
                console.error(err);
            }
        }

        // ================= CATEGORIES & FEED RENDER =================
        function renderCategories() {
            const container = document.getElementById('categoryContainer');
            container.innerHTML = categories.map(cat => `
                <button onclick="filterByCategory('${cat}')" 
                        class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition ${activeCategory === cat ? 'bg-redAccent text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'}">
                    ${cat}
                </button>
            `).join('');
        }

        function filterByCategory(cat) {
            activeCategory = cat;
            renderCategories();
            renderFeed();
        }

        function renderFeed() {
            const feedContainer = document.getElementById('masonryFeed');
            const searchVal = document.getElementById('searchInput').value.toLowerCase().trim();
            const sortVal = document.getElementById('sortSelect').value;

            let filtered = allPosts.filter(post => {
                const matchesCategory = activeCategory === "All" || post.category === activeCategory;
                const matchesSearch = post.title.toLowerCase().includes(searchVal) || post.desc.toLowerCase().includes(searchVal);
                return matchesCategory && matchesSearch;
            });

            if (sortVal === 'likes') filtered.sort((a, b) => b.likes - a.likes);
            else if (sortVal === 'price-low') filtered.sort((a, b) => a.price - b.price);
            else if (sortVal === 'price-high') filtered.sort((a, b) => b.price - a.price);
            else filtered.sort((a, b) => b.id - a.id);

            const filterInfo = document.getElementById('filterInfo');
            if (searchVal !== '') {
                filterInfo.classList.remove('hidden');
                document.getElementById('filterTerm').innerText = `"${searchVal}"`;
            } else {
                filterInfo.classList.add('hidden');
            }

            if (filtered.length === 0) {
                feedContainer.innerHTML = `
                    <div class="col-span-full py-16 text-center text-gray-400">
                        <i class="fa-solid fa-box-open text-4xl mb-3 text-gray-300"></i>
                        <p class="text-sm font-semibold">No crafts found in this category.</p>
                        <button onclick="resetFilters()" class="mt-3 px-4 py-1.5 bg-redAccent text-white text-xs font-bold rounded-full">Reset Filters</button>
                    </div>
                `;
                return;
            }

            feedContainer.innerHTML = filtered.map(post => `
                <div data-cy="product-card" class="masonry-item bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition duration-200 group cursor-pointer" onclick="openPostModal(${post.id})">
                    <div class="relative overflow-hidden bg-gray-100">
                        <img src="${post.imageUrl}" alt="${post.title}" class="w-full h-auto object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                        <span data-cy="product-price" class="absolute top-2.5 left-2.5 bg-black/60 backdrop-blur-md text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full shadow">
                            ₱${post.price.toLocaleString('en-US', {minimumFractionDigits: 2})}
                        </span>
                        <span class="absolute top-2.5 right-2.5 bg-white/90 backdrop-blur-md text-gray-800 text-[9px] font-bold px-2 py-0.5 rounded shadow">
                            ${post.category}
                        </span>
                    </div>

                    <div class="p-3">
                        <h3 data-cy="product-name" class="text-xs font-bold text-gray-900 line-clamp-2 leading-snug mb-2 group-hover:text-redAccent transition">
                            ${post.title}
                        </h3>

                        <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-50">
                            <div class="flex items-center gap-1.5 truncate">
                                <img src="${post.authorAvatar}" class="w-4 h-4 rounded-full object-cover border" alt="Author">
                                <span class="truncate max-w-[90px] font-medium">${post.author}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button data-cy="add-to-cart" onclick="quickAddToCart(event, ${post.id})" class="px-2 py-1 bg-red-50 text-redAccent hover:bg-red-100 font-bold text-[10px] rounded-lg transition flex items-center gap-1 border border-red-200 shadow-sm" title="Add to Cart">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span>Add</span>
                                </button>
                                <button onclick="toggleLike(event, ${post.id})" class="flex items-center gap-1 ${post.isLiked ? 'text-redAccent' : 'text-gray-400'} hover:text-redAccent transition">
                                    <i class="${post.isLiked ? 'fa-solid text-redAccent' : 'fa-regular'} fa-heart text-xs"></i>
                                    <span class="text-[10px] font-semibold">${post.likes}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function handleSearch() { renderFeed(); }
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            activeCategory = 'All';
            renderCategories();
            renderFeed();
        }

        // ================= LIKE TOGGLES =================
        async function toggleLike(e, id) {
            e.stopPropagation();
            const post = allPosts.find(p => p.id === id);
            if (!post) return;

            post.isLiked = !post.isLiked;
            post.likes += post.isLiked ? 1 : -1;
            renderFeed();
            if (activePostId === id) updateModalState();

            const fd = new FormData();
            fd.append('product_id', id);
            try {
                const res = await fetch('api.php?action=toggle_like', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    post.likes = data.likes_count;
                    post.isLiked = data.isLiked;
                    renderFeed();
                    if (activePostId === id) updateModalState();
                }
            } catch (err) { console.error(err); }
        }

        // ================= POST DETAIL MODAL =================
        function openPostModal(id) {
            activePostId = id;
            updateModalState();
            document.getElementById('postModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePostModal() {
            document.getElementById('postModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            activePostId = null;
        }

        function updateModalState() {
            const post = allPosts.find(p => p.id === activePostId);
            if (!post) return;

            document.getElementById('modalImage').src = post.imageUrl;
            document.getElementById('modalAvatar').src = post.authorAvatar;
            document.getElementById('modalAuthor').innerText = post.author;
            document.getElementById('modalCategory').innerText = post.category;
            document.getElementById('modalTitle').innerText = post.title;
            document.getElementById('modalDesc').innerText = post.desc;
            document.getElementById('modalPrice').innerText = `₱${post.price.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('modalLikesCount').innerText = post.likes;

            const likeIcon = document.getElementById('modalLikeIcon');
            const likeBtn = document.getElementById('modalLikeBtn');
            if (post.isLiked) {
                likeIcon.className = "fa-solid fa-heart text-xl text-redAccent";
                likeBtn.classList.add('text-redAccent');
            } else {
                likeIcon.className = "fa-regular fa-heart text-xl";
                likeBtn.classList.remove('text-redAccent');
            }
        }

        function toggleModalLike() {
            if (activePostId) toggleLike({ stopPropagation: () => {} }, activePostId);
        }

        // ================= CART & CHECKOUT (REQUIRES LOGIN) =================
        function addToCartFromModal() {
            if (!activePostId) return;
            const post = allPosts.find(p => p.id === activePostId);
            const existing = cart.find(i => i.id === post.id);
            if (existing) existing.qty += 1;
            else cart.push({ ...post, qty: 1 });

            renderCart();
            closePostModal();
            openCartDrawer();
        }

        function buyNowFromModal() {
            // Cannot purchase without an account: must direct to create account or signup interface!
            if (!currentUser) {
                closePostModal();
                openAuthModal('register');
                const regStatus = document.getElementById('registerStatus');
                regStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                regStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please create an account or sign in to complete your purchase under CodeCraft Guarantee.`;
                regStatus.classList.remove('hidden');
                return;
            }
            addToCartFromModal();
            openCheckoutModal();
        }

        function openCartDrawer() { document.getElementById('cartDrawer').classList.remove('hidden'); }
        function closeCartDrawer() { document.getElementById('cartDrawer').classList.add('hidden'); }

        function changeCartQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartItemsList');
            const cartBadge = document.getElementById('cartBadge');
            const mobCartBadge = document.getElementById('mobCartBadge');

            const totalItems = cart.reduce((acc, i) => acc + i.qty, 0);
            const totalSum = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);

            if (totalItems > 0) {
                cartBadge.innerText = totalItems;
                cartBadge.classList.remove('hidden');
                mobCartBadge.innerText = totalItems;
                mobCartBadge.classList.remove('hidden');
            } else {
                cartBadge.classList.add('hidden');
                mobCartBadge.classList.add('hidden');
            }

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="py-16 text-center text-gray-400">
                        <i class="fa-solid fa-cart-shopping text-4xl mb-3 text-gray-200"></i>
                        <p class="text-xs font-semibold">Your shopping bag is empty.</p>
                    </div>
                `;
            } else {
                container.innerHTML = cart.map(item => `
                    <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <img src="${item.imageUrl}" class="w-14 h-14 rounded-lg object-cover border" alt="Thumb">
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 truncate">${item.title}</h4>
                            <p class="text-xs font-black text-redAccent mt-0.5">₱${item.price.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <button onclick="changeCartQty(${item.id}, -1)" class="w-6 h-6 bg-white border rounded text-xs font-bold hover:bg-gray-100">-</button>
                                <span class="text-xs font-bold px-1">${item.qty}</span>
                                <button onclick="changeCartQty(${item.id}, 1)" class="w-6 h-6 bg-white border rounded text-xs font-bold hover:bg-gray-100">+</button>
                            </div>
                        </div>
                        <button onclick="changeCartQty(${item.id}, -${item.qty})" class="text-gray-400 hover:text-redAccent text-sm p-1.5">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `).join('');
            }

            document.getElementById('cartSubtotal').innerText = `₱${totalSum.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('cartTotal').innerText = `₱${totalSum.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('checkoutTotalAmount').innerText = `₱${totalSum.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        }

        function quickAddToCart(e, id) {
            if (e && e.stopPropagation) e.stopPropagation();
            const post = allPosts.find(p => p.id === id);
            if (!post) return;
            const existing = cart.find(i => i.id === post.id);
            if (existing) existing.qty += 1;
            else cart.push({ ...post, qty: 1 });
            renderCart();
            dismissCheckoutError();
        }

        function showCheckoutError(msg) {
            const banner = document.getElementById('checkoutErrorBanner');
            const text = document.getElementById('checkoutErrorText');
            if (banner) {
                if (text) text.innerText = msg;
                else banner.innerText = msg;
                banner.classList.remove('hidden');
            }
        }

        function dismissCheckoutError() {
            const banner = document.getElementById('checkoutErrorBanner');
            if (banner) banner.classList.add('hidden');
        }

        async function proceedToCheckoutDirect() {
            if (!cart || cart.length === 0) {
                showCheckoutError('Your shopping cart is empty. Please select products to checkout.');
                return;
            }
            await executeCheckoutRequest();
        }

        async function executeCheckoutRequest() {
            try {
                const payload = {
                    items: cart.map(i => ({
                        productId: i.id,
                        product_id: i.id,
                        id: i.id,
                        name: i.title,
                        price: i.price,
                        quantity: i.qty,
                        qty: i.qty
                    }))
                };
                const res = await fetch('checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    showCheckoutError(errData.error || 'Payment gateway unavailable');
                    return;
                }

                const data = await res.json();
                if (data.checkout_url) {
                    if (window.__cypress_stop_redirect || window.__cypress_prevent_redirect) {
                        return;
                    }
                    window.location.href = data.checkout_url;
                } else if (data.success && data.order_code) {
                    window.location.href = `success.php?order=${encodeURIComponent(data.order_code)}`;
                }
            } catch (err) {
                showCheckoutError('Checkout service error: Payment unavailable');
            }
        }

        function openCheckoutModal() {
            if (cart.length === 0) { alert('Your bag is empty.'); return; }
            
            // Cannot purchase without an account: must direct to create account or signup interface!
            if (!currentUser) {
                closeCartDrawer();
                openAuthModal('register');
                const regStatus = document.getElementById('registerStatus');
                regStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                regStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please create an account or sign in to complete your checkout under CodeCraft Guarantee.`;
                regStatus.classList.remove('hidden');
                return;
            }

            closeCartDrawer();
            document.getElementById('checkoutModal').classList.remove('hidden');
        }
        function closeCheckoutModal() { document.getElementById('checkoutModal').classList.add('hidden'); }

        async function processCheckout(e) {
            e.preventDefault();
            if (!currentUser) {
                closeCheckoutModal();
                openAuthModal('register');
                return;
            }

            const statusDiv = document.getElementById('checkoutStatus');
            const submitBtn = document.getElementById('paySubmitBtn');

            statusDiv.classList.remove('hidden');
            statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Capturing payment in CodeCraft Guarantee Escrow...`;
            submitBtn.disabled = true;

            const payload = {
                items: cart,
                shipping_name: document.getElementById('shipName').value,
                shipping_phone: document.getElementById('shipPhone').value,
                shipping_address: document.getElementById('shipAddress').value,
                payment_method: document.getElementById('paymentMethod').value
            };

            try {
                const res = await fetch('api.php?action=process_checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    if (data.checkout_url) {
                        statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
                        statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Order <strong>${data.order_code}</strong> initialized! Redirecting to PayMongo interface...`;
                        cart = [];
                        renderCart();
                        setTimeout(() => {
                            window.location.href = data.checkout_url;
                        }, 800);
                        return;
                    }

                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-shield-check mr-1"></i> Order <strong>${data.order_code}</strong> secured under Escrow Guarantee!`;
                    cart = [];
                    renderCart();

                    setTimeout(() => {
                        closeCheckoutModal();
                        submitBtn.disabled = false;
                        statusDiv.classList.add('hidden');
                        switchTab('account');
                        switchAccountSubTab('orders');
                        loadUserOrders();
                    }, 1200);
                } else {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error || 'Checkout failed.'}`;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Server error.`;
                submitBtn.disabled = false;
            }
        }

        // ================= DEVICE UPLOAD & IMAGE HANDLING =================
        function switchUploadMode(mode) {
            currentUploadMode = mode;
            const deviceSec = document.getElementById('deviceUploadSection');
            const urlSec = document.getElementById('urlUploadSection');
            const btnDevice = document.getElementById('btn-mode-device');
            const btnUrl = document.getElementById('btn-mode-url');

            if (mode === 'device') {
                deviceSec.classList.remove('hidden');
                urlSec.classList.add('hidden');
                btnDevice.className = "px-3.5 py-1.5 rounded-lg bg-redAccent text-white shadow-sm flex items-center gap-1.5 font-bold";
                btnUrl.className = "px-3.5 py-1.5 rounded-lg bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 flex items-center gap-1.5 font-medium";
            } else {
                deviceSec.classList.add('hidden');
                urlSec.classList.remove('hidden');
                btnDevice.className = "px-3.5 py-1.5 rounded-lg bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 flex items-center gap-1.5 font-medium";
                btnUrl.className = "px-3.5 py-1.5 rounded-lg bg-redAccent text-white shadow-sm flex items-center gap-1.5 font-bold";
            }
        }

        async function handleDeviceFileUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!currentUser) {
                openAuthModal('login');
                const loginStatus = document.getElementById('loginStatus');
                loginStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                loginStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please sign in or register to upload craft photos.`;
                loginStatus.classList.remove('hidden');
                pendingRedirectTab = 'create';
                e.target.value = '';
                return;
            }

            const statusText = document.getElementById('uploadStatusText');
            statusText.classList.remove('hidden');
            statusText.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Uploading ${file.name} to server...`;

            const fd = new FormData();
            fd.append('image', file);

            try {
                const res = await fetch('api.php?action=upload_image', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    currentUploadedUrl = data.url;
                    statusText.className = "text-xs text-center font-bold text-emerald-600";
                    statusText.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> Uploaded! Ready to publish.`;
                    
                    document.getElementById('imagePreviewBox').classList.remove('hidden');
                    document.getElementById('previewImgElement').src = data.url;
                    document.getElementById('previewImgUrlText').innerText = data.url;
                } else {
                    statusText.className = "text-xs text-center font-bold text-red-600";
                    statusText.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                }
            } catch (err) {
                statusText.className = "text-xs text-center font-bold text-red-600";
                statusText.innerHTML = `Upload failed: network error.`;
            }
        }

        function setPostImagePreset(url) {
            document.getElementById('postImageUrl').value = url;
            currentUploadedUrl = url;
            document.getElementById('imagePreviewBox').classList.remove('hidden');
            document.getElementById('previewImgElement').src = url;
            document.getElementById('previewImgUrlText').innerText = url;
        }

        function toggleShippingAssistantGuide() {
            const guide = document.getElementById('shippingAssistantGuide');
            if (guide) guide.classList.toggle('hidden');
        }

        // ================= LIVE FEE ESTIMATOR (SHOPEE STYLE & SHIPPING ASSISTANT) =================
        async function updateLiveFeeEstimator() {
            const price = parseFloat(document.getElementById('postPrice').value) || 0;
            const category = document.getElementById('postCategory').value;
            const optIn = document.getElementById('optInPrograms')?.checked ? 1 : 0;
            const sellerShipping = document.getElementById('optSellerShipping')?.checked ? 1 : 0;
            const optInFSS = document.getElementById('optInFSS')?.checked ? 1 : 0;

            if (price <= 0) {
                document.getElementById('feeValComm').innerText = "₱0.00";
                document.getElementById('feeValTrans').innerText = "₱0.00";
                document.getElementById('feeValShip').innerText = "₱0.00";
                document.getElementById('feeValGrowth').innerText = "₱0.00";
                document.getElementById('feeValProgram').innerText = "₱0.00";
                document.getElementById('feeValTotal').innerText = "₱0.00";
                document.getElementById('feeValNet').innerText = "₱0.00";
                return;
            }

            try {
                const res = await fetch(`api.php?action=get_fee_preview&price=${price}&category=${encodeURIComponent(category)}&opt_in_marketing=${optIn}&seller_shipping=${sellerShipping}&opt_in_fss=${optInFSS}`);
                const data = await res.json();
                if (data.success) {
                    const b = data.breakdown;
                    document.getElementById('feeRateComm').innerText = b.commission_rate;
                    document.getElementById('feeValComm').innerText = `₱${(b.commission_fee / 100).toFixed(2)}`;
                    document.getElementById('feeValTrans').innerText = `₱${(b.transaction_fee / 100).toFixed(2)}`;
                    
                    const shipLabel = document.getElementById('feeLabelShip');
                    if (shipLabel) {
                        shipLabel.innerText = sellerShipping ? 'Platform Shipping (5.6%)' : 'Shipping (Buyer Pays)';
                    }
                    document.getElementById('feeValShip').innerText = sellerShipping ? `₱${(b.shipping_fee / 100).toFixed(2)}` : '₱0.00 (Buyer)';
                    
                    document.getElementById('feeValGrowth').innerText = `₱${(b.growth_support_fee / 100).toFixed(2)}`;
                    
                    const progTotal = (b.program_fee || 0) + (b.fss_fee || 0);
                    document.getElementById('feeValProgram').innerText = `₱${(progTotal / 100).toFixed(2)}`;
                    
                    document.getElementById('feeValTotal').innerText = `₱${(b.total_deductions / 100).toFixed(2)}`;
                    document.getElementById('feeValNet').innerText = `₱${(b.net_amount / 100).toFixed(2)}`;
                    document.getElementById('estRateBadge').innerText = `${b.effective_rate_percent} Total Fees`;
                }
            } catch (err) { console.error(err); }
        }

        async function handleCreatePost(e) {
            e.preventDefault();
            if (!currentUser) {
                openAuthModal('login');
                const loginStatus = document.getElementById('loginStatus');
                loginStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                loginStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please sign in or register to add and publish crafts to CodeCraft.`;
                loginStatus.classList.remove('hidden');
                pendingRedirectTab = 'create';
                return;
            }

            const title = document.getElementById('postTitle').value;
            const category = document.getElementById('postCategory').value;
            const price = document.getElementById('postPrice').value;
            const description = document.getElementById('postDescription').value;
            let imageUrl = currentUploadedUrl;

            if (!imageUrl && currentUploadMode === 'url') {
                imageUrl = document.getElementById('postImageUrl').value;
            }

            if (!imageUrl) {
                alert('Please upload a photo from your device or paste an Image URL.');
                return;
            }

            const statusDiv = document.getElementById('createPostStatus');
            const submitBtn = document.getElementById('submitPostBtn');
            statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Publishing craft to CodeCraft catalog...`;
            statusDiv.classList.remove('hidden');
            submitBtn.disabled = true;

            const fd = new FormData();
            fd.append('title', title);
            fd.append('category', category);
            fd.append('price', price);
            fd.append('imageUrl', imageUrl);
            fd.append('description', description);

            try {
                const res = await fetch('api.php?action=create_product', { method: 'POST', body: fd });
                if (res.status === 401) {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Please sign in or register to add crafts to CodeCraft.`;
                    submitBtn.disabled = false;
                    openAuthModal('login');
                    return;
                }
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> Craft published successfully!`;
                    document.getElementById('createPostForm').reset();
                    currentUploadedUrl = '';
                    document.getElementById('imagePreviewBox').classList.add('hidden');
                    await fetchProducts();

                    setTimeout(() => {
                        statusDiv.classList.add('hidden');
                        submitBtn.disabled = false;
                        switchTab('feed');
                    }, 900);
                } else {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Server error.`;
                submitBtn.disabled = false;
            }
        }

        // ================= MY ACCOUNT INTERFACE LOGIC =================
        function switchAccountSubTab(subTab) {
            if (!currentUser) {
                openAuthModal('register');
                const regStatus = document.getElementById('registerStatus');
                regStatus.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-amber-50 text-amber-800 border border-amber-200";
                regStatus.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> You must be signed in or registered to access ${subTab === 'wallet' ? 'Seller Wallet & Escrow' : 'your Account'}.`;
                regStatus.classList.remove('hidden');
                return;
            }

            document.querySelectorAll('.acc-view-section').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById(`acc-view-${subTab}`);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('[id^="acc-tab-"]').forEach(btn => {
                btn.className = "w-full text-left px-3.5 py-2.5 rounded-xl text-gray-700 hover:bg-gray-100 flex items-center justify-between font-semibold";
            });
            const activeBtn = document.getElementById(`acc-tab-${subTab}`);
            if (activeBtn) activeBtn.className = "w-full text-left px-3.5 py-2.5 rounded-xl bg-redLight text-redAccent font-bold flex items-center justify-between shadow-sm";

            if (subTab === 'orders') loadUserOrders();
            else if (subTab === 'notifications') loadNotifications();
            else if (subTab === 'wallet') loadSellerIncomeWallet();
            else if (subTab === 'my-crafts') renderAccountCraftsGrid();
            else if (subTab === 'settings') populateSettingsForm();
        }

        function loadAccountOverview() {
            if (!currentUser) return;
            document.getElementById('accSidebarName').innerText = currentUser.display_name;
            document.getElementById('accSidebarUsername').innerText = `@${currentUser.username}`;
            document.getElementById('accSidebarRole').innerText = currentUser.role === 'admin' ? 'System Admin' : 'Artisan Creator';
            if (currentUser.avatar_url) document.getElementById('accSidebarAvatar').src = currentUser.avatar_url;

            document.getElementById('ovDisplayName').innerText = currentUser.display_name;
            document.getElementById('ovUsername').innerText = `@${currentUser.username}`;
            document.getElementById('ovEmail').innerText = currentUser.email;
            document.getElementById('ovPhone').innerText = currentUser.phone || '+63 917 123 4567';
            document.getElementById('ovLocation').innerText = currentUser.location || 'Manila, Philippines';
            document.getElementById('ovBio').innerText = currentUser.bio || 'Curating handmade crafts on CodeCraft.';
            document.getElementById('ovRole').innerText = currentUser.role === 'admin' ? 'Admin Manager' : 'Verified Seller';

            loadSellerIncomeWallet();
            loadUserOrders();
            loadNotifications(true);
        }

        // ================= NOTIFICATION CENTER LOGIC =================
        let allNotifications = [];
        let currentNotifFilter = 'all';

        async function loadNotifications(silent = false) {
            if (!currentUser) return;
            try {
                const res = await fetch('api.php?action=get_notifications');
                const data = await res.json();
                if (data.success) {
                    allNotifications = data.notifications || [];
                    const unreadCount = data.unread_count || 0;

                    const notifBadge = document.getElementById('notifBadge');
                    const mobNotifBadge = document.getElementById('mobNotifBadge');
                    const badgeNotifCount = document.getElementById('badgeNotifCount');
                    const notifDropdownCount = document.getElementById('notifDropdownCount');
                    const accNotifBadgePill = document.getElementById('accNotifBadgePill');

                    if (notifBadge) {
                        notifBadge.innerText = unreadCount;
                        notifBadge.classList.toggle('hidden', unreadCount === 0);
                    }
                    if (mobNotifBadge) {
                        mobNotifBadge.innerText = unreadCount;
                        mobNotifBadge.classList.toggle('hidden', unreadCount === 0);
                    }
                    if (badgeNotifCount) {
                        badgeNotifCount.innerText = unreadCount;
                        badgeNotifCount.classList.toggle('hidden', unreadCount === 0);
                    }
                    if (notifDropdownCount) {
                        notifDropdownCount.innerText = `${unreadCount} New`;
                    }
                    if (accNotifBadgePill) {
                        accNotifBadgePill.innerText = `${unreadCount} Unread`;
                    }

                    renderNotifDropdownList();
                    renderAccountNotifsList();
                }
            } catch (err) {
                if (!silent) console.error("Error loading notifications:", err);
            }
        }

        function toggleNotifDropdown(forceState = null) {
            const dropdown = document.getElementById('notifDropdown');
            if (!dropdown) return;
            if (!currentUser) {
                openAuthModal('login');
                return;
            }
            const isHidden = dropdown.classList.contains('hidden');
            const shouldOpen = (forceState !== null) ? forceState : isHidden;
            if (shouldOpen) {
                dropdown.classList.remove('hidden');
                loadNotifications();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        function getNotifIconInfo(type) {
            switch(type) {
                case 'order':
                    return { icon: 'fa-box', bg: 'bg-blue-100 text-blue-600', label: 'Order Update' };
                case 'escrow':
                    return { icon: 'fa-shield-halved', bg: 'bg-emerald-100 text-emerald-600', label: 'Escrow & Wallet' };
                case 'shipping':
                    return { icon: 'fa-truck-fast', bg: 'bg-amber-100 text-amber-600', label: 'Shipping' };
                case 'promo':
                    return { icon: 'fa-tags', bg: 'bg-rose-100 text-rose-600', label: 'Promotion' };
                default:
                    return { icon: 'fa-bell', bg: 'bg-purple-100 text-purple-600', label: 'Platform Alert' };
            }
        }

        function renderNotifDropdownList() {
            const container = document.getElementById('notifDropdownList');
            if (!container) return;

            if (allNotifications.length === 0) {
                container.innerHTML = `
                    <div class="p-6 text-center text-gray-400">
                        <i class="fa-regular fa-bell-slash text-2xl mb-2 text-gray-300 block"></i>
                        <p class="font-bold text-gray-600">No notifications yet</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Alerts for orders and payouts will appear here.</p>
                    </div>
                `;
                return;
            }

            const recent = allNotifications.slice(0, 5);
            container.innerHTML = recent.map(n => {
                const iconInfo = getNotifIconInfo(n.type);
                return `
                    <div onclick="handleNotifClick(${n.id}, '${n.link || ''}')" class="p-3 hover:bg-gray-50 flex items-start gap-3 cursor-pointer transition ${!n.is_read ? 'bg-redLight/20 font-medium' : ''}">
                        <div class="w-8 h-8 rounded-full ${iconInfo.bg} flex items-center justify-center shrink-0 shadow-xs text-xs mt-0.5">
                            <i class="fa-solid ${iconInfo.icon}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <h5 class="text-xs font-bold text-gray-900 truncate">${n.title}</h5>
                                ${!n.is_read ? '<span class="w-2 h-2 rounded-full bg-redAccent shrink-0"></span>' : ''}
                            </div>
                            <p class="text-[11px] text-gray-600 line-clamp-2 mt-0.5">${n.message}</p>
                            <span class="text-[10px] text-gray-400 mt-1 block">${n.time_ago || 'Recent'}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterNotifs(type) {
            currentNotifFilter = type;
            ['all', 'unread', 'order', 'escrow'].forEach(t => {
                const btn = document.getElementById(`notif-flt-${t}`);
                if (btn) {
                    if (t === type) {
                        btn.className = "px-3 py-1 rounded-full bg-redLight text-redAccent font-bold transition shadow-sm cursor-pointer";
                    } else {
                        btn.className = "px-3 py-1 rounded-full hover:bg-gray-100 transition cursor-pointer";
                    }
                }
            });
            renderAccountNotifsList();
        }

        function renderAccountNotifsList() {
            const container = document.getElementById('accNotifListContainer');
            if (!container) return;

            let filtered = allNotifications;
            if (currentNotifFilter === 'unread') {
                filtered = allNotifications.filter(n => !n.is_read);
            } else if (currentNotifFilter === 'order') {
                filtered = allNotifications.filter(n => n.type === 'order' || n.type === 'shipping');
            } else if (currentNotifFilter === 'escrow') {
                filtered = allNotifications.filter(n => n.type === 'escrow');
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="py-12 text-center">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-xl">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <h4 class="font-bold text-gray-800 text-sm">No Notifications Found</h4>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto mt-1">${currentNotifFilter === 'unread' ? "You're all caught up! No unread notifications." : "No notifications found in this category."}</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = filtered.map(n => {
                const iconInfo = getNotifIconInfo(n.type);
                return `
                    <div class="py-3.5 flex items-start justify-between gap-3 ${!n.is_read ? 'bg-redLight/15 -mx-2 px-2 rounded-xl' : ''}">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-2xl ${iconInfo.bg} flex items-center justify-center shrink-0 shadow-sm text-sm mt-0.5">
                                <i class="fa-solid ${iconInfo.icon}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-xs font-bold text-gray-900">${n.title}</h4>
                                    <span class="text-[9px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-full ${iconInfo.bg}">${iconInfo.label}</span>
                                    ${!n.is_read ? '<span class="text-[9px] bg-redAccent text-white px-1.5 py-0.5 rounded-full font-bold">NEW</span>' : ''}
                                </div>
                                <p class="text-xs text-gray-600 mt-1 leading-relaxed">${n.message}</p>
                                <div class="flex items-center gap-3 text-[11px] text-gray-400 mt-2">
                                    <span><i class="fa-regular fa-clock mr-1"></i>${n.time_ago || 'Recent'}</span>
                                    <span class="text-gray-300">•</span>
                                    <span>${n.created_at || ''}</span>
                                    ${n.link ? `
                                        <button onclick="handleNotifClick(${n.id}, '${n.link}')" class="text-redAccent hover:underline font-bold text-xs flex items-center gap-1 ml-2 cursor-pointer">
                                            <span>View Details</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 pt-1">
                            ${!n.is_read ? `
                                <button onclick="handleMarkNotifRead(${n.id})" class="px-2.5 py-1 text-[11px] bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-semibold rounded-lg shadow-2xs hover:bg-gray-50 transition cursor-pointer" title="Mark as read">
                                    <i class="fa-solid fa-check text-emerald-600 mr-1"></i>Mark Read
                                </button>
                            ` : ''}
                            <button onclick="handleDeleteNotif(${n.id})" class="p-1.5 text-gray-400 hover:text-rose-600 transition rounded-lg hover:bg-gray-100 cursor-pointer" title="Delete notification">
                                <i class="fa-regular fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function handleMarkNotifRead(id) {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('api.php?action=mark_notification_read', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    loadNotifications();
                }
            } catch (err) { console.error(err); }
        }

        async function handleMarkAllNotifsRead() {
            try {
                const res = await fetch('api.php?action=mark_all_notifications_read', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    loadNotifications();
                }
            } catch (err) { console.error(err); }
        }

        async function handleDeleteNotif(id) {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('api.php?action=delete_notification', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    loadNotifications();
                }
            } catch (err) { console.error(err); }
        }

        function handleNotifClick(notifId, link) {
            handleMarkNotifRead(notifId);
            toggleNotifDropdown(false);
            if (link) {
                if (link.startsWith('#order-')) {
                    switchTab('account');
                    switchAccountSubTab('orders');
                    loadUserOrders();
                    setTimeout(() => {
                        const targetOrder = document.querySelector(link);
                        if (targetOrder) {
                            targetOrder.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetOrder.classList.add('ring-2', 'ring-redAccent', 'rounded-xl', 'p-2');
                            setTimeout(() => targetOrder.classList.remove('ring-2', 'ring-redAccent'), 3000);
                        }
                    }, 500);
                } else if (link.startsWith('#wallet')) {
                    switchTab('account');
                    switchAccountSubTab('wallet');
                } else {
                    window.location.hash = link;
                }
            } else {
                switchTab('account');
                switchAccountSubTab('notifications');
            }
        }

        // ================= PURCHASES & ORDER TRACKING =================
        async function loadUserOrders() {
            const container = document.getElementById('accOrdersContainer');
            if (!container) return;
            container.innerHTML = `<div class="py-6 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1 text-redAccent"></i> Loading orders from MySQL...</div>`;

            try {
                const res = await fetch('api.php?action=get_order_history');
                const data = await res.json();
                if (data.success) {
                    const orders = data.orders || [];
                    const statElem = document.getElementById('ovStatOrders');
                    const badgeElem = document.getElementById('badgeOrderCount');
                    if (statElem) statElem.innerText = orders.length;
                    if (badgeElem) badgeElem.innerText = orders.length;

                    if (orders.length === 0) {
                        container.innerHTML = `
                            <div class="py-12 text-center">
                                <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-redLight text-redAccent flex items-center justify-center text-xl shadow-inner">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 text-sm">No Orders Found</h4>
                                <p class="text-xs text-gray-500 max-w-sm mx-auto mt-1">You haven't placed any craft purchases or received customer orders yet.</p>
                                <button onclick="switchTab('feed')" class="mt-4 px-4 py-2 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-compass"></i> Discover Artisan Crafts
                                </button>
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = orders.map(o => {
                        const total = Number(o.total || 0);
                        const status = (o.status || 'pending').toLowerCase();
                        
                        let statusColor = "bg-amber-100 text-amber-800 border-amber-200";
                        let statusLabel = status.replace(/_/g, ' ');
                        let step = 1; // 1: Placed, 2: Shipped, 3: Delivered, 4: Completed

                        if (status === 'completed') {
                            statusColor = "bg-emerald-100 text-emerald-800 border-emerald-200";
                            statusLabel = "Completed (Escrow Released)";
                            step = 4;
                        } else if (status === 'delivered') {
                            statusColor = "bg-blue-100 text-blue-800 border-blue-200";
                            statusLabel = "Delivered (7-Day Guarantee)";
                            step = 3;
                        } else if (status === 'shipped') {
                            statusColor = "bg-purple-100 text-purple-800 border-purple-200";
                            statusLabel = "In Transit / Shipped";
                            step = 2;
                        } else if (status === 'paid') {
                            statusColor = "bg-amber-100 text-amber-800 border-amber-200";
                            statusLabel = "Paid (Escrow Secured)";
                            step = 1;
                        } else if (status === 'refund_requested') {
                            statusColor = "bg-rose-100 text-rose-800 border-rose-200";
                            statusLabel = "Dispute / Refund Requested";
                        } else if (status === 'refunded') {
                            statusColor = "bg-gray-100 text-gray-700 border-gray-300";
                            statusLabel = "Refunded to Buyer";
                        } else if (status === 'cancelled') {
                            statusColor = "bg-red-50 text-red-700 border-red-200";
                            statusLabel = "Cancelled";
                        }

                        const canConfirmReceived = (status === 'paid' || status === 'shipped' || status === 'delivered');
                        const canRequestRefund = (status === 'paid' || status === 'shipped' || status === 'delivered');

                        const roleBadge = o.is_buyer 
                            ? `<span class="bg-gray-100 text-gray-700 text-[10px] px-2 py-0.5 rounded-full font-bold flex items-center gap-1"><i class="fa-solid fa-bag-shopping text-redAccent"></i> Your Purchase</span>`
                            : (o.is_seller ? `<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] px-2 py-0.5 rounded-full font-bold flex items-center gap-1"><i class="fa-solid fa-store"></i> Artisan Sale</span>` : '');

                        const items = Array.isArray(o.items) ? o.items : [];

                        return `
                            <div class="py-4 space-y-3" id="order-${o.id}">
                                <div class="flex flex-wrap justify-between items-center gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-extrabold text-gray-900 font-mono text-sm tracking-tight">#${o.id}</span>
                                        ${roleBadge}
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border ${statusColor} uppercase tracking-wider">
                                            ● ${statusLabel}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400 block sm:inline mr-1">Total:</span>
                                        <span class="text-sm font-black text-redAccent">₱${total.toFixed(2)}</span>
                                    </div>
                                </div>

                                <!-- Visual Order Tracking Stepper -->
                                <div class="bg-gray-50/80 p-3 rounded-xl border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                                        <span><i class="fa-solid fa-truck-ramp-box mr-1"></i> Order Tracking & Escrow Lifecycle</span>
                                        <span class="text-emerald-700 font-bold"><i class="fa-solid fa-shield-halved mr-1"></i>CodeCraft Guarantee Active</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 text-center text-[10px]">
                                        <div class="flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold mb-1 ${step >= 1 ? 'bg-redAccent text-white shadow-sm' : 'bg-gray-200 text-gray-500'}">
                                                <i class="fa-solid fa-receipt text-[10px]"></i>
                                            </div>
                                            <span class="font-bold ${step >= 1 ? 'text-gray-900' : 'text-gray-400'}">1. Placed</span>
                                            <span class="text-[9px] text-gray-400">Escrow Held</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold mb-1 ${step >= 2 ? 'bg-redAccent text-white shadow-sm' : 'bg-gray-200 text-gray-500'}">
                                                <i class="fa-solid fa-box text-[10px]"></i>
                                            </div>
                                            <span class="font-bold ${step >= 2 ? 'text-gray-900' : 'text-gray-400'}">2. Dispatched</span>
                                            <span class="text-[9px] text-gray-400">In Transit</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold mb-1 ${step >= 3 ? 'bg-redAccent text-white shadow-sm' : 'bg-gray-200 text-gray-500'}">
                                                <i class="fa-solid fa-house-chimney text-[10px]"></i>
                                            </div>
                                            <span class="font-bold ${step >= 3 ? 'text-gray-900' : 'text-gray-400'}">3. Delivered</span>
                                            <span class="text-[9px] text-gray-400">Inspection</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold mb-1 ${step >= 4 ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-200 text-gray-500'}">
                                                <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            </div>
                                            <span class="font-bold ${step >= 4 ? 'text-emerald-700' : 'text-gray-400'}">4. Released</span>
                                            <span class="text-[9px] text-gray-400">Seller Paid</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-[11px] text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                                    <span>Date: <strong>${o.date || 'N/A'}</strong></span>
                                    <span>Recipient: <strong>${o.shipping_name || 'Customer'}</strong></span>
                                    <span>Payment: <strong class="uppercase">${o.payment_method || 'GCASH'}</strong></span>
                                    ${o.guarantee_expires_at ? `<span class="text-amber-700 font-semibold"><i class="fa-solid fa-clock"></i> Guarantee expires: ${o.guarantee_expires_at}</span>` : ''}
                                </div>

                                <div class="flex gap-2 overflow-x-auto py-1">
                                    ${items.map(it => `
                                        <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-xl border border-gray-100 shrink-0 min-w-[200px]">
                                            <img src="${it.image_url || 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=100'}" 
                                                 class="w-10 h-10 rounded-lg object-cover border border-gray-200" 
                                                 onerror="this.src='https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=100'"
                                                 title="${it.name}">
                                            <div class="text-[11px] overflow-hidden">
                                                <p class="font-bold truncate max-w-[140px] text-gray-800">${it.name}</p>
                                                <p class="text-gray-400 text-[10px]">Qty: ${it.quantity} • ₱${Number(it.price || 0).toFixed(2)}</p>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>

                                <div class="pt-1 flex flex-wrap items-center justify-between gap-2 border-t border-gray-50">
                                    <div class="text-[11px] text-gray-400 flex items-center gap-1">
                                        <i class="fa-solid fa-lock text-emerald-600 text-[10px]"></i>
                                        <span>Payment protected by PayMongo Escrow</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        ${canConfirmReceived ? `
                                            <button onclick="handleConfirmReceived(${o.db_id})" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-full shadow transition flex items-center gap-1 cursor-pointer">
                                                <i class="fa-solid fa-circle-check"></i> Order Received (Release Escrow)
                                            </button>
                                        ` : ''}
                                        ${canRequestRefund ? `
                                            <button onclick="handleRequestRefund(${o.db_id})" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-full border border-rose-200 transition cursor-pointer">
                                                Request Return / Refund
                                            </button>
                                        ` : ''}
                                        ${status === 'refund_requested' && currentUser && currentUser.role === 'admin' ? `
                                            <button onclick="handleResolveRefund(${o.db_id}, 'refund_buyer')" class="px-3 py-1 bg-red-600 text-white rounded text-xs font-bold shadow cursor-pointer">Admin: Grant Refund</button>
                                            <button onclick="handleResolveRefund(${o.db_id}, 'release_seller')" class="px-3 py-1 bg-emerald-600 text-white rounded text-xs font-bold shadow cursor-pointer">Admin: Release to Seller</button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = `
                        <div class="py-8 text-center text-xs text-rose-600 bg-rose-50 rounded-xl border border-rose-200 p-4">
                            <i class="fa-solid fa-circle-exclamation text-base mb-1 block"></i>
                            <p class="font-bold">${data.error || 'Failed to retrieve order history.'}</p>
                            <button onclick="loadUserOrders()" class="mt-2 px-3 py-1 bg-white text-rose-700 border border-rose-300 rounded-full font-bold shadow-sm hover:bg-rose-100 transition cursor-pointer">
                                <i class="fa-solid fa-rotate mr-1"></i> Try Again
                            </button>
                        </div>
                    `;
                }
            } catch (err) {
                console.error("loadUserOrders error:", err);
                container.innerHTML = `
                    <div class="py-8 text-center text-xs text-red-500 bg-red-50 rounded-xl border border-red-200 p-4">
                        <i class="fa-solid fa-triangle-exclamation text-base mb-1 block"></i>
                        <p class="font-bold">Failed to connect to MySQL database.</p>
                        <button onclick="loadUserOrders()" class="mt-2 px-3 py-1 bg-white text-red-700 border border-red-300 rounded-full font-bold shadow-sm hover:bg-red-100 transition cursor-pointer">
                            <i class="fa-solid fa-rotate mr-1"></i> Retry
                        </button>
                    </div>
                `;
            }
        }

        async function handleConfirmReceived(orderId) {
            if (!confirm('Confirm that you have received this order in good condition? This will release funds from Escrow to the Seller Wallet.')) return;
            const fd = new FormData();
            fd.append('order_id', orderId);
            try {
                const res = await fetch('api.php?action=confirm_received', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert('Order confirmed! Escrow funds released to seller.');
                    loadUserOrders();
                    loadSellerIncomeWallet();
                    loadNotifications();
                } else {
                    alert(data.error || 'Could not complete release.');
                }
            } catch (err) { alert('Network error.'); }
        }

        async function handleRequestRefund(orderId) {
            const reason = prompt('Please enter the reason for return/refund (e.g. item damaged, not as described):');
            if (!reason) return;

            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('reason', reason);

            try {
                const res = await fetch('api.php?action=request_refund', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert('Dispute opened. Funds are now frozen in escrow pending resolution.');
                    loadUserOrders();
                    loadNotifications();
                } else {
                    alert(data.error || 'Failed to request refund.');
                }
            } catch (err) { alert('Network error.'); }
        }

        async function handleResolveRefund(orderId, decision) {
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('decision', decision);
            fd.append('reason', 'Admin dispute decision');

            try {
                const res = await fetch('api.php?action=resolve_refund', { method: 'POST', body: fd });
                const data = await res.json();
                alert(data.message || 'Dispute resolved.');
                loadUserOrders();
                loadSellerIncomeWallet();
                loadNotifications();
            } catch (err) { alert('Error resolving dispute.'); }
        }

        // ================= SELLER WALLET, ESCROW & WITHDRAWALS =================
        async function loadSellerIncomeWallet() {
            if (!currentUser) return;
            try {
                const res = await fetch('api.php?action=get_seller_income');
                const data = await res.json();
                if (data.success) {
                    document.getElementById('walletAvailableBalance').innerText = `₱${data.available_balance.toFixed(2)}`;
                    document.getElementById('walletPendingBalance').innerText = `₱${data.pending_balance.toFixed(2)}`;
                    document.getElementById('ovStatWallet').innerText = `₱${data.available_balance.toFixed(2)}`;
                    document.getElementById('ovStatEscrow').innerText = `₱${data.pending_balance.toFixed(2)}`;
                    document.getElementById('withMaxAvailable').innerText = `₱${data.available_balance.toFixed(2)}`;

                    // Bank accounts list
                    const bList = document.getElementById('bankAccountsList');
                    const bSelect = document.getElementById('withBankSelect');

                    if (data.bank_accounts.length === 0) {
                        bList.innerHTML = `<p class="col-span-full text-xs text-gray-400 py-3">No bank accounts linked yet.</p>`;
                        bSelect.innerHTML = `<option value="">No linked bank accounts</option>`;
                    } else {
                        bList.innerHTML = data.bank_accounts.map(b => `
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-white border flex items-center justify-center text-sm ${b.is_partner_bank ? 'text-emerald-600' : 'text-gray-700'}">
                                        <i class="fa-solid fa-building-columns"></i>
                                    </div>
                                    <div class="text-xs">
                                        <p class="font-bold text-gray-900">${b.bank_name}</p>
                                        <p class="text-gray-400 font-mono">${b.account_number_masked} • ${b.account_name}</p>
                                    </div>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold ${b.is_partner_bank ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700'}">
                                    ${b.is_partner_bank ? 'Partner (0 Fee)' : 'Standard'}
                                </span>
                            </div>
                        `).join('');

                        bSelect.innerHTML = data.bank_accounts.map(b => `
                            <option value="${b.id}">${b.bank_name} (${b.account_number_masked}) - ${b.is_partner_bank ? 'Free Instant' : 'Standard'}</option>
                        `).join('');
                    }

                    // Ledger table
                    const ledgerBody = document.getElementById('escrowLedgerBody');
                    if (data.ledger.length === 0) {
                        ledgerBody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-gray-400">No ledger transactions yet.</td></tr>`;
                    } else {
                        ledgerBody.innerHTML = data.ledger.map(l => {
                            let badge = "bg-blue-100 text-blue-800";
                            if (l.type === 'release_to_seller') badge = "bg-emerald-100 text-emerald-800";
                            else if (l.type === 'withdrawal') badge = "bg-amber-100 text-amber-800";
                            else if (l.type === 'refund') badge = "bg-rose-100 text-rose-800";

                            return `
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2.5 text-gray-500 whitespace-nowrap">${l.date}</td>
                                    <td class="p-2.5 whitespace-nowrap"><span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase ${badge}">${l.type.replace(/_/g, ' ')}</span></td>
                                    <td class="p-2.5 text-gray-800 max-w-xs truncate">${l.description}</td>
                                    <td class="p-2.5 text-right font-bold ${l.type === 'withdrawal' || l.type === 'refund' ? 'text-rose-600' : 'text-emerald-600'}">₱${l.amount.toFixed(2)}</td>
                                    <td class="p-2.5 text-right font-mono font-bold text-gray-700">₱${l.balance_after.toFixed(2)}</td>
                                </tr>
                            `;
                        }).join('');
                    }
                }
            } catch (err) { console.error(err); }
        }

        function openWithdrawModal() { document.getElementById('withdrawModal').classList.remove('hidden'); }
        function closeWithdrawModal() { document.getElementById('withdrawModal').classList.add('hidden'); }

        async function handleWithdrawalSubmit(e) {
            e.preventDefault();
            const bankId = document.getElementById('withBankSelect').value;
            const amount = document.getElementById('withAmount').value;
            const statusDiv = document.getElementById('withdrawStatus');
            const btn = document.getElementById('btnSubmitWithdraw');

            if (!bankId) { alert('Please select a linked bank account.'); return; }

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Processing withdrawal request...`;
            statusDiv.classList.remove('hidden');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('bank_account_id', bankId);
            fd.append('amount', amount);

            try {
                const res = await fetch('api.php?action=request_withdrawal', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    setTimeout(() => {
                        closeWithdrawModal();
                        btn.disabled = false;
                        statusDiv.classList.add('hidden');
                        loadSellerIncomeWallet();
                    }, 1200);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    btn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Server error.`;
                btn.disabled = false;
            }
        }

        function openAddBankModal() { document.getElementById('addBankModal').classList.remove('hidden'); }
        function closeAddBankModal() { document.getElementById('addBankModal').classList.add('hidden'); }

        async function handleAddBankSubmit(e) {
            e.preventDefault();
            const bankName = document.getElementById('newBankName').value;
            const accName = document.getElementById('newAccName').value;
            const accNum = document.getElementById('newAccNumber').value;
            const isPartner = bankName.includes('Partner') ? 1 : 0;
            const statusDiv = document.getElementById('addBankStatus');
            const btn = document.getElementById('btnSubmitAddBank');

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Verifying account...`;
            statusDiv.classList.remove('hidden');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('bank_name', bankName);
            fd.append('account_name', accName);
            fd.append('account_number', accNum);
            fd.append('is_partner_bank', isPartner);

            try {
                const res = await fetch('api.php?action=add_bank_account', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    setTimeout(() => {
                        closeAddBankModal();
                        btn.disabled = false;
                        statusDiv.classList.add('hidden');
                        loadSellerIncomeWallet();
                    }, 900);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    btn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Network error.`;
                btn.disabled = false;
            }
        }

        // ================= EDIT INFO & PASSWORD SETTINGS =================
        function populateSettingsForm() {
            if (!currentUser) return;
            document.getElementById('setDisplayName').value = currentUser.display_name;
            document.getElementById('setEmail').value = currentUser.email;
            document.getElementById('setPhone').value = currentUser.phone || '';
            document.getElementById('setLocation').value = currentUser.location || 'Manila, Philippines';
            document.getElementById('setAvatarUrl').value = currentUser.avatar_url || '';
            document.getElementById('setBio').value = currentUser.bio || '';
            document.getElementById('setOptInMarketing').checked = (currentUser.opt_in_marketing == 1);
        }

        async function handleAccountInfoUpdate(e) {
            e.preventDefault();
            const statusDiv = document.getElementById('accountUpdateStatus');
            const btn = document.getElementById('saveAccountBtn');

            statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Updating account info in MySQL...`;
            statusDiv.classList.remove('hidden');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('display_name', document.getElementById('setDisplayName').value);
            fd.append('email', document.getElementById('setEmail').value);
            fd.append('phone', document.getElementById('setPhone').value);
            fd.append('location', document.getElementById('setLocation').value);
            fd.append('avatar_url', document.getElementById('setAvatarUrl').value);
            fd.append('bio', document.getElementById('setBio').value);
            fd.append('opt_in_marketing', document.getElementById('setOptInMarketing').checked ? 1 : 0);

            try {
                const res = await fetch('api.php?action=update_account_info', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    currentUser = { ...currentUser, ...data.user };
                    updateAuthUI();
                    loadAccountOverview();
                    setTimeout(() => {
                        statusDiv.classList.add('hidden');
                        btn.disabled = false;
                    }, 1200);
                } else {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    btn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Server connection error.`;
                btn.disabled = false;
            }
        }

        async function handlePasswordChange(e) {
            e.preventDefault();
            const cur = document.getElementById('pwdCurrent').value;
            const newP = document.getElementById('pwdNew').value;
            const conf = document.getElementById('pwdConfirm').value;
            const statusDiv = document.getElementById('passwordStatus');
            const btn = document.getElementById('savePwdBtn');

            statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Verifying & changing password...`;
            statusDiv.classList.remove('hidden');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('current_password', cur);
            fd.append('new_password', newP);
            fd.append('confirm_password', conf);

            try {
                const res = await fetch('api.php?action=change_password', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    document.getElementById('pwdCurrent').value = '';
                    document.getElementById('pwdNew').value = '';
                    document.getElementById('pwdConfirm').value = '';
                    setTimeout(() => {
                        statusDiv.classList.add('hidden');
                        btn.disabled = false;
                    }, 1200);
                } else {
                    statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    btn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-3 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Network error.`;
                btn.disabled = false;
            }
        }

        function renderAccountCraftsGrid() {
            if (!currentUser) return;
            const myPosts = allPosts.filter(p => p.user_id === currentUser.id || p.author === currentUser.display_name);
            document.getElementById('ovStatCrafts').innerText = myPosts.length;
            const container = document.getElementById('accMyCraftsGrid');

            if (myPosts.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full py-10 text-center text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                        <i class="fa-solid fa-shapes text-3xl mb-2 text-gray-300"></i>
                        <p class="text-xs font-semibold">You haven't listed any crafts yet.</p>
                        <button onclick="switchTab('create')" class="mt-3 px-4 py-1.5 bg-redAccent hover:bg-redHover text-white text-xs font-bold rounded-full transition shadow-sm">
                            <i class="fa-solid fa-plus mr-1"></i> Add Your First Craft
                        </button>
                    </div>
                `;
            } else {
                container.innerHTML = myPosts.map(post => `
                    <div class="bg-white rounded-xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition flex flex-col justify-between group">
                        <div class="relative cursor-pointer" onclick="openPostModal(${post.id})">
                            <img src="${post.imageUrl}" class="w-full h-36 object-cover group-hover:scale-102 transition duration-300" alt="${post.title}">
                            <span class="absolute top-2 left-2 bg-black/60 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                ${post.category || 'Craft'}
                            </span>
                        </div>
                        <div class="p-3 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-gray-900 truncate" title="${post.title}">${post.title}</h4>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-redAccent font-black">₱${post.price.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                                    <span class="text-[10px] text-gray-400 font-semibold"><i class="fa-solid fa-heart text-red-400 mr-0.5"></i> ${post.likes || 0}</span>
                                </div>
                            </div>
                            <div class="pt-2.5 mt-2 border-t border-gray-100 flex items-center gap-1.5">
                                <button type="button" onclick="openEditCraftModal(${post.id})" class="flex-1 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold rounded-lg border border-amber-200 flex items-center justify-center gap-1 transition shadow-sm cursor-pointer" title="Edit this craft listing">
                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i> Edit Craft
                                </button>
                                <button type="button" onclick="openPostModal(${post.id})" class="px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-[11px] font-semibold rounded-lg border border-gray-200 transition cursor-pointer" title="Preview craft on storefront">
                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        // ================= PROFILE PICTURE MANAGEMENT =================
        let currentAvatarMode = 'device';
        let selectedAvatarFile = null;

        function openAvatarModal() {
            if (!currentUser) return;
            selectedAvatarFile = null;
            document.getElementById('avatarModalPreview').src = currentUser.avatar_url || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10';
            document.getElementById('avatarUrlInput').value = currentUser.avatar_url || '';
            document.getElementById('avatarFileInput').value = '';
            document.getElementById('avatarUpdateStatus').classList.add('hidden');
            switchAvatarMode('device');
            document.getElementById('avatarModal').classList.remove('hidden');
        }

        function closeAvatarModal() {
            document.getElementById('avatarModal').classList.add('hidden');
        }

        function switchAvatarMode(mode) {
            currentAvatarMode = mode;
            const devSec = document.getElementById('avatarDeviceSection');
            const urlSec = document.getElementById('avatarUrlSection');
            const btnDev = document.getElementById('btn-avatar-mode-device');
            const btnUrl = document.getElementById('btn-avatar-mode-url');

            if (mode === 'device') {
                devSec.classList.remove('hidden');
                urlSec.classList.add('hidden');
                btnDev.className = "flex-1 pb-2 text-center border-b-2 border-redAccent text-redAccent font-bold";
                btnUrl.className = "flex-1 pb-2 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-600";
            } else {
                devSec.classList.add('hidden');
                urlSec.classList.remove('hidden');
                btnDev.className = "flex-1 pb-2 text-center border-b-2 border-transparent text-gray-400 hover:text-gray-600";
                btnUrl.className = "flex-1 pb-2 text-center border-b-2 border-redAccent text-redAccent font-bold";
            }
        }

        function previewAvatarFromFile(e) {
            const file = e.target.files[0];
            if (!file) return;
            selectedAvatarFile = file;
            const reader = new FileReader();
            reader.onload = function(evt) {
                document.getElementById('avatarModalPreview').src = evt.target.result;
            };
            reader.readAsDataURL(file);
        }

        function previewAvatarFromUrl(url) {
            if (url) {
                document.getElementById('avatarModalPreview').src = url;
            }
        }

        function resetToDefaultAvatar() {
            const defaultUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10';
            document.getElementById('avatarUrlInput').value = defaultUrl;
            document.getElementById('avatarModalPreview').src = defaultUrl;
        }

        async function handleAvatarSubmit(e) {
            e.preventDefault();
            const statusDiv = document.getElementById('avatarUpdateStatus');
            const saveBtn = document.getElementById('saveAvatarBtn');

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Updating profile picture...`;
            statusDiv.classList.remove('hidden');
            saveBtn.disabled = true;

            const fd = new FormData();
            if (currentAvatarMode === 'device' && selectedAvatarFile) {
                fd.append('avatar_image', selectedAvatarFile);
            } else {
                const url = document.getElementById('avatarUrlInput').value.trim();
                fd.append('avatar_url', url || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10');
            }

            try {
                const res = await fetch('api.php?action=update_avatar', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    
                    currentUser.avatar_url = data.avatar_url;
                    document.getElementById('accSidebarAvatar').src = data.avatar_url;
                    document.getElementById('setAvatarUrl').value = data.avatar_url;
                    const previewElem = document.getElementById('setAvatarPreview');
                    if (previewElem) previewElem.src = data.avatar_url;
                    updateAuthUI();

                    setTimeout(() => {
                        closeAvatarModal();
                        saveBtn.disabled = false;
                        statusDiv.classList.add('hidden');
                    }, 900);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    saveBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Network error updating avatar.`;
                saveBtn.disabled = false;
            }
        }

        // ================= EDIT CRAFT LISTING =================
        let editCraftUploadedFile = null;

        function openEditCraftModal(productId) {
            const post = allPosts.find(p => p.id == productId);
            if (!post) { alert('Craft not found.'); return; }

            document.getElementById('editCraftId').value = post.id;
            document.getElementById('editCraftTitle').value = post.title;
            document.getElementById('editCraftCategory').value = post.category || 'Fashion';
            document.getElementById('editCraftPrice').value = post.price.toFixed(2);
            document.getElementById('editCraftDescription').value = post.desc || '';
            document.getElementById('editCraftImageUrl').value = post.imageUrl;
            document.getElementById('editCraftPreviewImg').src = post.imageUrl;
            document.getElementById('editCraftIsActive').checked = true;
            document.getElementById('editCraftStatus').classList.add('hidden');
            editCraftUploadedFile = null;

            updateEditFeeEstimator();
            document.getElementById('editCraftModal').classList.remove('hidden');
        }

        function closeEditCraftModal() {
            document.getElementById('editCraftModal').classList.add('hidden');
        }

        async function updateEditFeeEstimator() {
            const price = parseFloat(document.getElementById('editCraftPrice').value) || 0;
            const category = document.getElementById('editCraftCategory').value;
            const optIn = (currentUser && currentUser.opt_in_marketing) ? 1 : 0;

            if (price <= 0) {
                document.getElementById('editFeeTotalDeductions').innerText = "-₱0.00";
                document.getElementById('editFeeNetAmount').innerText = "₱0.00";
                return;
            }

            try {
                const res = await fetch(`api.php?action=get_fee_preview&price=${price}&category=${encodeURIComponent(category)}&opt_in_marketing=${optIn}`);
                const data = await res.json();
                if (data.success) {
                    const b = data.breakdown;
                    document.getElementById('editFeeTotalDeductions').innerText = `-₱${(b.total_deductions / 100).toFixed(2)} (${b.effective_rate_percent})`;
                    document.getElementById('editFeeNetAmount').innerText = `₱${(b.net_amount / 100).toFixed(2)}`;
                }
            } catch (err) { console.error(err); }
        }

        async function handleEditCraftFileUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('image', file);
            try {
                const res = await fetch('api.php?action=upload_image', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('editCraftImageUrl').value = data.url;
                    document.getElementById('editCraftPreviewImg').src = data.url;
                } else {
                    alert(data.error || 'Upload failed');
                }
            } catch (err) { alert('Upload network error.'); }
        }

        async function handleEditCraftSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('editCraftId').value;
            const title = document.getElementById('editCraftTitle').value;
            const category = document.getElementById('editCraftCategory').value;
            const price = document.getElementById('editCraftPrice').value;
            const imageUrl = document.getElementById('editCraftImageUrl').value;
            const desc = document.getElementById('editCraftDescription').value;
            const isActive = document.getElementById('editCraftIsActive').checked ? 1 : 0;

            const statusDiv = document.getElementById('editCraftStatus');
            const saveBtn = document.getElementById('btnSaveCraft');

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving changes to craft...`;
            statusDiv.classList.remove('hidden');
            saveBtn.disabled = true;

            const fd = new FormData();
            fd.append('product_id', id);
            fd.append('title', title);
            fd.append('category', category);
            fd.append('price', price);
            fd.append('imageUrl', imageUrl);
            fd.append('description', desc);
            fd.append('is_active', isActive);

            try {
                const res = await fetch('api.php?action=update_product', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;

                    await fetchProducts();
                    renderAccountCraftsGrid();

                    setTimeout(() => {
                        closeEditCraftModal();
                        saveBtn.disabled = false;
                        statusDiv.classList.add('hidden');
                    }, 800);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    saveBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Network error updating craft.`;
                saveBtn.disabled = false;
            }
        }

        async function handleDeleteCraft() {
            const id = document.getElementById('editCraftId').value;
            if (!confirm('Are you sure you want to permanently remove this craft from your listings?')) return;

            const fd = new FormData();
            fd.append('product_id', id);

            try {
                const res = await fetch('api.php?action=delete_product', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    closeEditCraftModal();
                    await fetchProducts();
                    renderAccountCraftsGrid();
                } else {
                    alert(data.error || 'Failed to delete craft.');
                }
            } catch (err) { alert('Network error deleting craft.'); }
        }

        // ================= SHIPPING FEE RESPONSIBILITY ASSISTANT =================
        function openShippingAssistantModal(defaultCause = 'seller_fault') {
            document.getElementById('shipAstCause').value = defaultCause;
            document.getElementById('shipAstProgram').checked = false;
            document.getElementById('shipAstFreeShip').checked = false;
            document.getElementById('shipAstCapsHit').checked = false;
            runShippingAssistantEval();
            document.getElementById('shippingAssistantModal').classList.remove('hidden');
        }

        function closeShippingAssistantModal() {
            document.getElementById('shippingAssistantModal').classList.add('hidden');
        }

        async function runShippingAssistantEval() {
            const cause = document.getElementById('shipAstCause').value;
            const program = document.getElementById('shipAstProgram').checked ? 1 : 0;
            const freeShip = document.getElementById('shipAstFreeShip').checked ? 1 : 0;
            const capsHit = document.getElementById('shipAstCapsHit').checked ? 1 : 0;

            try {
                const res = await fetch(`api.php?action=evaluate_shipping_responsibility&cause=${encodeURIComponent(cause)}&enrolled_program=${program}&seller_free_shipping=${freeShip}&caps_hit=${capsHit}`);
                const data = await res.json();
                if (data.success) {
                    const e = data.evaluation;
                    document.getElementById('shipAstPayer').innerText = e.payer;
                    document.getElementById('shipAstRule').innerText = e.rule_applied;
                    document.getElementById('shipAstForward').innerText = e.forward_shipping;
                    document.getElementById('shipAstReturn').innerText = e.return_shipping;
                    document.getElementById('shipAstConditions').innerText = e.conditions_exceptions;
                    
                    const badge = document.getElementById('shipAstConfidenceBadge');
                    badge.innerText = `Confidence: ${e.confidence}`;
                    if (e.confidence === 'High') {
                        badge.className = "px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800";
                    } else if (e.confidence === 'Medium') {
                        badge.className = "px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-800";
                    } else {
                        badge.className = "px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-800";
                    }
                }
            } catch (err) { console.error('Error running shipping assistant eval:', err); }
        }

        // ================= XAMPP DATABASE EXPLORER WITH FULL CRUD (ADMIN ONLY) =================
        async function loadDatabaseView() {
            const wrapper = document.getElementById('dbTableWrapper');
            document.getElementById('adminTableTitle').innerText = currentDbTable;
            
            const statusTag = document.getElementById('adminTableStatusTag');
            const isImmutableLedger = (currentDbTable === 'seller_wallets' || currentDbTable === 'escrow_transactions');
            if (statusTag) {
                if (isImmutableLedger) {
                    statusTag.innerHTML = `<span class="bg-amber-100 text-amber-800 text-[10px] font-black px-2 py-0.5 rounded border border-amber-200"><i class="fa-solid fa-lock mr-1"></i> Read-Only Escrow Ledger</span>`;
                } else {
                    statusTag.innerHTML = `<span>(CRUD enabled)</span>`;
                }
            }

            wrapper.innerHTML = `
                <div class="p-8 text-center text-gray-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-500"></i>
                    <p class="text-xs">Fetching live rows from MySQL table <strong>${currentDbTable}</strong>...</p>
                </div>
            `;

            try {
                const res = await fetch(`api.php?action=db_stats&table=${currentDbTable}`);
                const data = await res.json();

                if (data.success) {
                    currentTableRows = data.rows || [];
                    const pkCol = data.primary_key || (currentDbTable === 'seller_wallets' ? 'seller_id' : 'id');

                    if (currentTableRows.length === 0) {
                        wrapper.innerHTML = isImmutableLedger ? `
                            <div class="p-8 text-center text-gray-400 text-xs">
                                Table <strong>${currentDbTable}</strong> is currently empty (0 rows).
                                <p class="text-[11px] text-gray-400 mt-1">Balances are automatically created and updated by the escrow system.</p>
                            </div>
                        ` : `
                            <div class="p-8 text-center text-gray-400 text-xs">
                                Table <strong>${currentDbTable}</strong> is currently empty (0 rows).
                                <button onclick="openAdminCreateRowModal()" class="mt-2 text-emerald-600 underline font-bold block mx-auto">+ Add the first record</button>
                            </div>
                        `;
                        return;
                    }

                    const columns = Object.keys(currentTableRows[0]);
                    let tableHtml = `
                        <table class="w-full text-left text-xs text-gray-700 border-collapse">
                            <thead class="bg-gray-100 text-[11px] text-gray-600 uppercase tracking-wider sticky top-0 border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3 font-bold text-center">Actions</th>
                                    ${columns.map(col => `<th class="py-2.5 px-3 font-bold">${col}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                ${currentTableRows.map(row => {
                                    const rowPkVal = (row[pkCol] !== undefined && row[pkCol] !== null) ? row[pkCol] : (row.id ?? row.seller_id ?? 0);
                                    return `
                                    <tr class="hover:bg-emerald-50/40 transition">
                                        <td class="py-2 px-3 text-center whitespace-nowrap">
                                            ${isImmutableLedger ? `
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 text-gray-500 rounded-lg text-[10px] font-bold border border-gray-200" title="Financial Ledger Protected: Balances strictly reflect escrow releases and withdrawals.">
                                                    <i class="fa-solid fa-lock text-[9px]"></i> Ledger Protected
                                                </span>
                                            ` : `
                                                <button onclick="openAdminEditRowModal('${currentDbTable}', ${rowPkVal})" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg text-[10px] mr-1 border border-blue-200" title="Edit row">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </button>
                                                <button onclick="handleAdminDeleteRow('${currentDbTable}', ${rowPkVal})" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 font-bold rounded-lg text-[10px] border border-red-200" title="Delete row">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            `}
                                        </td>
                                        ${columns.map(col => {
                                            let val = row[col];
                                            if (val === null) val = '<span class="text-gray-300 italic">NULL</span>';
                                            else if (col.includes('image_url') || col.includes('avatar_url')) {
                                                val = `<div class="flex items-center gap-1.5"><img src="${val}" class="w-6 h-6 rounded object-cover border"><span class="truncate max-w-[100px] text-gray-400 font-mono text-[9px]">${val}</span></div>`;
                                            } else if (col.includes('amount') || col.includes('price_centavos') || col.includes('balance') || col.includes('fee')) {
                                                val = `<span class="font-bold text-emerald-700">₱${((parseInt(val))/100).toFixed(2)}</span> <span class="text-gray-400 font-mono text-[9px]">(${val}¢)</span>`;
                                            } else if (col === 'status') {
                                                val = `<span class="px-2 py-0.5 rounded text-[10px] font-bold ${val === 'completed' || val === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${val}</span>`;
                                            } else {
                                                val = String(val).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                            }
                                            return `<td class="py-2 px-3 max-w-xs truncate">${val}</td>`;
                                        }).join('')}
                                    </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    `;
                    wrapper.innerHTML = tableHtml;
                } else {
                    wrapper.innerHTML = `<div class="p-8 text-center text-red-500 text-xs">${data.error}</div>`;
                }
            } catch (err) {
                wrapper.innerHTML = `<div class="p-8 text-center text-red-500 text-xs">Error connecting to MySQL: ${err.message}</div>`;
            }
        }

        function selectDbTable(tableName) {
            currentDbTable = tableName;
            document.querySelectorAll('[id^="tbl-btn-"]').forEach(btn => {
                btn.className = "px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200";
            });
            const activeBtn = document.getElementById(`tbl-btn-${tableName}`);
            if (activeBtn) activeBtn.className = "px-3 py-1.5 rounded-lg bg-emerald-600 text-white shadow-sm font-bold";
            loadDatabaseView();
        }

        // ================= ADMIN CRUD HANDLERS =================
        async function openAdminCreateRowModal() {
            if (currentDbTable === 'seller_wallets' || currentDbTable === 'escrow_transactions') {
                alert(`Records in '${currentDbTable}' are financial ledger records automatically managed by the escrow system and cannot be added manually.`);
                return;
            }

            document.getElementById('adminRecordModalTitle').innerText = `+ Add Record into ${currentDbTable}`;
            document.getElementById('adminModalTable').innerText = currentDbTable;
            document.getElementById('adminModalActionType').value = 'create';
            document.getElementById('adminModalRowId').value = '';
            document.getElementById('adminRecordStatus').classList.add('hidden');

            const container = document.getElementById('adminDynamicFields');
            container.innerHTML = `<div class="p-4 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Fetching schema columns...</div>`;
            document.getElementById('adminRecordModal').classList.remove('hidden');

            try {
                const res = await fetch(`api.php?action=admin_get_schema&table=${currentDbTable}`);
                const data = await res.json();
                if (data.success) {
                    const columns = data.columns.filter(c => c.Field !== 'id' && c.Field !== 'created_at' && c.Field !== 'updated_at');
                    container.innerHTML = columns.map(c => {
                        let inputType = "text";
                        if (c.Type.includes('int') || c.Type.includes('decimal')) inputType = "number";
                        let placeholder = c.Default ? `Default: ${c.Default}` : `Enter ${c.Field}`;
                        let isPassword = (c.Field === 'password_hash' || c.Field === 'password');
                        let fieldLabel = isPassword ? 'Password' : c.Field;
                        let fieldName = isPassword ? 'password' : c.Field;

                        if (isPassword) {
                            return `
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1 capitalize">${fieldLabel} <span class="text-gray-400 font-mono text-[10px]">(${c.Type})</span></label>
                                    <div class="relative password-wrapper">
                                        <input type="password" name="${fieldName}" placeholder="${placeholder}" 
                                               class="w-full px-3 py-2 pr-10 border rounded-xl outline-none focus:border-emerald-600 bg-white">
                                        <button type="button" onclick="togglePasswordVisibility(null, this)" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                                title="Show password" aria-label="Toggle password visibility">
                                            <i class="fa-solid fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        }

                        return `
                            <div>
                                <label class="block font-bold text-gray-700 mb-1 capitalize">${fieldLabel} <span class="text-gray-400 font-mono text-[10px]">(${c.Type})</span></label>
                                <input type="${inputType}" name="${fieldName}" placeholder="${placeholder}" 
                                       class="w-full px-3 py-2 border rounded-xl outline-none focus:border-emerald-600 bg-white">
                            </div>
                        `;
                    }).join('');
                }
            } catch (err) {
                container.innerHTML = `<p class="text-xs text-red-500">Failed to load table schema.</p>`;
            }
        }

        function openAdminEditRowModal(tableName, rowId) {
            if (tableName === 'seller_wallets' || tableName === 'escrow_transactions') {
                alert('Seller wallet balances are ledger-protected. Balances strictly reflect escrow releases and withdrawals and cannot be edited manually.');
                return;
            }

            const row = currentTableRows.find(r => (r.id !== undefined && r.id == rowId) || (r.seller_id !== undefined && r.seller_id == rowId));
            if (!row) { alert('Record not found.'); return; }

            document.getElementById('adminRecordModalTitle').innerText = `Edit Record #${rowId} in ${tableName}`;
            document.getElementById('adminModalTable').innerText = tableName;
            document.getElementById('adminModalActionType').value = 'update';
            document.getElementById('adminModalRowId').value = rowId;
            document.getElementById('adminRecordStatus').classList.add('hidden');

            const container = document.getElementById('adminDynamicFields');
            const columns = Object.keys(row).filter(c => c !== 'id' && c !== 'seller_id' && c !== 'created_at' && c !== 'updated_at');

            container.innerHTML = columns.map(c => {
                let val = row[c] === null ? '' : row[c];
                let isPassword = (c === 'password_hash');
                let fieldLabel = isPassword ? 'Reset Password (optional)' : c;
                let fieldName = isPassword ? 'password' : c;

                if (isPassword) {
                    return `
                        <div>
                            <label class="block font-bold text-gray-700 mb-1 capitalize">${fieldLabel}</label>
                            <div class="relative password-wrapper">
                                <input type="password" name="${fieldName}" value="" 
                                       placeholder="Leave empty to keep unchanged"
                                       class="w-full px-3 py-2 pr-10 border rounded-xl outline-none focus:border-emerald-600 bg-white">
                                <button type="button" onclick="togglePasswordVisibility(null, this)" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1 transition" 
                                        title="Show password" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }

                return `
                    <div>
                        <label class="block font-bold text-gray-700 mb-1 capitalize">${fieldLabel}</label>
                        <input type="text" name="${fieldName}" value="${String(val).replace(/"/g, '&quot;')}" 
                               class="w-full px-3 py-2 border rounded-xl outline-none focus:border-emerald-600 bg-white">
                    </div>
                `;
            }).join('');

            document.getElementById('adminRecordModal').classList.remove('hidden');
        }

        function closeAdminRecordModal() {
            document.getElementById('adminRecordModal').classList.add('hidden');
        }

        async function handleAdminRecordSubmit(e) {
            e.preventDefault();
            const actionType = document.getElementById('adminModalActionType').value;
            const rowId = document.getElementById('adminModalRowId').value;
            const statusDiv = document.getElementById('adminRecordStatus');
            const saveBtn = document.getElementById('adminSaveRecordBtn');

            const formFields = {};
            const inputs = document.querySelectorAll('#adminDynamicFields input, #adminDynamicFields textarea, #adminDynamicFields select');
            inputs.forEach(inp => {
                if (inp.name) {
                    if (inp.name === 'password' && inp.value === '') return;
                    formFields[inp.name] = inp.value;
                }
            });

            statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-blue-50 text-blue-700 border border-blue-200";
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving changes to MySQL...`;
            statusDiv.classList.remove('hidden');
            saveBtn.disabled = true;

            const fd = new FormData();
            fd.append('table', currentDbTable);
            fd.append('fields', JSON.stringify(formFields));
            if (actionType === 'update') fd.append('id', rowId);

            const endpointAction = actionType === 'update' ? 'admin_update_row' : 'admin_create_row';

            try {
                const res = await fetch(`api.php?action=${endpointAction}`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-emerald-50 text-emerald-700 border border-emerald-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
                    setTimeout(() => {
                        closeAdminRecordModal();
                        saveBtn.disabled = false;
                        loadDatabaseView();
                        fetchProducts();
                    }, 800);
                } else {
                    statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.error}`;
                    saveBtn.disabled = false;
                }
            } catch (err) {
                statusDiv.className = "p-2.5 rounded-xl text-xs font-semibold text-center bg-red-50 text-red-600 border border-red-200";
                statusDiv.innerHTML = `Network error.`;
                saveBtn.disabled = false;
            }
        }

        async function handleAdminDeleteRow(tableName, rowId) {
            if (!confirm(`Are you sure you want to permanently delete record #${rowId} from '${tableName}' in MySQL?`)) return;

            const fd = new FormData();
            fd.append('table', tableName);
            fd.append('id', rowId);

            try {
                const res = await fetch('api.php?action=admin_delete_row', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    loadDatabaseView();
                    fetchProducts();
                } else {
                    alert(data.error || 'Failed to delete row.');
                }
            } catch (err) {
                alert('Connection error deleting row.');
            }
        }
    </script>
</body>
</html>
