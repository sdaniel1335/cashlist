# Cashlist

Lightweight income and expense tracker built on [VC Framework](https://github.com/dsoos1290/vcframework) for PHP 5.3 and newer.

Cashlist manages per-user transactions across bank accounts, cash wallets and envelopes, with B/P/W categories, multiple currencies and date-based balances.

## Features

* Per-user income, expense, opening balance and transfer entries with add, edit and delete actions.
* B/P/W categories, including transfers between categories within the same account.
* Multiple accounts, wallets and envelopes with individual currencies and cash/non-cash classification.
* Account balances, total cash and non-cash balances, and separate B/P/W summaries.
* Configurable main currency and manually recorded conversions for foreign-currency transactions.
* Original transaction amount and currency stored separately from the amount charged to the account.
* Date-based balances, defaulting to today, with support for future transactions.
* Account renaming and archiving while preserving transaction history and balances.
* Category and account filters, paginated transaction lists and a responsive interface.
* Shared user login with sibling applications through a common `users` table and PHP session.
* CSRF protection, flash messages, duplicate-submission protection and edit-conflict detection.
* MySQLi, optional database table prefixes, Apache URL rewriting and subdirectory support.
* No Composer or external frontend dependency; PHP 5.3+ compatible.

## Requirements

* PHP 5.3+ with MySQLi.
* OpenSSL for secure random generation on older PHP versions without `random_bytes`.
* MySQL or MariaDB with InnoDB and utf8mb4 support.
* Apache with `mod_rewrite`.

## Installation

1. Download the [latest release](https://github.com/sdaniel1335/cashlist/releases/latest) or clone the repository.
2. Rename `private_html/app/config/db-sample.php` to `private_html/app/config/db.php` and configure the database.
3. Import `install.sql` into the configured database. If using `DB_PREFIX`, apply the prefix to all table names and foreign-key references in the SQL file before importing, including `users`.
4. Open the application and sign in with an existing shared account, or `admin` / `admin` on a fresh installation.
5. **Immediately change the default password** through your shared user-management application before exposing the application to the internet. Cashlist does not provide a password-change screen.
6. Choose the main currency in **Settings**, add your accounts and wallets, then record their opening balances under the appropriate B/P/W categories.

The installer preserves existing users. When installing alongside Lifelist, Todolist or Playlist, use the same database and table prefix. If an `admin` username already exists, its password is not replaced.

**Security:** Keep `private_html` inaccessible to direct HTTP requests, use HTTPS and set `APP_ENV` to `prod` for production.

## Accounts and transactions

Each account has a currency and a cash/non-cash type. For example, an OTP bank account can be marked as non-cash, while a wallet or envelope can be marked as cash. The currency and type are fixed after creation to preserve historical balances; names can be changed and accounts can be archived.

Use opening balance entries to record existing funds, separately for each B/P/W category. Opening balances may be negative. Transfers move money between accounts or categories without changing the overall main-currency total. Record bank fees as separate expenses.

For a 20 USD purchase charged as 7,400 HUF to a HUF account, record an expense of 7,400 HUF and enter 20 USD as the original amount. For accounts in another currency, also enter the main-currency value. Summaries use these recorded conversions, not live exchange rates. The main currency can only be changed before the first transaction.

Balances include transactions up to the end of the selected date. Future transactions are marked as planned and start contributing when the selected date reaches them. Update or delete plans if the actual transaction changes or does not occur. The transaction list also shows entries after the selected balance date, marked as excluded from that balance.

## Configuration

* Application settings: `private_html/app/config/app.php` (`APP_URL` is optional).
* Database settings: `private_html/app/config/db.php` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_CHAR`, `DB_TIME`, `DB_PREFIX`, `DB_PORT`).
* Main currency: configure through the authenticated application interface; defaults to HUF.
* Interface: Hungarian, with B/P/W category labels.
* Shared login: sibling applications must use the same domain, session name, session storage and cookie path covering all applications, typically `/`.

## Releases and changelog

Download the [latest release](https://github.com/sdaniel1335/cashlist/releases/latest).

See [CHANGELOG.md](CHANGELOG.md) for the release history.
