# CodeCraft Shop — XAMPP Setup

## 1. Install the project
Copy this entire `codecraft-shop` folder into `C:\xampp\htdocs\codecraft-shop`.
The `index.php` file must be directly inside that folder.

## 2. Start XAMPP
Start Apache and MySQL in XAMPP Control Panel.

## 3. Create/import the database
Open `http://localhost/phpmyadmin`, choose **Import**, select this project's `database.sql`, then click **Go**. The SQL creates and uses the `codecraft_shop` database and adds three sample products.

If you already created an empty `codecraft_shop` database, importing this file is okay. It creates the tables and sample products. Do not import it repeatedly after sample products are already inserted, or you may see duplicate sample entries.

## 4. Database settings
`config.php` is configured for a default local XAMPP database:
- DB host: `localhost`
- DB name: `codecraft_shop`
- DB user: `root`
- DB password: blank

Change these only if your XAMPP MySQL credentials differ.

## 5. Test the storefront
Open `http://localhost/codecraft-shop/`.
Admin pages: `http://localhost/codecraft-shop/admin/products.php` and `/admin/orders.php`.
The local demo admin password is `admin123`. Change it in `admin/login.php` before sharing or deploying the project.

## 6. PayMongo test setup
Open `config.php` and replace the placeholder in `PAYMONGO_SECRET_KEY` with your own PayMongo **TEST secret key** (`sk_test_...`). Do not paste the key into chat, commit it to Git, or put it in frontend JavaScript.
The configured app URL is `http://localhost/codecraft-shop`.

The provided checkout flow uses PayMongo's v1 Checkout Session endpoint and should be tested with a valid test key. Do not treat a redirect as proof of payment; `success.php` attempts server-side verification. If PayMongo returns an API error, capture the error text without sharing your secret key.

## 7. Cypress tests (optional)
Install Node.js, open a terminal in this project folder, and run:
`npm install`
Then:
`npm run cy:run`
Make sure Apache/MySQL are running first.

## Included
- Storefront, cart, checkout, success, cancellation pages
- `assets/style.css`
- Admin login, product management, and order listing pages
- Database schema/sample products
- Cypress starter tests

## Notes
This is a local coursework starter, not a production-ready store. Admin password handling, CSRF protection, stock control, and robust PayMongo webhook processing should be improved before public deployment.
