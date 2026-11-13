# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2026-11-13

### Added

- Initial Cashlist application built on [VC Framework](https://github.com/dsoos1290/vcframework) with PHP 5.3+ compatibility.
- MySQLi-backed per-user income, expense, opening balance and transfer management.
- B/P/W categories with transfers between accounts or categories.
- Multiple bank accounts, cash wallets and envelopes with individual currencies and cash/non-cash classification.
- Per-account balances, total cash and non-cash balances, and separate B/P/W summaries.
- Configurable main currency and manually recorded foreign-currency conversions.
- Separate storage of original transaction amounts and account-currency amounts.
- Date-based balances and future transactions with planned and balance-exclusion indicators.
- Transaction editing and deletion, account renaming and archiving, filtering and pagination.
- Shared user authentication through a common users table and PHP session.
- CSRF protection, flash messages, atomic transfer saves, duplicate-submission protection and edit-conflict detection.
- Responsive Hungarian interface without external frontend dependencies.
- Optional database table prefixes, absolute application URL and subdirectory support.
- Apache URL rewriting and no Composer dependency.
