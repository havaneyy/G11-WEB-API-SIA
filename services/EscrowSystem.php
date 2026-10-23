<?php
/**
 * services/EscrowSystem.php
 * Clean Architecture Service Layer & Repository Pattern for CodeCraft Guarantee & Escrow
 * All monetary operations use integer minor units (centavos) — NO floating point errors.
 */

require_once __DIR__ . '/../config.php';

// ==========================================
// 1. FEE CALCULATOR (Shopee-Style Deductions)
// ==========================================
class FeeCalculator {
    /**
     * Compute Shopee-style multi-layered platform deductions.
     * Total standard deductions: 15% to 20% (up to 25%–30% with optional marketing programs).
     *
     * @param int $grossCentavos Gross sale amount in centavos
     * @param string $category Product category
     * @param bool $optInMarketing Whether seller participates in Mega Discount / Live Xtra
     * @param bool $sellerCoversShipping Whether seller subsidizes forward shipping (Free Shipping promo)
     * @param bool $optInFSS Whether seller participates in Free Shipping Special (FSS) Program
     * @return array Breakdown of fees and net earnings in centavos
     */
    public static function calculate(int $grossCentavos, string $category = 'Fashion', bool $optInMarketing = false, bool $sellerCoversShipping = true, bool $optInFSS = false): array {
        // 1. Commission Fee: 1% to 6% (Standard: 6.00%, Tech: 4.50%, Food: 5.00%, Beauty: 6.00%)
        $commRate = 0.06;
        if ($category === 'Tech') $commRate = 0.045;
        elseif ($category === 'Food & Cafe') $commRate = 0.05;
        $commissionFee = (int)round($grossCentavos * $commRate);

        // 2. Transaction Fee: Standard 2.24% on final transaction amount
        $transactionFee = (int)round($grossCentavos * 0.0224);

        // 3. Platform Shipping Fee: 5.60%, capped at ₱100.00 (10,000 centavos) if seller covers, else 0 (buyer pays)
        $shippingFee = $sellerCoversShipping ? min(10000, (int)round($grossCentavos * 0.056)) : 0;
        $shippingRate = $sellerCoversShipping ? '5.60% (max ₱100 - Seller Free Ship)' : '₱0.00 (Buyer Pays)';

        // 4. Seller Growth Support Fee: 1.50% flat platform fee
        $growthSupportFee = (int)round($grossCentavos * 0.015);

        // 5. Order Processing Fee: Flat ₱5.00 (500 centavos)
        $processingFee = 500;

        // 6. Optional Marketing Program Fees (Mega Discount Vouchers / Live Xtra): 3.00% if opt-in
        $programFee = $optInMarketing ? (int)round($grossCentavos * 0.03) : 0;

        // 7. Optional Free Shipping Special Program (FSS): 3.00% if opt-in
        $fssFee = $optInFSS ? (int)round($grossCentavos * 0.03) : 0;

        $totalDeductions = $commissionFee + $transactionFee + $shippingFee + $growthSupportFee + $processingFee + $programFee + $fssFee;
        $netAmount = max(0, $grossCentavos - $totalDeductions);
        $effectiveRatePercent = $grossCentavos > 0 ? round(($totalDeductions / $grossCentavos) * 100, 2) : 0;

        return [
            'gross_amount' => $grossCentavos,
            'commission_fee' => $commissionFee,
            'commission_rate' => ($commRate * 100) . '%',
            'transaction_fee' => $transactionFee,
            'transaction_rate' => '2.24%',
            'shipping_fee' => $shippingFee,
            'shipping_rate' => $shippingRate,
            'seller_covers_shipping' => $sellerCoversShipping,
            'growth_support_fee' => $growthSupportFee,
            'growth_support_rate' => '1.50%',
            'processing_fee' => $processingFee,
            'processing_rate' => '₱5.00 flat',
            'program_fee' => $programFee,
            'program_rate' => $optInMarketing ? '3.00% (Marketing Opt-in)' : '0.00%',
            'fss_fee' => $fssFee,
            'fss_rate' => $optInFSS ? '3.00% (FSS Free Shipping)' : '0.00%',
            'total_deductions' => $totalDeductions,
            'net_amount' => $netAmount,
            'effective_rate_percent' => $effectiveRatePercent . '%'
        ];
    }
}

