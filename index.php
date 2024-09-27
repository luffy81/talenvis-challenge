<?php
session_start();  // Start session to handle user logins

// Predefined users with usernames and passwords
$users = [
    'Feon' => 'pass123',
    'Vira' => 'pass456'
];

// Bank Account Class Definition
class BankAccount {
    private $balance;
    private $history = [];

    public function __construct($initialBalance = 0) {
        $this->balance = $initialBalance;
        $this->logTransaction('Initial Balance', 0, 0, $this->balance, '');
    }

    public function deposit($amount, $log=true) {
        if ($amount > 0) {
            $this->balance += $amount;
            if($log) $this->logTransaction('Deposit', 0, $amount, $this->balance, '');
            return "Deposited: $$amount. New Balance: $$this->balance";
        } else {
            return "Deposit amount must be positive.";
        }
    }

    public function withdraw($amount, $log=true) {
        if ($amount <= 0) {
            return ["res"=> "error", "message"=> "Withdrawal amount must be positive."];
        } elseif ($amount > $this->balance) {
            return ["res"=> "error", "message"=>"Your balance is insufficient"];
        } else {
            $this->balance -= $amount;
            if($log) $this->logTransaction('Withdraw', $amount, 0, $this->balance, '');
            return ["res"=> "ok", "message"=> "Withdrew: $$amount. New Balance: $$this->balance"];
        }
    }

    public function transfer($amount, $recipientAccount, $recipientName) {
        if ($amount <= 0) {
            return ["res"=> "error", "message"=> "Withdrawal amount must be positive."];
        } elseif ($amount > $this->balance) {
            return ["res"=> "error", "message"=>"Your balance is insufficient"];
        } else {
            $this->withdraw($amount, false);
            $recipientAccount->deposit($amount, false);
            $this->logTransaction('Transfer', $amount, 0, $this->balance, "Transfer to $recipientName");
            $recipientAccount->logTransaction('Transfer', 0, $amount, $recipientAccount->getBalance(), "Transfer from {$_SESSION['username']}");
            return ["res"=> "ok", "message"=> "Transferred: $$amount to $recipientName."];
        }
    }

    public function checkBalance() {
        return "Current Balance: $$this->balance";
    }

    private function logTransaction($type, $debit, $credit, $balance, $description) {
        $this->history[] = [
            'time' => date('Y-m-d H:i:s'),
            'type' => $type,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $balance,
            'description' => $description
        ];
    }

    public function getHistory() {
        return $this->history;
    }

    public function getBalance() {
        return $this->balance;
    }

    // Store the history in session
    public function storeHistoryInSession($username) {
        $_SESSION['history'][$username] = $this->history;  // Save current history to session
    }

    // Load the history from session
    public function loadHistoryFromSession($username) {
        if (isset($_SESSION['history'][$username])) {
            $this->history = $_SESSION['history'][$username];  // Load history from session
        }
    }
}

// Check if the user is trying to log out
if (isset($_POST['logout'])) {
    // print_r($_SESSION['accounts']);
    // print_r($_SESSION['username']);
    // print_r($_SESSION['history']);
    unset($_SESSION['username']);
    // session_destroy();  // Destroy all session data
    header("Location: " . $_SERVER['PHP_SELF']);  // Reload the page to show the login form again
    exit();
}

// Check if the user is trying to log in
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Authenticate user
    if (isset($users[$username]) && $users[$username] == $password) {
        $_SESSION['username'] = $username;

        // If it's the first login, initialize their bank account in the session
        if (!isset($_SESSION['accounts'][$username])) {
            $_SESSION['accounts'][$username] = new BankAccount(0);  // Starting balance is 100
        }

        // Load user's history from session
        $_SESSION['accounts'][$username]->loadHistoryFromSession($username);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}

