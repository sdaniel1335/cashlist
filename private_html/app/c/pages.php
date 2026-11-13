<?php
class pages extends app
{
  public function index()
  {
    try {
      $date = cash_date(isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'));
      $category = isset($_GET['category']) && $_GET['category'] !== '' ? cash_category($_GET['category']) : '';
      $account_id = isset($_GET['account_id']) && is_scalar($_GET['account_id']) ? (int) $_GET['account_id'] : 0;
    } catch (InvalidArgumentException $error) {
      $this->flash('error', $error->getMessage());
      $this->redirect('/');
    }
    $accounts = $this->accounts();
    $balances = array();
    $totals = array();
    foreach (array('B', 'P', 'W') as $code) { $totals[$code] = array('cash' => 0, 'bank' => 0); }
    $entries = $this->rows('SELECT e.account_id, e.category, SUM(e.amount) AS amount, SUM(e.main_amount) AS main_amount FROM '
      . $this->table('cash_entries') . ' e INNER JOIN ' . $this->table('cash_transactions') . ' t ON t.id = e.transaction_id AND t.user_id = e.user_id'
      . ' WHERE e.user_id = ' . $this->user_id() . ' AND t.booked_on <= ' . $this->quoted($date) . ' GROUP BY e.account_id, e.category');
    foreach ($entries as $entry) {
      $balances[$entry['account_id']][$entry['category']] = array('amount' => cash_stored_amount($entry['amount']), 'main_amount' => cash_stored_amount($entry['main_amount']));
    }
    foreach ($accounts as $account) {
      foreach (array('B', 'P', 'W') as $code) {
        if (isset($balances[$account['id']][$code])) {
          $totals[$code][$account['is_cash'] ? 'cash' : 'bank'] += $balances[$account['id']][$code]['main_amount'];
        }
      }
    }
    $where = ' WHERE t.user_id = ' . $this->user_id();
    if ($category !== '' || $account_id) {
      $where .= ' AND EXISTS (SELECT 1 FROM ' . $this->table('cash_entries') . ' f WHERE f.transaction_id = t.id AND f.user_id = t.user_id';
      if ($category !== '') { $where .= ' AND f.category = ' . $this->quoted($category); }
      if ($account_id) { $where .= ' AND f.account_id = ' . $account_id; }
      $where .= ')';
    }
    $count = $this->rows('SELECT COUNT(*) AS total FROM ' . $this->table('cash_transactions') . ' t' . $where);
    $page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $page_count = max(1, (int) ceil($count[0]['total'] / 50));
    $page = min($page, $page_count);
    $transactions = $this->rows('SELECT t.* FROM ' . $this->table('cash_transactions') . ' t' . $where . ' ORDER BY t.booked_on DESC, t.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
    $legs = array();
    if (count($transactions)) {
      $ids = array();
      foreach ($transactions as $transaction) { $ids[] = (int) $transaction['id']; }
      $entry_rows = $this->rows('SELECT e.*, a.name, a.currency FROM ' . $this->table('cash_entries') . ' e INNER JOIN ' . $this->table('cash_accounts') . ' a ON a.id = e.account_id AND a.user_id = e.user_id WHERE e.user_id = ' . $this->user_id() . ' AND e.transaction_id IN (' . implode(',', $ids) . ') ORDER BY e.id');
      foreach ($entry_rows as $entry) { $legs[$entry['transaction_id']][] = $entry; }
    }
    $this->set(array('title' => 'Cashlist', 'date' => $date, 'category' => $category, 'account_id' => $account_id, 'accounts' => $accounts,
      'balances' => $balances, 'totals' => $totals, 'transactions' => $transactions, 'legs' => $legs, 'main_currency' => $this->main_currency(),
      'page' => $page, 'page_count' => $page_count));
    $this->render('pages/index');
  }
}
