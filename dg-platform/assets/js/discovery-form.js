(function () {
  'use strict';

  var cfg = window.dgDiscoveryForm || {};
  var form = document.getElementById('dgDiscoveryForm');
  if (!form) {
    return;
  }

  var steps = Array.prototype.slice.call(form.querySelectorAll('.discovery-step'));
  var progressFill = document.getElementById('discoveryProgressFill');
  var stepLabel = document.getElementById('discoveryStepLabel');
  var prevBtn = document.getElementById('discoveryPrev');
  var nextBtn = document.getElementById('discoveryNext');
  var submitBtn = document.getElementById('discoverySubmit');
  var messageEl = document.getElementById('discoveryFormMessage');
  var resultsEl = document.getElementById('discoveryResults');
  var current = 0;

  function showMessage(type, text) {
    if (!messageEl) return;
    messageEl.style.display = 'block';
    messageEl.textContent = text;
    messageEl.className = 'discovery-message discovery-message--' + type;
  }

  function validateStep(index) {
    var step = steps[index];
    if (!step) return true;
    var fields = step.querySelectorAll('[required]');
    for (var i = 0; i < fields.length; i++) {
      if (!fields[i].checkValidity()) {
        fields[i].reportValidity();
        return false;
      }
    }
    return true;
  }

  function renderStep() {
    steps.forEach(function (step, i) {
      step.style.display = i === current ? 'block' : 'none';
    });
    if (progressFill) {
      progressFill.style.width = ((current + 1) / steps.length) * 100 + '%';
    }
    if (stepLabel) {
      stepLabel.textContent = 'Step ' + (current + 1) + ' of ' + steps.length;
    }
    if (prevBtn) prevBtn.style.display = current === 0 ? 'none' : 'inline-flex';
    if (nextBtn) nextBtn.style.display = current === steps.length - 1 ? 'none' : 'inline-flex';
    if (submitBtn) submitBtn.style.display = current === steps.length - 1 ? 'inline-flex' : 'none';
  }

  function collectFormData() {
    var data = new FormData(form);
    var payload = {};
    data.forEach(function (value, key) {
      if (key.endsWith('[]')) {
        var k = key.slice(0, -2);
        if (!payload[k]) payload[k] = [];
        payload[k].push(value);
      } else if (payload[key] !== undefined) {
        if (!Array.isArray(payload[key])) payload[key] = [payload[key]];
        payload[key].push(value);
      } else {
        payload[key] = value;
      }
    });
    ['challenges', 'integrations', 'growth_objectives', 'interested_in'].forEach(function (field) {
      payload[field] = data.getAll(field + '[]');
    });
    return payload;
  }

  function renderResults(result) {
    if (!resultsEl) return;
    form.style.display = 'none';
    var progress = document.querySelector('.discovery-progress');
    if (progress) progress.style.display = 'none';
    var nav = document.querySelector('.discovery-nav');
    if (nav) nav.style.display = 'none';

    var rec = result.recommendation || {};
    var grade = result.maturity_grade || rec.maturity_grade || '—';
    var score = result.maturity_score || rec.maturity_score || '—';
    var tier = rec.platform_tier_label || 'Growth';
    var apps = (rec.recommended_apps || []).join(', ') || 'Core Platform';
    var priorities = (result.priorities || []).map(function (p) {
      return '<li>' + p + '</li>';
    }).join('');

    resultsEl.innerHTML =
      '<div class="discovery-results-inner">' +
      '<span class="sub-label">Your Digital Maturity Snapshot</span>' +
      '<h2>Grade ' + grade + ' · ' + score + '/100</h2>' +
      '<p class="results-lead">' + (result.summary || 'We\'ve received your discovery and calculated an initial maturity score.') + '</p>' +
      '<div class="results-grid">' +
      '<div class="results-card"><h3>Recommended Platform</h3><p class="results-value">' + tier + '</p></div>' +
      '<div class="results-card"><h3>Suggested Apps</h3><p class="results-value-sm">' + apps + '</p></div>' +
      '</div>' +
      (priorities ? '<h3>Priority opportunities</h3><ul class="results-list">' + priorities + '</ul>' : '') +
      '<p class="results-note">A confirmation email is on its way. Our team will review your discovery before your consultation.</p>' +
      '<a href="https://digitalgate.com.au/contact/" class="btn-primary">Book Your Free Consultation →</a>' +
      '<a href="https://digitalgate.com.au/onboarding/" class="btn-secondary">Start Free Trial</a>' +
      '</div>';
    resultsEl.style.display = 'block';
    resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      if (current > 0) {
        current -= 1;
        renderStep();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      if (!validateStep(current)) return;
      if (current < steps.length - 1) {
        current += 1;
        renderStep();
      }
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateStep(current)) return;

    var payload = collectFormData();
    delete payload.action;
    delete payload._wpnonce;
    delete payload.website;

    submitBtn.disabled = true;
    showMessage('info', 'Analysing your business and generating recommendations…');

    fetch(cfg.restUrl || '/wp-json/digitalgate/v1/discovery', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.message || 'Submission failed');
        }
        renderResults(data);
      })
      .catch(function (err) {
        showMessage('error', err.message || 'Something went wrong. Please try again or contact us at hello@digitalgate.com.au.');
        submitBtn.disabled = false;
      });
  });

  var params = new URLSearchParams(window.location.search);
  if (params.get('discovery_sent') === '1' && resultsEl) {
    resultsEl.innerHTML =
      '<div class="discovery-results-inner">' +
      '<h2>Thank you — discovery received</h2>' +
      '<p class="results-lead">We\'ve saved your submission and sent a summary to your inbox. Our team will be in touch shortly.</p>' +
      '<a href="https://digitalgate.com.au/contact/" class="btn-primary">Book Your Free Consultation →</a>' +
      '</div>';
    resultsEl.style.display = 'block';
    form.style.display = 'none';
  } else if (params.get('discovery_error')) {
    showMessage('error', 'We couldn\'t process your submission. Please try again or email hello@digitalgate.com.au.');
  }

  renderStep();
})();
