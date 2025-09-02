<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Kolkata');

// Include database connection
include "../db.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// Initialize array to store all transactions
$all_rows = [];

// 1. Fetch user_ledger data (existing)
$query = "SELECT ul.user_id, ul.username, ul.created_at as created_date, 
          ul.service_name, ul.transaction_type, ul.amount, ul.surcharge,
          ul.before_balance as opening_balance, 
          ul.after_balance as closing_balance,
          ul.utr_no 
          FROM user_ledger ul
          WHERE ul.username = :username 
          AND DATE(ul.created_at) BETWEEN :from_date AND :to_date
          ORDER BY ul.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$user_ledger_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add service name for user_ledger
foreach ($user_ledger_rows as &$row) {
    $row['service_name'] = $row['service_name'] ?? 'User Ledger';
    $row['status'] = 'success';
}
$all_rows = array_merge($all_rows, $user_ledger_rows);

// 2. Fetch transactions table data (existing)
$trans_query = "SELECT 
    transaction_date as created_date,
    user_id,
    remitter_mobile as username,
    'DMT Transaction' as service_name,
    'debit' as transaction_type,
    input_debit_amount as amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    utr_no,
    status
    FROM transactions
    WHERE user_id = :username
      AND DATE(transaction_date) BETWEEN :from_date AND :to_date
    ORDER BY transaction_date DESC";
$trans_stmt = $pdo->prepare($trans_query);
$trans_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$trans_rows = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $trans_rows);

// 3. Fetch aadharpay_transactions data
$aadharpay_query = "SELECT 
    transaction_date as created_date,
    username,
    'Aadhaar Pay' as service_name,
    CASE 
        WHEN transaction_status = 'success' THEN 'debit'
        ELSE 'debit'
    END as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    ackno as utr_no,
    transaction_status as status
    FROM aadharpay_transactions
    WHERE username = :username
      AND DATE(transaction_date) BETWEEN :from_date AND :to_date
    ORDER BY transaction_date DESC";
$aadharpay_stmt = $pdo->prepare($aadharpay_query);
$aadharpay_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$aadharpay_rows = $aadharpay_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $aadharpay_rows);

// 4. Fetch aeps_withdrawal data
$aeps_query = "SELECT 
    created_at as created_date,
    username,
    'AEPS Withdrawal' as service_name,
    'debit' as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    ackno as utr_no,
    status
    FROM aeps_withdrawal
    WHERE username = :username
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$aeps_stmt = $pdo->prepare($aeps_query);
$aeps_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$aeps_rows = $aeps_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $aeps_rows);

// 5. Fetch bill_payment_transactions data
$bill_query = "SELECT 
    created_at as created_date,
    user_id as username,
    CONCAT('Bill Payment - ', operator) as service_name,
    'debit' as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    referenceid as utr_no,
    status_label as status
    FROM bill_payment_transactions
    WHERE user_id = (SELECT id FROM users WHERE username = :username)
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$bill_stmt = $pdo->prepare($bill_query);
$bill_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$bill_rows = $bill_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $bill_rows);

// 6. Fetch dmt_transaction_logs data
$dmt_logs_query = "SELECT 
    transaction_date as created_date,
    user_id as username,
    'DMT Transaction Log' as service_name,
    'debit' as transaction_type,
    input_debit_amount as amount,
    surcharge,
    0 as before_balance,
    0 as after_balance,
    utr_no,
    status
    FROM dmt_transaction_logs
    WHERE user_id = :username
      AND DATE(transaction_date) BETWEEN :from_date AND :to_date
    ORDER BY transaction_date DESC";
$dmt_logs_stmt = $pdo->prepare($dmt_logs_query);
$dmt_logs_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$dmt_logs_rows = $dmt_logs_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $dmt_logs_rows);

// 7. Fetch dth_recharges data
$dth_query = "SELECT 
    created_at as created_date,
    username,
    CONCAT('DTH Recharge - ', provider_name) as service_name,
    CASE 
        WHEN status = 'success' THEN 'debit'
        ELSE 'debit'
    END as transaction_type,
    amount,
    0 as surcharge,
    before_balance,
    updated_balance as after_balance,
    CONCAT('DTH-', id) as utr_no,
    status
    FROM dth_recharges
    WHERE username = :username
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$dth_stmt = $pdo->prepare($dth_query);
$dth_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$dth_rows = $dth_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $dth_rows);

// 8. Fetch payout_transactions data
$payout_query = "SELECT 
    created_at as created_date,
    username,
    CONCAT('Payout - ', transaction_type) as service_name,
    CASE 
        WHEN transaction_type = 'DO_TRANSACTION' THEN 'debit'
        ELSE 'debit'
    END as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    ackno as utr_no,
    status
    FROM payout_transactions
    WHERE username = :username
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$payout_stmt = $pdo->prepare($payout_query);
$payout_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$payout_rows = $payout_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $payout_rows);

