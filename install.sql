-- Cashlist 0.1.0. Shared users table: never replace existing accounts.
-- If DB_PREFIX is configured, apply the same prefix to ALL table names below,
-- including users and every REFERENCES clause. Use the same DB_PREFIX in sibling apps.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin / admin
INSERT IGNORE INTO `users` (`username`,`password_hash`) VALUES ('admin','$2y$12$EOg47Nwzf6dRaggt3cx6YuD9v6p.pa.vJy96i1x/ZvsQGBr8uYkNe');

CREATE TABLE IF NOT EXISTS cash_settings (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  main_currency CHAR(3) NOT NULL DEFAULT 'HUF',
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  currency CHAR(3) NOT NULL,
  is_cash TINYINT UNSIGNED NOT NULL DEFAULT 0,
  archived TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY cash_account_owner (id,user_id),
  KEY cash_accounts_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL,
  booked_on DATE NOT NULL,
  note VARCHAR(1000) NOT NULL DEFAULT '',
  original_amount DECIMAL(18,2) NOT NULL,
  original_currency CHAR(3) NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  request_token CHAR(64) NOT NULL,
  UNIQUE KEY cash_transaction_owner (id,user_id),
  UNIQUE KEY cash_request (user_id,request_token),
  KEY cash_date (user_id,booked_on,id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  category CHAR(1) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  main_amount DECIMAL(18,2) NOT NULL,
  KEY cash_entry_transaction (transaction_id,user_id),
  KEY cash_entry_account (account_id,user_id),
  KEY cash_entry_user (user_id,category),
  FOREIGN KEY (transaction_id,user_id) REFERENCES cash_transactions(id,user_id) ON DELETE CASCADE,
  FOREIGN KEY (account_id,user_id) REFERENCES cash_accounts(id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
