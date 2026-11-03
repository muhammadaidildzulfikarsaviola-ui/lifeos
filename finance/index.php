<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Finance';
$currentPage = 'finance';

require LIFEOS_ROOT . '/includes/header.php';

$financeNavigation = [
    ['Overview', 'index.php'],
    ['Transactions', 'transactions.php'],
    ['Accounts', 'accounts.php'],
    ['Budgets', 'budgets.php'],
    ['Loans', 'loans.php'],
    ['Bills', 'bills.php'],
];
?>

<div class="finance-page">

    <!-- Page heading -->
    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Finance</h1>
            <p class="finance-subtitle">
                Manage your money, understand your spending,
                and stay in control of your finances.
            </p>
        </div>

        <button class="finance-primary-button" type="button" disabled>
            <span>＋</span> Add transaction
        </button>
    </section>

    <!-- Finance navigation -->
    <nav class="finance-navigation" aria-label="Finance navigation">
        <?php foreach ($financeNavigation as $index => [$label, $path]): ?>
            <a
                href="<?= e($path) ?>"
                class="<?= $index === 0 ? 'active' : '' ?>"
                <?= $index === 0 ? 'aria-current="page"' : '' ?>
            >
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Summary -->
    <section class="finance-section">

        <div class="finance-section-heading">
            <div>
                <h2>Financial overview</h2>
                <p>Your financial position at a glance.</p>
            </div>

            <span class="finance-period">All accounts</span>
        </div>

        <div class="finance-summary-grid">

            <article class="finance-summary-card balance-card">
                <div class="summary-card-heading">
                    <span>Total balance</span>
                    <span class="summary-icon">◈</span>
                </div>

                <div class="summary-value">—</div>
                <div class="summary-caption">Across all accounts</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Total income</span>
                    <span class="summary-icon income-icon">↗</span>
                </div>

                <div class="summary-value">—</div>
                <div class="summary-caption">This month</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Total expenses</span>
                    <span class="summary-icon expense-icon">↙</span>
                </div>

                <div class="summary-value">—</div>
                <div class="summary-caption">This month</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Net cash flow</span>
                    <span class="summary-icon">⌁</span>
                </div>

                <div class="summary-value">—</div>
                <div class="summary-caption">Income minus expenses</div>
            </article>

        </div>
    </section>

    <!-- Analytics -->
    <section class="finance-section">

        <div class="finance-section-heading">
            <div>
                <h2>Cash flow</h2>
                <p>Track your income and expenses over time.</p>
            </div>

            <span class="finance-period">Monthly</span>
        </div>

        <article class="finance-panel cashflow-panel">
            <div class="finance-empty-state">
                <div class="empty-state-icon">▥</div>
                <h3>No financial activity yet</h3>
                <p>
                    Your income and expenses will appear here
                    once you start recording transactions.
                </p>
            </div>
        </article>

    </section>

    <!-- Lower dashboard -->
    <section class="finance-lower-grid">

        <article class="finance-panel">

            <div class="finance-panel-heading">
                <div>
                    <h2>Recent transactions</h2>
                    <p>Your latest financial activity.</p>
                </div>

                <a href="transactions.php">View all ↗</a>
            </div>

            <div class="finance-empty-state compact-empty">
                <div class="empty-state-icon">↔</div>
                <h3>No transactions recorded</h3>
                <p>Transactions will appear here.</p>
            </div>

        </article>

        <article class="finance-panel">

            <div class="finance-panel-heading">
                <div>
                    <h2>My accounts</h2>
                    <p>Your wallets and bank accounts.</p>
                </div>

                <a href="accounts.php">View all ↗</a>
            </div>

            <div class="finance-empty-state compact-empty">
                <div class="empty-state-icon">▣</div>
                <h3>No accounts added</h3>
                <p>Add an account to start tracking balances.</p>
            </div>

        </article>

        <article class="finance-panel">

            <div class="finance-panel-heading">
                <div>
                    <h2>Budget overview</h2>
                    <p>Monitor your spending limits.</p>
                </div>

                <a href="budgets.php">View all ↗</a>
            </div>

            <div class="finance-empty-state compact-empty">
                <div class="empty-state-icon">◫</div>
                <h3>No budgets created</h3>
                <p>Your budget progress will appear here.</p>
            </div>

        </article>

        <article class="finance-panel">

            <div class="finance-panel-heading">
                <div>
                    <h2>Upcoming bills</h2>
                    <p>Keep track of recurring payments.</p>
                </div>

                <a href="bills.php">View all ↗</a>
            </div>

            <div class="finance-empty-state compact-empty">
                <div class="empty-state-icon">▤</div>
                <h3>No upcoming bills</h3>
                <p>Your payment reminders will appear here.</p>
            </div>

        </article>

    </section>

    <!-- Loans -->
    <section class="finance-section">
        <article class="finance-panel">

            <div class="finance-panel-heading">
                <div>
                    <h2>Personal loans</h2>
                    <p>Track money borrowed and repayments.</p>
                </div>

                <a href="loans.php">Manage loans ↗</a>
            </div>

            <div class="finance-empty-state compact-empty">
                <div class="empty-state-icon">⇄</div>
                <h3>No active loans</h3>
                <p>Your borrowing and repayment records will appear here.</p>
            </div>

        </article>
    </section>

</div>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>