// 9. Fetch purchased_services data
$purchased_query = "SELECT 
    purchase_date as created_date,
    username,
    CONCAT('Service Purchase - ', service) as service_name,
    'debit' as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    CONCAT('SERVICE-', id) as utr_no,
    'success' as status
    FROM purchased_services
    WHERE username = :username
      AND DATE(purchase_date) BETWEEN :from_date AND :to_date
    ORDER BY purchase_date DESC";
$purchased_stmt = $pdo->prepare($purchased_query);
$purchased_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$purchased_rows = $purchased_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $purchased_rows);

// 10. Fetch recharge_details data
$recharge_query = "SELECT 
    created_at as created_date,
    username,
    CONCAT('Mobile Recharge - ', operator_name) as service_name,
    CASE 
        WHEN status = 'success' THEN 'debit'
        ELSE 'debit'
    END as transaction_type,
    amount,
    0 as surcharge,
    before_balance,
    updated_balance as after_balance,
    CONCAT('RECHARGE-', id) as utr_no,
    status
    FROM recharge_details
    WHERE username = :username
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$recharge_stmt = $pdo->prepare($recharge_query);
$recharge_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$recharge_rows = $recharge_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $recharge_rows);

// 11. Fetch wallet_transactions data
$wallet_query = "SELECT 
    created_at as created_date,
    user_id as username,
    CONCAT('Wallet ', type) as service_name,
    type as transaction_type,
    amount,
    0 as surcharge,
    0 as before_balance,
    0 as after_balance,
    reference_id as utr_no,
    status
    FROM wallet_transactions
    WHERE user_id = (SELECT id FROM users WHERE username = :username)
      AND DATE(created_at) BETWEEN :from_date AND :to_date
    ORDER BY created_at DESC";
$wallet_stmt = $pdo->prepare($wallet_query);
$wallet_stmt->execute([
    'username' => $username,
    'from_date' => $from_date,
    'to_date' => $to_date
]);
$wallet_rows = $wallet_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_rows = array_merge($all_rows, $wallet_rows);

// Sort all transactions by created_date descending
usort($all_rows, function ($a, $b) {
    return strtotime($b['created_date']) - strtotime($a['created_date']);
});

// Update the wallet query to use total_balance from users table
$wallet_query = "SELECT total_balance FROM users WHERE username = :username";
$wallet_stmt = $pdo->prepare($wallet_query);
$wallet_stmt->execute(['username' => $username]);
$wallet_row = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
$current_wallet_balance = $wallet_row ? floatval($wallet_row['total_balance']) : 0;

