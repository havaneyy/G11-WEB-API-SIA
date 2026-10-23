# SIA1 Merch Store — PayMongo Checkout Demo (XAMPP)

A small e-commerce app split into three responsibilities, matching the activity spec:

- **Product Service** → `index.php`, `admin/products.php` (the products table — this is the "content" you edit)
- **Order Service** → `cart.php`, the `orders` / `order_items` tables
- **Payment Service** → `checkout.php`, `success.php` (the *only* files that talk to PayMongo)

## 1. Install into XAMPP

1. Copy the whole `paymongo-shop` folder into your XAMPP `htdocs` directory, e.g.:
   - Windows: `C:\xampp\htdocs\paymongo-shop`
   - macOS: `/Applications/XAMPP/htdocs/paymongo-shop`
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

## 2. Create the database

1. Go to `http://localhost/phpmyadmin`.
2. Click **Import**, choose `database.sql` from this folder, and click **Go**.
   - This creates the `paymongo_shop` database with `products`, `orders`, and `order_items` tables, plus 3 sample products.

## 3. Add your PayMongo test key

1. Sign up at [paymongo.com](https://paymongo.com) (sandbox keys are instant, no approval needed).
2. Dashboard → **Developers → API Keys** → copy the **Test Secret Key** (`sk_test_...`).
3. Open `config.php` and replace:
   ```php
   define('PAYMONGO_SECRET_KEY', 'sk_test_REPLACE_WITH_YOUR_TEST_KEY');
   ```
   with your actual test key.
4. Confirm `APP_BASE_URL` matches how you access the app, e.g. `http://localhost/paymongo-shop`.

## 4. Run it

- Storefront: `http://localhost/paymongo-shop/`
- Admin (edit products / view orders): `http://localhost/paymongo-shop/admin/products.php`
  - Default password: `admin123` (change it in `admin/auth.php` before sharing with anyone)

## 5. Test a payment

1. Add a product to your cart and click **Proceed to PayMongo Checkout**.
2. You'll land on PayMongo's own hosted checkout page (`checkout.paymongo.com/...`).
3. Pick a payment method:
   - **Card**: use any [PayMongo test card number](https://developers.paymongo.com/docs/testing) with a future expiry and any CVC.
   - **GCash / Maya**: PayMongo shows a simulated Authorize/Fail page — no real account needed.
4. On success you're redirected back to `success.php`, which re-checks the payment status with PayMongo's API and marks the order `paid`.
5. Check `admin/orders.php` to see the order's status update.

## Notes on the design

- The PayMongo **secret key never leaves the server** — it's only referenced inside `checkout.php` and `success.php`, both of which run in PHP on the server, never in the browser.
- Amounts are stored and sent to PayMongo in **centavos** (`price_pesos * 100`), per PayMongo's API requirement.
- This uses the **Checkout API** only (no webhooks, no custom card form) — PayMongo hosts the entire payment page, so raw card numbers never touch this app's code, per the activity's intentionally limited scope.
- `success.php` doesn't just trust the redirect — it calls PayMongo's API server-side to confirm the payment actually went through before marking the order paid. This is a lightweight stand-in for the optional webhook stretch goal mentioned in the activity instructions.
