(function () {
  'use strict';
  var forms = document.querySelectorAll('[data-confirm]');
  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function (event) {
      if (!window.confirm(this.getAttribute('data-confirm'))) { event.preventDefault(); }
    });
  }
  var form = document.querySelector('.transaction-form');
  if (!form) { return; }
  var kind = form.querySelector('[name="kind"]');
  var account = form.querySelector('[name="account_id"]');
  var original_currency = form.querySelector('[name="original_currency"]');
  var main_currency = form.getAttribute('data-main-currency');
  function update_fields() {
    var option = account.options[account.selectedIndex];
    var currency = option ? option.getAttribute('data-currency') : null;
    form.querySelector('.transfer-fields').hidden = kind.value !== 'transfer';
    form.querySelector('.conversion-field').hidden = !currency || currency === main_currency;
    form.querySelector('[name="main_amount"]').required = !!currency && currency !== main_currency;
  }
  kind.addEventListener('change', update_fields);
  account.addEventListener('change', function () {
    var option = account.options[account.selectedIndex];
    if (!form.querySelector('[name="original_amount"]').value && option.getAttribute('data-currency')) {
      original_currency.value = option.getAttribute('data-currency');
    }
    update_fields();
  });
  form.addEventListener('submit', function () {
    var button = form.querySelector('button[type="submit"], .form-actions button');
    if (button) { button.disabled = true; button.textContent = 'Mentés…'; }
  });
  update_fields();
}());