// Fetch user details
$user_query = "SELECT name, phone, email FROM users WHERE username = :username";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute(['username' => $username]);
$user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch surcharge_value from users table for current user
$surcharge_percent = 0;
$surcharge_query = "SELECT surcharge_value FROM users WHERE username = :username";
$surcharge_stmt = $pdo->prepare($surcharge_query);
$surcharge_stmt->execute(['username' => $username]);
$surcharge_row = $surcharge_stmt->fetch(PDO::FETCH_ASSOC);
if ($surcharge_row && isset($surcharge_row['surcharge_value'])) {
    $surcharge_percent = floatval($surcharge_row['surcharge_value']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete User Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <div class="container mt-4">
        <!-- Date Filter Form -->
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <label for="fromDate" class="form-label">From Date:</label>
                <input type="date" id="fromDate" name="from_date" class="form-control"
                    value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-4">
                <label for="toDate" class="form-label">To Date:</label>
                <input type="date" id="toDate" name="to_date" class="form-control"
                    value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-4 align-self-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        <h2>Complete Transaction History</h2>
        <div class="alert alert-info">
            <strong>Total Transactions:</strong> <?= count($all_rows) ?> transactions found
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped" id="transactionTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Opening Balance</th>
                        <th>Debit/Credit</th>
                        <th>Closing Balance</th>
                        <th>UTR No</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($all_rows):
                        // Get current wallet balance for closing balance
                        $closing_balance = $current_wallet_balance;
                        
                        foreach ($all_rows as $row):
                            $transaction_type = strtolower($row['transaction_type'] ?? 'debit');
                            $is_transaction = isset($row['service_name']) && $row['service_name'] === 'DMT Transaction';
                            
                            // Check if transaction is REFUNDED
                            $is_refunded = isset($row['status']) && $row['status'] === 'REFUNDED';
                            if ($is_refunded) {
                                $transaction_type = 'credit'; // Force credit for refunds
                            }
                            
                            // Surcharge value logic
                            if ($is_transaction) {
                                $input_debit_amount = $row['amount'];
                                $surcharge_value = ($input_debit_amount * $surcharge_percent) / 100;
                                $total_amount = $input_debit_amount + $surcharge_value;
                            } else {
                                $amount = $row['amount'];
                                $surcharge_value = isset($row['surcharge']) ? $row['surcharge'] : 0;
                                $total_amount = $amount + $surcharge_value;
                            }
                            
                            // Calculate opening balance based on transaction type
                            if ($transaction_type == 'credit') {
                                $opening_balance = $closing_balance - $total_amount;
                            } else {
                                $opening_balance = $closing_balance + $total_amount;
                            }
                            
                            // Store closing balance for next iteration
                            $prev_closing_balance = $closing_balance;
                            
                            // Set closing balance as opening balance for next iteration
                            $closing_balance = $opening_balance;
                            
                            // Status color coding
                            $status_class = '';
                            $status_text = $row['status'] ?? 'Unknown';
                            switch(strtolower($status_text)) {
                                case 'success':
                                case 'completed':
                                    $status_class = 'text-success';
                                    break;
                                case 'failed':
                                case 'error':
                                    $status_class = 'text-danger';
                                    break;
                                case 'pending':
                                    $status_class = 'text-warning';
                                    break;
                                default:
                                    $status_class = 'text-secondary';
                            }
                    ?>
                            <tr>
                                <td><?= date("d M Y, h:i A", strtotime($row['created_date'])) ?></td>
                                <td><?= htmlspecialchars($row['service_name'] ?? 'Unknown Service') ?></td>
                                <td>₹<?= number_format(abs($opening_balance), 2) ?></td>
                                <td class="<?= $transaction_type == 'credit' ? 'text-success' : 'text-danger' ?> fw-bold">
                                    <?= ($transaction_type == 'credit' ? '+' : '-') . '₹' . number_format($total_amount, 2) ?>
                                </td>
                                <td>₹<?= number_format(abs($prev_closing_balance), 2) ?></td>
                                <td><?= htmlspecialchars($row['utr_no'] ?? 'N/A') ?></td>
                                <td><span class="<?= $status_class ?>"><?= htmlspecialchars($status_text) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="printReceipt(<?= htmlspecialchars(json_encode([
                                        'transaction_date' => $row['created_date'],
                                        'service_name' => $row['service_name'] ?? 'Unknown Service',
                                        'amount' => $total_amount,
                                        'before_balance' => $opening_balance,
                                        'after_balance' => $closing_balance,
                                        'utr_no' => $row['utr_no'] ?? 'N/A',
                                        'user_name' => $user_details['name'],
                                        'user_mobile' => $user_details['phone'],
                                        'user_email' => $user_details['email'],
                                        'username' => $username,
                                        'status' => $status_text
                                    ])) ?>)">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </td>
                            </tr>
                    <?php 
                        endforeach; 
                    else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No transactions found for the selected date range</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function () {
            $('#transactionTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true,
                language: {
                    search: "Search transactions:",
                    lengthMenu: "Show _MENU_ transactions per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ transactions",
                    infoEmpty: "No transactions found",
                    infoFiltered: "(filtered from _MAX_ total transactions)"
                }
            });
        });

        // Enhanced printReceipt function
        function printReceipt(data) {
            var receiptHTML = `
                <div style="max-width:540px;margin:auto;padding:24px;
                    border-radius:12px;
                    box-shadow:0 2px 16px rgba(0,0,0,0.12);
                    background: linear-gradient(135deg, #e74c3c 0%, #000 100%);
                    color: #fff;
                    font-family:Arial,sans-serif;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <h3 style="margin:0;font-weight:bold;letter-spacing:1px;">Dhanaxis</h3>
                            <small style="font-size:1.1em;font-weight:bold;letter-spacing:1px;">
                                Transaction Receipt
                            </small>
                        </div>
                        <div>
                            <img src="Untitled design (6).png" alt="Logo" style="height:48px;border-radius:8px;">
                        </div>
                    </div>
                    <hr style="border-color:rgba(255,255,255,0.3);">
                    <div style="margin-bottom:12px;">
                        <strong>User Name:</strong> ${data.user_name}<br>
                        <strong>Mobile:</strong> ${data.user_mobile}<br>
                        <strong>Email:</strong> ${data.user_email}
                    </div>
                    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Date</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">${new Date(data.transaction_date).toLocaleString()}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Service</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">${data.service_name}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Amount</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">₹${parseFloat(data.amount).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Opening Balance</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">₹${Math.abs(data.before_balance).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Closing Balance</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">₹${Math.abs(data.after_balance).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>UTR No</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">${data.utr_no}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);"><strong>Status</strong></td>
                            <td style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.2);">${data.status}</td>
                        </tr>
                    </table>
                    <div style="text-align:center;color:#fff;">
                        <small style="font-size:1em;opacity:0.85;">This is a computer generated receipt.<br>
                        Thank you for using Dhanaxis.</small>
                    </div>
                </div>
            `;

            var win = window.open('', '', 'width=600,height=700');
            win.document.write('<html><head><title>Print Receipt</title></head><body>' + receiptHTML + '</body></html>');
            win.document.close();
            setTimeout(() => {
                win.print();
                win.close();
            }, 500);
        }
    </script>
</body>
</html>