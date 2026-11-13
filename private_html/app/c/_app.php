<?php


function auth_random_bytes($length)
{
  if (function_exists('random_bytes')) { return random_bytes($length); }
  if (function_exists('openssl_random_pseudo_bytes')) {
    $strong = false;
    $bytes = openssl_random_pseudo_bytes($length, $strong);

    if ($strong && $bytes !== false && strlen($bytes) === $length) {
      return $bytes;
    }
  }

  $handle = @fopen('/dev/urandom', 'rb');

  if ($handle !== false) {
    $bytes = fread($handle, $length);
    fclose($handle);

    if ($bytes !== false && strlen($bytes) === $length) {
      return $bytes;
    }
  }

  throw new RuntimeException('Biztonságos véletlenszám-forrás szükséges (OpenSSL).');
}

function auth_hash_equals($known, $user)
{
  if (function_exists('hash_equals')) {
    return hash_equals($known, $user);
  }

  if (!is_string($known) || !is_string($user)) {
    return false;
  }

  $known_length = strlen($known);

  if ($known_length !== strlen($user)) {
    return false;
  }

  $result = 0;

  for ($i = 0; $i < $known_length; $i++) {
    $result |= ord($known[$i]) ^ ord($user[$i]);
  }

  return $result === 0;
}

function auth_pbkdf2($password, $salt, $iterations, $length)
{
  $hash_length = 32;
  $block_count = (int) ceil($length / $hash_length);
  $output = '';

  for ($block = 1; $block <= $block_count; $block++) {
    $last = hash_hmac(
      'sha256',
      $salt . pack('N', $block),
      $password,
      true
    );

    $xor = $last;

    for ($i = 1; $i < $iterations; $i++) {
      $last = hash_hmac('sha256', $last, $password, true);
      $xor = $xor ^ $last;
    }

    $output .= $xor;
  }

  return substr($output, 0, $length);
}

function auth_password_make($password)
{
  if (function_exists('password_hash')) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($hash !== false) {
      return $hash;
    }
  }

  $iterations = 60000;
  $salt = bin2hex(auth_random_bytes(16));
  $hash = bin2hex(
    auth_pbkdf2(
      $password,
      $salt,
      $iterations,
      32
    )
  );

  return 'pbkdf2_sha256$'
    . $iterations
    . '$'
    . $salt
    . '$'
    . $hash;
}

function auth_password_check($password, $stored_hash)
{
  if (strpos($stored_hash, 'pbkdf2_sha256$') === 0) {
    $parts = explode('$', $stored_hash);

    if (count($parts) !== 4) {
      return false;
    }

    $iterations = (int) $parts[1];

    if ($iterations < 1000 || $iterations > 1000000) {
      return false;
    }

    $hash = bin2hex(
      auth_pbkdf2(
        $password,
        $parts[2],
        $iterations,
        32
      )
    );

    return auth_hash_equals($parts[3], $hash);
  }

  if (function_exists('password_verify')) {
    return password_verify($password, $stored_hash);
  }

  if (function_exists('crypt')) {
    $hash = crypt($password, $stored_hash);

    if (is_string($hash) && strlen($hash) === strlen($stored_hash)) {
      return auth_hash_equals($stored_hash, $hash);
    }
  }

  return false;
}

function auth_logged_in()
{
  return isset($_SESSION['auth_user_id'])
    && (int) $_SESSION['auth_user_id'] > 0;
}

function auth_user_id()
{
  return auth_logged_in()
    ? (int) $_SESSION['auth_user_id']
    : null;
}

function auth_username()
{
  return isset($_SESSION['auth_username'])
    ? $_SESSION['auth_username']
    : '';
}

function auth_csrf_token()
{
  if (
    ! isset($_SESSION['_auth_csrf'])
    || ! is_string($_SESSION['_auth_csrf'])
    || $_SESSION['_auth_csrf'] === ''
  ) {
    $_SESSION['_auth_csrf'] = bin2hex(auth_random_bytes(32));
  }

  return $_SESSION['_auth_csrf'];
}

function auth_csrf_valid($token)
{
  return isset($_SESSION['_auth_csrf'])
    && is_string($token)
    && auth_hash_equals($_SESSION['_auth_csrf'], $token);
}

require_once __DIR__ . '/money.php';

class app extends C
{
  public function __construct($public = false)
  {
    parent::__construct();
    if (!$public && !auth_logged_in()) { $this->redirect('/login'); }
  }

  protected function require_csrf()
  {
    if (!auth_csrf_valid(isset($_POST['_csrf']) ? $_POST['_csrf'] : '')) {
      header('HTTP/1.1 400 Bad Request');
      exit('A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.');
    }
  }

  protected function post($key, $default = '')
  {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
  }

  protected function quoted($value)
  {
    return "'" . $this->db->real_escape_string($value) . "'";
  }

  protected function rows($sql)
  {
    $result = $this->db->query($sql);
    $rows = array();
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    $result->free();
    return $rows;
  }

  protected function user_id() { return (int) auth_user_id(); }

  protected function main_currency()
  {
    $rows = $this->rows('SELECT main_currency FROM ' . $this->table('cash_settings') . ' WHERE user_id = ' . $this->user_id());
    return count($rows) ? $rows[0]['main_currency'] : 'HUF';
  }

  protected function accounts()
  {
    return $this->rows('SELECT * FROM ' . $this->table('cash_accounts') . ' WHERE user_id = ' . $this->user_id() . ' ORDER BY archived, is_cash DESC, name, id');
  }

  protected function account($id, $allow_archived = false)
  {
    foreach ($this->accounts() as $account) {
      if ($account['id'] == $id && ($allow_archived || !$account['archived'])) { return $account; }
    }
    throw new InvalidArgumentException('A számla nem található vagy archivált.');
  }
}
