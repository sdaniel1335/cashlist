<?php
class auth extends app
{
  public function __construct() { parent::__construct(true); }

  public function login()
  {
    if (auth_logged_in()) { $this->redirect('/'); }
    $this->set('title', 'Belépés · Cashlist');
    $this->render('auth/login');
  }

  public function login_post()
  {
    $this->require_csrf();
    $username = $this->post('username');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $rows = $this->rows('SELECT id, username, password_hash FROM ' . $this->table('users') . ' WHERE username = ' . $this->quoted($username) . ' LIMIT 1');
    if (!count($rows) || !auth_password_check($password, $rows[0]['password_hash'])) {
      $this->flash('error', 'Hibás felhasználónév vagy jelszó.');
      $this->redirect('/login');
    }
    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $rows[0]['id'];
    $_SESSION['auth_username'] = $rows[0]['username'];
    unset($_SESSION['_auth_csrf']);
    $this->redirect('/');
  }

  public function logout()
  {
    $this->require_csrf();
    unset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['_auth_csrf']);
    session_regenerate_id(true);
    $this->redirect('/login');
  }
}
