// UmaLink - small helper script (validation styling, delete modal, date rules)
(function () {
  'use strict';

  // 1) Bootstrap client-side validation on every form that has .needs-validation
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  // 2) Delete confirmation modal: fills in the record name and the target form
  var modal = document.getElementById('deleteModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      modal.querySelector('form').setAttribute('action', btn.getAttribute('data-action'));
      modal.querySelector('input[name="id"]').value = btn.getAttribute('data-id');
      modal.querySelector('.delete-label').textContent = btn.getAttribute('data-label');
    });
  }

  // 3) Planting form: expected harvest date must be after the planting date
  var planted = document.getElementById('planting_date');
  var expected = document.getElementById('expected_harvest_date');
  if (planted && expected) {
    var sync = function () {
      if (planted.value) {
        var d = new Date(planted.value);
        d.setDate(d.getDate() + 1);
        expected.min = d.toISOString().slice(0, 10);
      }
    };
    planted.addEventListener('change', sync);
    sync();
  }

  // 4) Report/filter date range: "to" cannot be before "from"
  var from = document.getElementById('date_from');
  var to = document.getElementById('date_to');
  if (from && to) {
    var limit = function () { to.min = from.value || ''; };
    from.addEventListener('change', limit);
    limit();
  }

  // 5) Harvest form: live estimated value = quantity x selling price per kg
  var qty = document.getElementById('quantity_kg');
  var price = document.getElementById('selling_price_per_kg');
  var est = document.getElementById('est_value');
  if (qty && price && est) {
    var calc = function () {
      var v = parseFloat(qty.value) * parseFloat(price.value);
      est.value = isNaN(v) ? '' : '\u20B1' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    qty.addEventListener('input', calc);
    price.addEventListener('input', calc);
    calc();
  }
})();
