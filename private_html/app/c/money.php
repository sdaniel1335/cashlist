<?php
/* Monetary arithmetic uses whole hundredths, never unrounded currency floats. */
function cash_amount($value, $signed = false)
{
  if (!is_string($value) || !preg_match($signed ? '/^-?\d{1,9}([.,]\d{1,2})?$/' : '/^\d{1,9}([.,]\d{1,2})?$/', trim($value))) {
    throw new InvalidArgumentException('Az összeg legfeljebb 9 egész és 2 tizedesjegyből állhat.');
  }
  $value = str_replace(',', '.', trim($value));
  $negative = substr($value, 0, 1) === '-';
  $parts = explode('.', ltrim($value, '-'));
  $cents = $parts[0] * 100 + (int) str_pad(isset($parts[1]) ? $parts[1] : '', 2, '0');
  return $negative ? -$cents : $cents;
}

function cash_decimal($cents)
{
  return ($cents < 0 ? '-' : '') . sprintf('%.0f', floor(abs($cents) / 100)) . '.' . sprintf('%02d', fmod(abs($cents), 100));
}

/* Aggregate database values may exceed the individual input limit. */
function cash_stored_amount($value)
{
  return round((float) $value * 100);
}

function cash_date($value)
{
  if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
    || $parts[1] < 1900 || $parts[1] > 9999 || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
    throw new InvalidArgumentException('Érvényes dátumot adj meg (1900–9999).');
  }
  return $value;
}

function cash_currency($value)
{
  if (!is_string($value) || !preg_match('/^[A-Z]{3}$/', $value)) {
    throw new InvalidArgumentException('A devizanem három nagybetűs kód legyen, például HUF, EUR vagy USD.');
  }
  return $value;
}

function cash_category($value)
{
  if (!in_array($value, array('B', 'P', 'W'), true)) {
    throw new InvalidArgumentException('Válassz B, P vagy W kategóriát.');
  }
  return $value;
}

function cash_e($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cash_format($cents, $currency)
{
  return number_format($cents / 100, fmod($cents, 100) == 0 ? 0 : 2, ',', ' ') . ' ' . $currency;
}