// ==========================================
// 2. REPOSITORY INTERFACES & IMPLEMENTATIONS
// ==========================================

class EscrowRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getOrder(int $orderId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getOrderByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getWallet(int $sellerId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM seller_wallets WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $wallet = $stmt->fetch();
        if (!$wallet) {
            $this->db->prepare("INSERT IGNORE INTO seller_wallets (seller_id, available_balance, pending_balance) VALUES (?, 0, 0)")
                     ->execute([$sellerId]);
            $stmt->execute([$sellerId]);
            $wallet = $stmt->fetch();
        }
        return $wallet;
    }

    public function hasReleaseRecord(int $orderId): bool {
        $stmt = $this->db->prepare("SELECT id FROM escrow_transactions WHERE order_id = ? AND type = 'release_to_seller'");
        $stmt->execute([$orderId]);
        return (bool)$stmt->fetch();
    }

    public function appendLedger(?int $orderId, int $sellerId, string $type, int $amount, int $balanceAfter, string $description): int {
        $stmt = $this->db->prepare(
            "INSERT INTO escrow_transactions (order_id, seller_id, type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$orderId ?: null, $sellerId, $type, $amount, $balanceAfter, $description]);
        return (int)$this->db->lastInsertId();
    }

    public function logOrderState(int $orderId, ?int $actorId, ?string $prevStatus, string $newStatus, string $note): void {
        $stmt = $this->db->prepare(
            "INSERT INTO order_state_logs (order_id, actor_id, previous_status, new_status, note) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$orderId, $actorId, $prevStatus, $newStatus, $note]);
    }
}

// ==========================================
// 3. ESCROW SERVICE (State Machine & Release)
// ==========================================

class EscrowService {
    private PDO $db;
    private EscrowRepository $repo;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->repo = new EscrowRepository($db);
    }

    /**
     * Capture buyer funds upon successful payment into escrow.
     */
    public function capture(int $orderId, int $sellerId, int $grossCentavos, int $netCentavos, ?int $actorId = null): void {
        $this->db->beginTransaction();
        try {
            // Update order status to paid
            $stmt = $this->db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
            $stmt->execute([$orderId]);

            // Credit pending balance in seller wallet
            $wallet = $this->repo->getWallet($sellerId);
            $newPending = $wallet['pending_balance'] + $netCentavos;
            $this->db->prepare("UPDATE seller_wallets SET pending_balance = ? WHERE seller_id = ?")
                     ->execute([$newPending, $sellerId]);

            // Append ledger entry
            $this->repo->appendLedger(
                $orderId,
                $sellerId,
                'capture',
                $netCentavos,
                $wallet['available_balance'],
                "Buyer payment captured into escrow for Order #$orderId (Pending: ₱" . number_format($netCentavos / 100, 2) . ")"
            );

            // Audit log
            $this->repo->logOrderState($orderId, $actorId, 'pending', 'paid', 'Buyer funds captured into platform escrow.');
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Release escrow funds to seller's internal wallet balance.
     * Enforces:
     * - Double-release prevention (Idempotency)
     * - Dispute/Refund freeze check
     * - Atomic DB transaction (Order completed, wallet credited, ledger inserted)
     */
    public function release(int $orderId, ?int $actorId = null, string $note = 'Order Received by buyer'): array {
        $order = $this->repo->getOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Order #$orderId not found.");
        }

        // Rule: If refund_requested, freeze the amount
        if ($order['status'] === 'refund_requested') {
            throw new \DomainException("Cannot release funds: Order #$orderId is disputed/refund requested. Amount is frozen.");
        }

        // Idempotency check: Cannot release twice
        if ($this->repo->hasReleaseRecord($orderId) || $order['status'] === 'completed') {
            throw new \DomainException("Idempotency violation: Escrow funds for Order #$orderId have already been released.");
        }

        if (!in_array($order['status'], ['paid', 'shipped', 'delivered'])) {
            throw new \DomainException("Order #$orderId status '{$order['status']}' is not eligible for escrow release.");
        }

        $sellerId = (int)$order['seller_id'];
        $netCentavos = (int)$order['net_amount'];

        $this->db->beginTransaction();
        try {
            // 1. Update order status
            $prevStatus = $order['status'];
            $stmt = $this->db->prepare("UPDATE orders SET status = 'completed', released_at = NOW() WHERE id = ?");
            $stmt->execute([$orderId]);

            // 2. Debit pending, credit available balance in wallet
            $wallet = $this->repo->getWallet($sellerId);
            $newPending = max(0, $wallet['pending_balance'] - $netCentavos);
            $newAvailable = $wallet['available_balance'] + $netCentavos;

            $this->db->prepare("UPDATE seller_wallets SET pending_balance = ?, available_balance = ? WHERE seller_id = ?")
                     ->execute([$newPending, $newAvailable, $sellerId]);

            // 3. Append ledger row (type = 'release_to_seller')
            $this->repo->appendLedger(
                $orderId,
                $sellerId,
                'release_to_seller',
                $netCentavos,
                $newAvailable,
                "Escrow Guarantee released to Seller Wallet for Order #{$order['order_code']} (₱" . number_format($netCentavos / 100, 2) . ")"
            );

            // 4. Audit Log
            $this->repo->logOrderState($orderId, $actorId, $prevStatus, 'completed', $note);

            $this->db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'net_amount' => $netCentavos,
                'new_available_balance' => $newAvailable,
                'message' => "Order #{$order['order_code']} marked completed. Escrow released to seller wallet."
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark order as shipped.
     */
    public function markShipped(int $orderId, ?int $actorId = null): void {
        $order = $this->repo->getOrder($orderId);
        if (!$order || $order['status'] !== 'paid') {
            throw new \DomainException("Order must be paid before marking shipped.");
        }
        $this->db->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")->execute([$orderId]);
        $this->repo->logOrderState($orderId, $actorId, 'paid', 'shipped', 'Seller has dispatched the parcel.');
    }

    /**
     * Mark order as delivered, starting the 7-day guarantee countdown.
     */
    public function markDelivered(int $orderId, ?int $actorId = null, int $guaranteeDays = 7): void {
        $order = $this->repo->getOrder($orderId);
        if (!$order || !in_array($order['status'], ['paid', 'shipped'])) {
            throw new \DomainException("Order cannot be marked delivered in its current state.");
        }
        $stmt = $this->db->prepare(
            "UPDATE orders SET status = 'delivered', delivered_at = NOW(), guarantee_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?"
        );
        $stmt->execute([$guaranteeDays, $orderId]);
        $this->repo->logOrderState($orderId, $actorId, $order['status'], 'delivered', "Order delivered. Guarantee protection active for $guaranteeDays days.");
    }
}

// ==========================================
// 4. REFUND SERVICE (Dispute Resolution)
// ==========================================

class RefundService {
    private PDO $db;
    private EscrowRepository $repo;
    private EscrowService $escrowService;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->repo = new EscrowRepository($db);
        $this->escrowService = new EscrowService($db);
    }

    public function requestRefund(int $orderId, int $buyerId, string $reason): void {
        $order = $this->repo->getOrder($orderId);
        if (!$order) throw new \InvalidArgumentException("Order not found.");

        if (in_array($order['status'], ['completed', 'refunded', 'cancelled'])) {
            throw new \DomainException("Cannot dispute an order that is already {$order['status']}.");
        }

        $prevStatus = $order['status'];
        $this->db->prepare("UPDATE orders SET status = 'refund_requested' WHERE id = ?")->execute([$orderId]);
        $this->repo->logOrderState($orderId, $buyerId, $prevStatus, 'refund_requested', "Buyer requested refund/return: $reason. Funds frozen in escrow.");
    }

    public function resolve(int $orderId, int $actorId, string $decision, string $reason): array {
        $order = $this->repo->getOrder($orderId);
        if (!$order || $order['status'] !== 'refund_requested') {
            throw new \DomainException("Order must have a pending refund request to resolve.");
        }

        $sellerId = (int)$order['seller_id'];
        $netCentavos = (int)$order['net_amount'];

        if ($decision === 'refund_buyer') {
            $this->db->beginTransaction();
            try {
                $this->db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$orderId]);

                $wallet = $this->repo->getWallet($sellerId);
                $newPending = max(0, $wallet['pending_balance'] - $netCentavos);
                $this->db->prepare("UPDATE seller_wallets SET pending_balance = ? WHERE seller_id = ?")
                         ->execute([$newPending, $sellerId]);

                $this->repo->appendLedger(
                    $orderId,
                    $sellerId,
                    'refund',
                    $netCentavos,
                    $wallet['available_balance'],
                    "Dispute resolved in buyer's favor. Escrow refunded for Order #{$order['order_code']} ($reason)"
                );

                $this->repo->logOrderState($orderId, $actorId, 'refund_requested', 'refunded', "Dispute resolved: Refund granted. $reason");
                $this->db->commit();
                return ['success' => true, 'decision' => 'refund_buyer', 'message' => 'Order refunded to buyer.'];
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } elseif ($decision === 'release_seller') {
            // Unfreeze and release to seller
            $this->db->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$orderId]);
            return $this->escrowService->release($orderId, $actorId, "Dispute resolved in seller's favor: $reason");
        } else {
            throw new \InvalidArgumentException("Invalid resolution decision: $decision");
        }
    }
}