// If logged in, load the user's account
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $account = $_SESSION['accounts'][$username];

    // Handle action
    if (isset($_POST['save'])) {
        if ($_POST['type'] == 'deposit') {
            $amount = floatval($_POST['amount']);
            $message = $account->deposit($amount);
            $_SESSION['account'] = $account; // Update session with new account state
        }
    
        if ($_POST['type'] == 'withdraw') {
            $amount = floatval($_POST['amount']);
            $withdraw = $account->withdraw($amount);
            $errorWithdraw = ($withdraw['res'] == 'error') ? $withdraw['message'] : '';
            $_SESSION['account'] = $account; // Update session with new account state
        }

        $account->storeHistoryInSession($username);  // Store history after deposit
        $_SESSION['accounts'][$username] = $account;  // Update session with new account state
    
    }

    // Handle transfer action
    if (isset($_POST['transfer'])) {
        $amount = floatval($_POST['amount']);
        $recipient = $_POST['recipient'];

        if (isset($_SESSION['accounts'][$recipient])) {
            $recipientAccount = $_SESSION['accounts'][$recipient];
            $transfer = $account->transfer($amount, $recipientAccount, $recipient);
            $errorTransfer = ($transfer['res'] == 'error') ? $transfer['message'] : '';
            $account->storeHistoryInSession($username);  // Store sender's history after transfer
            $recipientAccount->storeHistoryInSession($recipient);  // Store recipient's history after transfer
            $_SESSION['accounts'][$username] = $account;  // Update sender's account in session
            $_SESSION['accounts'][$recipient] = $recipientAccount;  // Update recipient's account in session
        } else {
            $message = "Recipient does not exist!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>table{width:100%;border-collapse:collapse}table,th,td{border:1px solid black}th,td{padding:8px;text-align:center}</style>
    <title>Bank Account with Transfer History</title>
</head>
<body>

    <?php if (!isset($_SESSION['username'])): ?>

        <!-- Login Form -->
        <h2>Login</h2>
        <form method="post" action="">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required><br><br>
            
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required><br><br>

            <input type="submit" name="login" value="Login">
        </form>

        <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

    <?php else: ?>

        <h1>Welcome, <?php echo $username; ?></h1>

        <!-- Display current balance -->
        <p><?php echo $account->checkBalance(); ?></p>

        <!-- Display transaction messages -->
        <!-- <p><?php if (isset($message)) { echo $message; } ?></p> -->

        <!-- Form for Deposit / Withdraw -->
        <h3>Deposit / Withdraw Money</h3>
        <form method="post" action="">
            <label for="transaction-amount">Amount:</label>
            <select name="type" id="type-transaction">
                <option value="deposit">Deposit</option>
                <option value="withdraw">Withdraw</option>
            </select>
            <input type="number" name="amount" id="amount" step="0.01" required>
            <input type="submit" name="save" value="Save">
        </form>
        <?php if (isset($errorWithdraw)) { echo "<p style='color:red;'>$errorWithdraw</p>"; } ?>

        <!-- Transfer Money to Another User -->
        <h3>Transfer Money</h3>
        <form method="post" action="">
            <label for="recipient">Transfer to (Username):</label>
            <input type="text" name="recipient" id="recipient" required>
            <br><br>
            <label for="amount">Amount:</label>
            <input type="number" name="amount" id="amount" step="0.01" required>
            <input type="submit" name="transfer" value="Transfer">
        </form>
        <?php if (isset($errorTransfer)) { echo "<p style='color:red;'>$errorTransfer</p>"; } ?>

        <!-- Transaction History -->
        <h3>Transaction History</h3>
        <table border="1">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $history = $account->getHistory();
                foreach ($history as $transaction) {
                    echo "<tr>";
                    echo "<td>" . $transaction['time'] . "</td>";
                    echo "<td>" . $transaction['type'] . "</td>";
                    echo "<td>" . ($transaction['debit'] > 0 ? "$" . $transaction['debit'] : "-") . "</td>";
                    echo "<td>" . ($transaction['credit'] > 0 ? "$" . $transaction['credit'] : "-") . "</td>";
                    echo "<td>$" . $transaction['balance'] . "</td>";
                    echo "<td>" . $transaction['description'] . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
        <br>
        <!-- Logout Button -->
        <form method="post" action="">
            <input type="submit" name="logout" value="Logout">
        </form>

    <?php endif; ?>

</body>
</html>
