# CodeCraft Shop — E2E Testing with Cypress (SIA1 Final Project)

## 1. System Overview & Architecture
CodeCraft Shop is an artisan e-commerce platform built for System Integration and Architecture (SIA1). It consists of:
- **Storefront & UI**: Curated craft catalog with peso-formatted prices (centavos converted to Pesos).
- **Order Service (`orders.php` / `api.php`)**: Manages orders, calculating line-item totals and Escrow guarantee platform fees.
- **Payment Service (`checkout.php`)**: Centralized server-to-server gateway that initiates PayMongo Checkout Sessions. Secrets remain strictly on the server (`PAYMONGO_SECRET_KEY`).
- **Post-Payment Return Handling (`success.php` & `cancel.php`)**: Processes return redirects and marks orders as `paid` or `cancelled`.

---

## 2. Running the E2E Tests

### Step 1: Start Services
Ensure Apache and MySQL are running in your XAMPP Control Panel:
- Apache: `http://localhost/codecraft-shop/`
- MySQL: Running on port `3306` with database `codecraft_shop`

### Step 2: Seed the Data
Import `database.sql` into MySQL if not already loaded. The test suite expects at least 3 seeded products:
1. `SIA1 Class T-Shirt` (35,000 centavos = ₱350.00)
2. `SIA1 Tote Bag` (15,000 centavos = ₱150.00)
3. `SIA1 Mug` (12,000 centavos = ₱120.00)

### Step 3: Run the Tests Headlessly
Open your terminal in the project root and run:
```bash
npm run test:e2e
```
Or directly via Cypress CLI:
```bash
npx cypress run
```

To run interactively with the Cypress UI Test Runner:
```bash
npm run cy:open
```

---

## 3. Test Plan & Scenario Traceability Matrix

| Scenario ID | Feature / Scenario | Spec File | Test Name | Role / Owner | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **E2E-01** | Catalog | `cypress/e2e/catalog.cy.js` | `E2E-01 shows the catalog with correct peso prices` | Product | PASS |
| **E2E-02** | Order creation | `cypress/e2e/orders.cy.js` | `E2E-02 creates a pending order with the right total` | Order | PASS |
| **E2E-03** | Checkout session | `cypress/e2e/checkout.cy.js` | `E2E-03 gets a checkout URL from PayMongo` | Payment | PASS |
| **E2E-04** | Hand-over | `cypress/e2e/checkout.cy.js` | `E2E-04 hands the customer over to PayMongo` | Payment | PASS |
| **E2E-05** | Success return | `cypress/e2e/orders.cy.js` | `E2E-05 updates the order on the success page` | Order | PASS |
| **E2E-06** | Cancel return | `cypress/e2e/orders.cy.js` | `E2E-06 never marks a cancelled order as paid` | Order | PASS |
| **E2E-07** | Checkout failure | `cypress/e2e/checkout.cy.js` | `E2E-07 shows a clear error when checkout fails` | Payment | PASS |
| **E2E-08** | No secrets leaked | `cypress/e2e/security.cy.js` | `E2E-08 keeps the secret key out of the browser` | Product | PASS |
| **E2E-09** | Invalid checkout | `cypress/e2e/catalog.cy.js` | `E2E-09 blocks checkout with an empty cart` | Product | PASS |

---

## 4. Key Rules Followed
- **`data-cy` Selectors**: Tests select elements exclusively using `data-cy` attributes (`data-cy="product-card"`, `data-cy="checkout-button"`, `data-cy="order-status"`, etc.) without relying on CSS classes or fragile DOM positions.
- **Zero Fixed Waits**: No arbitrary `cy.wait(3000)`. All steps synchronize on aliased requests (`cy.wait('@checkout')`) or auto-retrying Cypress assertions.
- **PayMongo Boundary**: PayMongo hosted checkout (`https://checkout.paymongo.com`) is validated via `cy.origin()` asserting only `hostname`, leaving external third-party form fields untouched.
- **Security Audit**: Cypress spys on `https://api.paymongo.com/**` from the client and verifies that secret keys (`sk_test_`, `sk_live_`) are never transmitted or embedded in browser HTML/JS.
- **Automated Evidence**: Video recordings for all runs are saved automatically to `cypress/videos/`.
