<?php
class cash extends app
{
  public function transaction()
  {
    $id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;
    $record = array('id' => 0, 'kind' => 'expense', 'booked_on' => date('Y-m-d'), 'note' => '', 'original_amount' => '', 'original_currency' => $this->main_currency());
    $entries = array();
    if ($id) {
      $rows = $this->rows('SELECT * FROM ' . $this->table('cash_transactions') . ' WHERE id = ' . $id . ' AND user_id = ' . $this->user_id());
      if (!count($rows)) { header('HTTP/1.1 404 Not Found'); exit('A tétel nem található.'); }
      $record = $rows[0];
      $entries = $this->rows('SELECT * FROM ' . $this->table('cash_entries') . ' WHERE transaction_id = ' . $id . ' AND user_id = ' . $this->user_id() . ' ORDER BY id');
    }
    if (isset($_SESSION['cash_draft']) && (int) $_SESSION['cash_draft']['id'] === $id) {
      $draft = $_SESSION['cash_draft'];
      unset($_SESSION['cash_draft']);
    } else { $draft = array(); }
    $this->set(array('title' => $id ? 'Tétel szerkesztése' : 'Új tétel', 'record' => $record, 'entries' => $entries, 'draft' => $draft, 'accounts' => $this->accounts(), 'main_currency' => $this->main_currency()));
    $this->render('cash/transaction');
  }

