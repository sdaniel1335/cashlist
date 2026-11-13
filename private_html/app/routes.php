<?php
return array(
  'GET' => array(
    '/' => array('controller' => 'pages', 'class' => 'pages', 'action' => 'index'),
    '/login' => array('controller' => 'auth', 'class' => 'auth', 'action' => 'login'),
    '/transaction' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'transaction'),
    '/accounts' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'account_list'),
    '/settings' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'settings')
  ),
  'POST' => array(
    '/login' => array('controller' => 'auth', 'class' => 'auth', 'action' => 'login_post'),
    '/logout' => array('controller' => 'auth', 'class' => 'auth', 'action' => 'logout'),
    '/transaction' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'transaction_save'),
    '/transaction/delete' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'transaction_delete'),
    '/accounts' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'account_save'),
    '/accounts/archive' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'account_archive'),
    '/settings' => array('controller' => 'cash', 'class' => 'cash', 'action' => 'settings_save')
  )
);
