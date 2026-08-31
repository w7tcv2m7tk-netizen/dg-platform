(function () {
  var cfg = window.dgFoundingSetup || {};
  var form = document.getElementById('f10setup');
  if (!form) return;
  var panels = Array.prototype.slice.call(form.querySelectorAll('.dg-f10-panel'));
  var stepsEl = document.getElementById('f10steps');
  var err = document.getElementById('f10err');
  var prev = document.getElementById('f10prev');
  var next = document.getElementById('f10next');
  var pay = document.getElementById('f10pay');
  var step = 0;

  function showError(msg) {
    err.textContent = msg;
    err.style.display = 'block';
  }

  function payload() {
    var data = new FormData(form);
    var body = { token: data.get('token') };
    data.forEach(function (value, key) {
      if (key.slice(-2) === '[]') {
        var name = key.slice(0, -2);
        if (!body[name]) body[name] = [];
        body[name].push(value);
      } else {
        body[key] = value;
      }
    });
    ['apps', 'premium', 'addons'].forEach(function (k) {
      if (!body[k]) body[k] = [];
    });
    return body;
  }

  function render() {
    panels.forEach(function (p, i) { p.classList.toggle('is-on', i === step); });
    if (stepsEl && cfg.labels) {
      stepsEl.innerHTML = cfg.labels.map(function (label, i) {
        return '<button type="button" data-go="' + i + '" class="' + (i === step ? 'is-on' : '') + '">' + (i + 1) + '. ' + label + '</button>';
      }).join('');
    }
    prev.style.visibility = step === 0 ? 'hidden' : 'visible';
    next.style.display = step === panels.length - 1 ? 'none' : 'inline-flex';
    pay.style.display = step === panels.length - 1 ? 'inline-flex' : 'none';
  }

  function save() {
    err.style.display = 'none';
    return fetch(cfg.restSetup, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload())
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Save failed'); return j; }); });
  }

  if (stepsEl) {
    stepsEl.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      step = parseInt(btn.getAttribute('data-go'), 10) || 0;
      render();
    });
  }

  next.addEventListener('click', function () {
    save().then(function () {
      step = Math.min(step + 1, panels.length - 1);
      render();
    }).catch(function (e) { showError(e.message); });
  });

  prev.addEventListener('click', function () {
    step = Math.max(step - 1, 0);
    render();
  });

  pay.addEventListener('click', function () {
    pay.disabled = true;
    save().then(function () {
      return fetch(cfg.restCheckout, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload())
      });
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Checkout failed'); return j; }); })
      .then(function (j) {
        if (!j.url) throw new Error('Stripe Checkout URL missing.');
        window.location.href = j.url;
      })
      .catch(function (e) {
        pay.disabled = false;
        showError(e.message);
      });
  });

  render();
})();