// ==========================================
// 5. WITHDRAWAL SERVICE & BANK ACCOUNTS
// ==========================================

class WithdrawalService {
    private PDO $db;
    private EscrowRepository $repo;
    public const DAILY_MAX_CENTAVOS = 50000000; // ₱500,000.00
    public const FLAT_FEE_CENTAVOS = 1500;       // ₱15.00 after 2 free transfers

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->repo = new EscrowRepository($db);
    }

    public function getBankAccounts(int $sellerId): array {
        $stmt = $this->db->prepare("SELECT * FROM bank_accounts WHERE seller_id = ? ORDER BY id DESC");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll();
    }

    public function addBankAccount(int $sellerId, string $bankName, string $accountNumber, string $accountName, bool $isPartner = false): int {
        $masked = '•••• •••• ' . substr(trim($accountNumber), -4);
        $encrypted = 'enc_' . base64_encode($accountNumber);

        $stmt = $this->db->prepare(
            "INSERT INTO bank_accounts (seller_id, bank_name, account_number_encrypted, account_number_masked, account_name, is_partner_bank, is_verified, verified_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$sellerId, $bankName, $encrypted, $masked, $accountName, $isPartner ? 1 : 0]);
        return (int)$this->db->lastInsertId();
    }

    public function request(int $sellerId, int $bankAccountId, int $amountCentavos, string $method = 'manual'): array {
        if ($amountCentavos <= 0) {
            throw new \InvalidArgumentException("Withdrawal amount must be greater than zero.");
        }

        if ($amountCentavos > self::DAILY_MAX_CENTAVOS) {
            throw new \DomainException("Requested amount exceeds daily limit of ₱500,000.00.");
        }

        // Verify bank account belongs to seller & is verified
        $stmt = $this->db->prepare("SELECT * FROM bank_accounts WHERE id = ? AND seller_id = ?");
        $stmt->execute([$bankAccountId, $sellerId]);
        $bank = $stmt->fetch();
        if (!$bank || empty($bank['is_verified'])) {
            throw new \DomainException("A verified linked bank account is required before requesting withdrawal.");
        }

        $wallet = $this->repo->getWallet($sellerId);

        // Check fee rules:
        // Partner bank is always free (0 fee)
        // Non-partner: first 2 manual withdrawals in current calendar week free, then flat fee of ₱15.00
        $feeCentavos = 0;
        if (empty($bank['is_partner_bank'])) {
            $weekStmt = $this->db->prepare(
                "SELECT count(*) FROM withdrawals 
                 WHERE seller_id = ? AND method = 'manual' AND status != 'failed' 
                   AND YEARWEEK(requested_at, 1) = YEARWEEK(NOW(), 1)"
            );
            $weekStmt->execute([$sellerId]);
            $countThisWeek = (int)$weekStmt->fetchColumn();
            if ($countThisWeek >= 2) {
                $feeCentavos = self::FLAT_FEE_CENTAVOS;
            }
        }

        $totalDeduction = $amountCentavos + $feeCentavos;
        if ($wallet['available_balance'] < $totalDeduction) {
            $needed = number_format($totalDeduction / 100, 2);
            $have = number_format($wallet['available_balance'] / 100, 2);
            throw new \DomainException("Insufficient available balance. Required: ₱$needed (including fee: ₱" . number_format($feeCentavos / 100, 2) . "), Available: ₱$have");
        }

        // Weekend queuing: only process weekdays (Mon-Fri)
        $dayOfWeek = (int)date('N'); // 1 (Mon) to 7 (Sun)
        $isWeekend = ($dayOfWeek >= 6);

        $this->db->beginTransaction();
        try {
            $newBalance = $wallet['available_balance'] - $totalDeduction;
            $this->db->prepare("UPDATE seller_wallets SET available_balance = ? WHERE seller_id = ?")
                     ->execute([$newBalance, $sellerId]);

            $stmt = $this->db->prepare(
                "INSERT INTO withdrawals (seller_id, bank_account_id, amount, fee, method, status, is_weekend_queued)
                 VALUES (?, ?, ?, ?, ?, 'processing', ?)"
            );
            $stmt->execute([$sellerId, $bankAccountId, $amountCentavos, $feeCentavos, $method, $isWeekend ? 1 : 0]);
            $withdrawalId = (int)$this->db->lastInsertId();

            $desc = "Withdrawal to {$bank['bank_name']} ({$bank['account_number_masked']}) - ₱" . number_format($amountCentavos / 100, 2);
            if ($feeCentavos > 0) $desc .= " (Fee: ₱" . number_format($feeCentavos / 100, 2) . ")";
            if ($isWeekend) $desc .= " [Weekend Queued for Monday]";

            $this->repo->appendLedger(null, $sellerId, 'withdrawal', $totalDeduction, $newBalance, $desc);
            $this->db->commit();

            return [
                'success' => true,
                'withdrawal_id' => $withdrawalId,
                'amount' => $amountCentavos,
                'fee' => $feeCentavos,
                'is_weekend_queued' => $isWeekend,
                'remaining_balance' => $newBalance,
                'message' => $isWeekend 
                    ? "Withdrawal requested. It is weekend; funds are queued and will process Monday." 
                    : "Withdrawal processing to {$bank['bank_name']}."
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Settle withdrawal (e.g. from bank webhooks or simulation).
     * Failed transfers must recredit the wallet.
     */
    public function settle(int $withdrawalId, bool $isSuccess): void {
        $stmt = $this->db->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$withdrawalId]);
        $w = $stmt->fetch();
        if (!$w || $w['status'] !== 'processing') return;

        $sellerId = (int)$w['seller_id'];
        $amount = (int)$w['amount'];
        $fee = (int)$w['fee'];
        $total = $amount + $fee;

        $this->db->beginTransaction();
        try {
            if ($isSuccess) {
                $this->db->prepare("UPDATE withdrawals SET status = 'completed', processed_at = NOW() WHERE id = ?")
                         ->execute([$withdrawalId]);
            } else {
                // Recredit wallet
                $this->db->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?")
                         ->execute([$withdrawalId]);

                $wallet = $this->repo->getWallet($sellerId);
                $newAvailable = $wallet['available_balance'] + $total;
                $this->db->prepare("UPDATE seller_wallets SET available_balance = ? WHERE seller_id = ?")
                         ->execute([$newAvailable, $sellerId]);

                $this->repo->appendLedger(
                    null,
                    $sellerId,
                    'withdrawal_recredit',
                    $total,
                    $newAvailable,
                    "Recredit for failed withdrawal #$withdrawalId (₱" . number_format($total / 100, 2) . ")"
                );
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

// ==========================================
// 6. BACKGROUND CRON / SCHEDULED JOBS
// ==========================================

class GuaranteeExpiryJob {
    public static function run(PDO $db): array {
        $escrow = new EscrowService($db);
        // Find delivered orders past guarantee expiry
        $stmt = $db->query(
            "SELECT id, order_code FROM orders 
             WHERE status = 'delivered' 
               AND guarantee_expires_at IS NOT NULL 
               AND guarantee_expires_at <= NOW()"
        );
        $orders = $stmt->fetchAll();
        $released = [];
        foreach ($orders as $ord) {
            try {
                $res = $escrow->release((int)$ord['id'], null, 'Auto-release: CodeCraft Guarantee window expired.');
                $released[] = $ord['order_code'];
            } catch (\Exception $e) {
                // Log and continue
            }
        }
        return ['processed_count' => count($released), 'orders' => $released];
    }
}

class AutoWithdrawalJob {
    public static function run(PDO $db): array {
        $withdrawalService = new WithdrawalService($db);
        $dayOfWeek = (int)date('N'); // 1 = Monday

        $stmt = $db->prepare(
            "SELECT ws.seller_id, sw.available_balance, ba.id AS bank_id 
             FROM withdrawal_settings ws
             JOIN seller_wallets sw ON ws.seller_id = sw.seller_id
             JOIN bank_accounts ba ON ba.seller_id = ws.seller_id AND ba.is_verified = 1
             WHERE ws.auto_enabled = 1 AND ws.day_of_week = ? AND sw.available_balance > 10000
             GROUP BY ws.seller_id"
        );
        $stmt->execute([$dayOfWeek]);
        $eligible = $stmt->fetchAll();
        $processed = 0;
        foreach ($eligible as $row) {
            try {
                $withdrawalService->request((int)$row['seller_id'], (int)$row['bank_id'], (int)$row['available_balance'], 'auto');
                $processed++;
            } catch (\Exception $e) {
                // Log and continue
            }
        }
        return ['auto_withdrawals_executed' => $processed];
    }
}

// ==========================================
// 8. SHIPPING FEE RESPONSIBILITY ASSISTANT
// ==========================================
class ShippingFeeAssistant {
    /**
     * Determine and explain who pays shipping costs — forward, return, or replacement — based on order context.
     * Evaluates in strict order:
     * 1. Who caused the issue?
     * 2. Is order enrolled in fee-support program?
     * 3. Does seller offer free/seller-covered shipping?
     * 4. Are subsidy caps or limits hit?
     */
    public static function evaluate(
        string $cause, 
        bool $enrolledProgram = false, 
        bool $sellerFreeShipping = false, 
        bool $capsHit = false,
        string $specificReason = ''
    ): array {
        // Step 1: Who caused the issue?
        if ($cause === 'unclear' || $cause === 'disputed' || empty($cause)) {
            return [
                'payer' => 'Platform / Flag for Manual Review',
                'forward_shipping' => 'Pending review of courier logs & parcel evidence',
                'return_shipping' => 'Pending review of dispute claims',
                'rule_applied' => 'Step 1: Unclear / Disputed liability branch',
                'conditions_exceptions' => 'Dispute flagged for manual admin review. Costs will not be assigned until evidence is examined.',
                'confidence' => 'Low'
            ];
        }

        if ($cause === 'seller_fault') {
            // Seller fault (defective, wrong item, not as described, late dispatch) -> SELLER pays forward + return shipping.
            $payer = 'Seller';
            $forwardShipping = 'Covered by Seller (or refunded to buyer if previously paid)';
            $returnShipping = 'Covered by Seller';
            $ruleApplied = 'Step 1: Seller fault (defective/wrong item/not as described/late dispatch)';
            $conditionsExceptions = 'Seller is liable for all transit fees. Seller must provide return waybill or refund courier cost.';
            $confidence = 'High';

            // Step 2 & 4: If enrolled in fee support program and caps not hit
            if ($enrolledProgram) {
                if (!$capsHit) {
                    $returnShipping = 'Subsidized by Platform Program (up to subsidy cap); excess covered by Seller';
                    $ruleApplied = 'Step 2: Seller fault with Fee-Support Program Enrollment';
                    $conditionsExceptions = 'Order enrolled in Platform Fee-Support. Return label subsidized up to platform ceiling (₱100/label).';
                } else {
                    $conditionsExceptions .= ' Note: Platform subsidy ceiling has been reached for this period; standard seller liability applies.';
                }
            }
        } elseif ($cause === 'buyer_fault') {
            // Buyer fault / change of mind -> BUYER pays return shipping; forward shipping may be non-refundable.
            $payer = 'Buyer';
            $forwardShipping = $sellerFreeShipping ? 'Covered initially by Seller promotion (non-refundable upon return)' : 'Covered by Buyer (non-refundable)';
            $returnShipping = 'Covered by Buyer';
            $ruleApplied = 'Step 1: Buyer fault / Change of mind';
            $conditionsExceptions = 'Forward shipping fee is non-refundable. Buyer must arrange and shoulder return transit in original condition.';
            $confidence = 'High';

            // Step 2: Fee-support program check
            if ($enrolledProgram) {
                if (!$capsHit) {
                    $returnShipping = 'Subsidized by Platform Return Voucher (up to platform cap); remainder covered by Buyer';
                    $ruleApplied = 'Step 2: Buyer change of mind under Program Support Voucher';
                    $conditionsExceptions = 'Platform subsidizes return label up to subsidy cap. Excess shipping borne by buyer.';
                } else {
                    $conditionsExceptions .= ' Note: Program subsidy cap reached; buyer pays 100% of return transit.';
                }
            }

            // Step 3: Free shipping promo note
            if ($sellerFreeShipping) {
                $conditionsExceptions .= ' Free shipping promotions do not shift return liability to the seller.';
            }
        } elseif ($cause === 'carrier_fault') {
            // Carrier / platform fault (lost, damaged in transit) -> PLATFORM covers via insurance/support program.
            $payer = 'Platform';
            $forwardShipping = 'Covered by Platform (via shipping insurance / logistics claim)';
            $returnShipping = 'Covered by Platform (or waived if item lost/destroyed in transit)';
            $ruleApplied = 'Step 1: Carrier / Platform fault (damaged or lost in transit)';
            $conditionsExceptions = 'Damaged/lost parcel covered under CodeCraft Platform Guarantee Insurance. Neither seller nor buyer is penalized.';
            $confidence = 'High';

            if ($capsHit) {
                $conditionsExceptions .= ' Standard carrier insurance claim filed with third-party logistics provider.';
                $confidence = 'Medium';
            }
        } else {
            return [
                'payer' => 'Escalate / Manual Review',
                'forward_shipping' => 'Undetermined',
                'return_shipping' => 'Undetermined',
                'rule_applied' => 'Fallback: Unrecognized cause category',
                'conditions_exceptions' => 'Please provide specific cause details to evaluate liability.',
                'confidence' => 'Low'
            ];
        }

        return [
            'payer' => $payer,
            'forward_shipping' => $forwardShipping,
            'return_shipping' => $returnShipping,
            'rule_applied' => $ruleApplied,
            'conditions_exceptions' => $conditionsExceptions,
            'confidence' => $confidence
        ];
    }
}