  public function transaction_save()
  {
    $this->require_csrf();
    $id = (int) $this->post('id', '0');
    $this->db->autocommit(false);
    try {
      /* Serialize writes per user, including the initial currency setup. */
      $this->rows('SELECT id FROM ' . $this->table('users') . ' WHERE id = ' . $this->user_id() . ' FOR UPDATE');
      if ($id) {
        $rows = $this->rows('SELECT id FROM ' . $this->table('cash_transactions') . ' WHERE id = ' . $id . ' AND user_id = ' . $this->user_id() . ' FOR UPDATE');
        if (!count($rows)) { throw new InvalidArgumentException('A tétel nem található.'); }
      }
      $kind = $this->post('kind');
      if (!in_array($kind, array('income', 'expense', 'transfer', 'opening'), true)) { throw new InvalidArgumentException('Érvénytelen típus.'); }
      $date = cash_date($this->post('booked_on'));
      $category = cash_category($this->post('category'));
      $account = $this->account((int) $this->post('account_id'), $id > 0);
      $amount = cash_amount($this->post('amount'), $kind === 'opening');
      if ($amount == 0 || ($kind !== 'opening' && $amount < 0)) { throw new InvalidArgumentException('Az összeg legyen pozitív; nyitóegyenleg lehet negatív is.'); }
      $main_currency = $this->main_currency();
      $main_amount = $account['currency'] === $main_currency ? $amount : cash_amount($this->post('main_amount'), $kind === 'opening');
      if ($main_amount == 0 || (($main_amount < 0) !== ($amount < 0))) { throw new InvalidArgumentException('A fődevizás összeg előjele egyezzen a számla összegével.'); }
      $original_currency = $this->post('original_amount') === '' ? $account['currency'] : cash_currency($this->post('original_currency', $account['currency']));
      $original_amount = $this->post('original_amount') === '' ? abs($amount) : cash_amount($this->post('original_amount'));
      if ($original_amount <= 0) { throw new InvalidArgumentException('Az eredeti összeg legyen pozitív.'); }
      if ($original_currency === $account['currency']) { $original_amount = abs($amount); }
      $note = $this->post('note');
      if (strlen($note) > 1000) { throw new InvalidArgumentException('A megjegyzés legfeljebb 1000 bájt lehet.'); }
      $target = false;
      if ($kind === 'transfer') {
        $target = $this->account((int) $this->post('target_account_id'), $id > 0);
        $target_category = cash_category($this->post('target_category'));
        if ($target['id'] === $account['id'] && $category === $target_category) { throw new InvalidArgumentException('Válassz másik célszámlát vagy kategóriát.'); }
        $target_amount = $target['currency'] === $account['currency'] ? $amount : cash_amount($this->post('target_amount'));
        if ($target_amount <= 0) { throw new InvalidArgumentException('A célösszeg legyen pozitív.'); }
        if ($target['currency'] === $main_currency && $target_amount != $main_amount) { throw new InvalidArgumentException('A célösszeg egyezzen a fődevizás összeggel. Az esetleges díjat külön kiadásként vedd fel.'); }
      }
      $values = 'kind = ' . $this->quoted($kind) . ', booked_on = ' . $this->quoted($date) . ', note = ' . $this->quoted($note)
        . ', original_amount = ' . $this->quoted(cash_decimal($original_amount)) . ', original_currency = ' . $this->quoted($original_currency);
      if ($id) {
        $this->db->query('UPDATE ' . $this->table('cash_transactions') . ' SET ' . $values . ', revision = revision + 1 WHERE id = ' . $id . ' AND user_id = ' . $this->user_id() . ' AND revision = ' . (int) $this->post('revision'));
        if ($this->db->affected_rows !== 1) { throw new InvalidArgumentException('A tétel közben megváltozott. Nyisd meg újra a listából.'); }
        $this->db->query('DELETE FROM ' . $this->table('cash_entries') . ' WHERE transaction_id = ' . $id . ' AND user_id = ' . $this->user_id());
      } else {
        $request_token = $this->post('request_token');
        if (!preg_match('/^[a-f0-9]{64}$/', $request_token)) { throw new InvalidArgumentException('Frissítsd az űrlapot.'); }
        $existing = $this->rows('SELECT id FROM ' . $this->table('cash_transactions') . ' WHERE user_id = ' . $this->user_id() . ' AND request_token = ' . $this->quoted($request_token));
        if (count($existing)) { $this->db->rollback(); $this->db->autocommit(true); $this->redirect('/'); }
        $this->db->query('INSERT INTO ' . $this->table('cash_transactions') . ' SET user_id = ' . $this->user_id() . ', request_token = ' . $this->quoted($request_token) . ', ' . $values);
        $id = $this->db->insert_id;
      }
      $sign = $kind === 'expense' || $kind === 'transfer' ? -1 : 1;
      $this->save_entry($id, $account['id'], $category, $sign * $amount, $sign * $main_amount);
      if ($target) { $this->save_entry($id, $target['id'], $target_category, $target_amount, $main_amount); }
      $this->db->commit();
      $this->db->autocommit(true);
      unset($_SESSION['cash_draft']);
      $this->flash('success', 'Tétel elmentve.');
      $this->redirect('/?date=' . $date);
    } catch (Exception $error) {
      $this->db->rollback();
      $this->db->autocommit(true);
      if (!($error instanceof InvalidArgumentException)) { error_log($error->getMessage()); }
      $this->flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'A mentés nem sikerült. Próbáld újra.');
      $_SESSION['cash_draft'] = array();
      foreach (array('id', 'revision', 'kind', 'booked_on', 'category', 'account_id', 'amount', 'main_amount', 'original_amount', 'original_currency', 'target_account_id', 'target_category', 'target_amount', 'note', 'request_token') as $key) {
        $_SESSION['cash_draft'][$key] = $this->post($key);
      }
      $this->redirect('/transaction' . ((int) $this->post('id') ? '?id=' . (int) $this->post('id') : ''));
    }
  }

  protected function save_entry($id, $account_id, $category, $amount, $main_amount)
  {
    $this->db->query('INSERT INTO ' . $this->table('cash_entries') . ' (transaction_id,user_id,account_id,category,amount,main_amount) VALUES ('
      . (int) $id . ',' . $this->user_id() . ',' . (int) $account_id . ',' . $this->quoted($category) . ',' . $this->quoted(cash_decimal($amount)) . ',' . $this->quoted(cash_decimal($main_amount)) . ')');
  }

  public function transaction_delete()
  {
    $this->require_csrf();
    $this->db->query('DELETE FROM ' . $this->table('cash_transactions') . ' WHERE id = ' . (int) $this->post('id') . ' AND user_id = ' . $this->user_id() . ' AND revision = ' . (int) $this->post('revision'));
    $this->flash($this->db->affected_rows ? 'success' : 'error', $this->db->affected_rows ? 'Tétel törölve, az egyenlegek frissültek.' : 'A tétel megváltozott vagy már nem létezik.');
    $this->redirect('/');
  }

  public function account_list()
  {
    $this->set(array('title' => 'Számlák és zsebek', 'accounts' => $this->accounts(), 'main_currency' => $this->main_currency()));
    $this->render('cash/accounts');
  }

  public function account_save()
  {
    $this->require_csrf();
    try {
      $id = (int) $this->post('id');
      $name = $this->post('name');
      if ($name === '' || strlen($name) > 120) { throw new InvalidArgumentException('A név 1–120 bájt hosszú legyen.'); }
      $is_cash = $this->post('is_cash') === '1' ? 1 : 0;
      if ($id) {
        $account = $this->account($id, true);
        /* Classification and currency are immutable: history must stay meaningful. */
        $this->db->query('UPDATE ' . $this->table('cash_accounts') . ' SET name = ' . $this->quoted($name) . ' WHERE id = ' . $id . ' AND user_id = ' . $this->user_id());
      } else {
        $currency = cash_currency($this->post('currency'));
        $this->db->query('INSERT INTO ' . $this->table('cash_accounts') . ' (user_id,name,currency,is_cash) VALUES (' . $this->user_id() . ',' . $this->quoted($name) . ',' . $this->quoted($currency) . ',' . $is_cash . ')');
      }
      $this->flash('success', 'Számla elmentve. A kezdőösszeget nyitóegyenlegként vedd fel.');
    } catch (Exception $error) {
      if (!($error instanceof InvalidArgumentException)) { error_log($error->getMessage()); }
      $this->flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'A számlát nem sikerült elmenteni.');
    }
    $this->redirect('/accounts');
  }

  public function account_archive()
  {
    $this->require_csrf();
    $this->db->query('UPDATE ' . $this->table('cash_accounts') . ' SET archived = 1 - archived WHERE id = ' . (int) $this->post('id') . ' AND user_id = ' . $this->user_id());
    $this->flash('success', 'A számla állapota frissült. Az előzmények és az egyenlegek megmaradnak.');
    $this->redirect('/accounts');
  }

  public function settings()
  {
    $this->set(array('title' => 'Beállítások', 'main_currency' => $this->main_currency()));
    $this->render('cash/settings');
  }

  public function settings_save()
  {
    $this->require_csrf();
    $this->db->autocommit(false);
    try {
      $this->rows('SELECT id FROM ' . $this->table('users') . ' WHERE id = ' . $this->user_id() . ' FOR UPDATE');
      $currency = cash_currency($this->post('main_currency'));
      $rows = $this->rows('SELECT id FROM ' . $this->table('cash_transactions') . ' WHERE user_id = ' . $this->user_id() . ' LIMIT 1');
      if (count($rows) && $currency !== $this->main_currency()) { throw new InvalidArgumentException('A fődeviza csak az első tétel előtt módosítható, hogy a korábbi összegek értéke megmaradjon.'); }
      $this->db->query('INSERT INTO ' . $this->table('cash_settings') . ' (user_id,main_currency) VALUES (' . $this->user_id() . ',' . $this->quoted($currency) . ') ON DUPLICATE KEY UPDATE main_currency = VALUES(main_currency)');
      $this->db->commit();
      $this->flash('success', 'Beállítások elmentve.');
    } catch (Exception $error) {
      $this->db->rollback();
      if (!($error instanceof InvalidArgumentException)) { error_log($error->getMessage()); }
      $this->flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'A beállítást nem sikerült menteni.');
    }
    $this->db->autocommit(true);
    $this->redirect('/settings');
  }
}
