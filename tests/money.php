<?php
require dirname(__DIR__) . '/private_html/app/c/money.php';
function check_equal($actual, $expected, $label) {
  if ($actual != $expected) { fwrite(STDERR, $label . ': expected ' . $expected . ', got ' . $actual . "\n"); exit(1); }
}
check_equal(cash_amount('7400'), 740000, 'HUF');
check_equal(cash_amount('20,15'), 2015, 'comma');
check_equal(cash_decimal(cash_amount('-0.01', true)), '-0.01', 'negative cent');
check_equal(cash_decimal(cash_amount('999999999.99')), '999999999.99', 'large amount on 32-bit PHP');
check_equal(cash_decimal(cash_amount('0.10') + cash_amount('0.20')), '0.30', 'exact addition');
check_equal(cash_decimal(cash_stored_amount('-1234567890.12')), '-1234567890.12', 'aggregate');
check_equal(cash_date('2028-02-29'), '2028-02-29', 'leap day');
$invalid_values = array('', '1e3', 'NaN', '1.234', '-1', '1000000000', array('10'));
foreach ($invalid_values as $value) {
  try { cash_amount($value); fwrite(STDERR, "Accepted invalid money\n"); exit(1); }
  catch (InvalidArgumentException $error) {}
}
foreach (array('2026-02-29', '2026-11-31', '2026-1-01', '0000-01-01', array()) as $value) {
  try { cash_date($value); fwrite(STDERR, "Accepted invalid date\n"); exit(1); }
  catch (InvalidArgumentException $error) {}
}
check_equal(cash_category('B'), 'B', 'category');
check_equal(cash_currency('USD'), 'USD', 'currency');
echo "Money and date regression checks passed on PHP " . PHP_VERSION . "\n";
