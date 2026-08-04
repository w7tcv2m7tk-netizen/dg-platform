(function () {
  if (typeof dgSupportChat === 'undefined') {
    return;
  }

  var state = {
    open: false,
    conversationId: 0,
    lastMessageId: 0,
    pollTimer: null,
    loading: false,
  };

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html) node.innerHTML = html;
    return node;
  }

  function api(path, options) {
    options = options || {};
    var headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': dgSupportChat.nonce,
    };
    return fetch(dgSupportChat.restUrl + path, {
      method: options.method || 'GET',
      headers: headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin',
    }).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (data) {
          throw new Error((data && data.message) || 'Request failed');
        });
      }
      return res.json();
    });
  }

  function formatTime(at) {
    if (!at) return '';
    var d = new Date(at.replace(' ', 'T'));
    if (isNaN(d.getTime())) return at;
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function renderMessages(messages, append) {
    if (!messagesEl) return;
    if (!append) {
      messagesEl.innerHTML = '';
    }
    if (!messages.length && !append) {
      messagesEl.innerHTML = '<div class="dg-support-empty">Say hello — we typically reply within a few hours on business days.</div>';
      return;
    }
    messages.forEach(function (msg) {
      if (msg.id <= state.lastMessageId && append) {
        return;
      }
      state.lastMessageId = Math.max(state.lastMessageId, msg.id);
      var bubble = el('div', 'dg-support-msg ' + msg.role);
      bubble.innerHTML = '<span class="dg-support-meta">' + msg.sender + ' · ' + formatTime(msg.at) + '</span>' + escapeHtml(msg.body);
      messagesEl.appendChild(bubble);
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
      .replace(/\n/g, '<br>');
  }

  function loadConversation() {
    if (state.loading) return;
    state.loading = true;
    return api('conversation')
      .then(function (data) {
        state.conversationId = data.conversation_id || 0;
        state.lastMessageId = 0;
        renderMessages(data.messages || [], false);
      })
      .catch(function (err) {
        if (messagesEl) {
          messagesEl.innerHTML = '<div class="dg-support-empty">Unable to load chat. Please refresh or email support@digitalgate.com.au</div>';
        }
        console.error(err);
      })
      .finally(function () {
        state.loading = false;
      });
  }

  function pollMessages() {
    if (!state.open || !state.conversationId) return;
    var path = 'messages?after=' + state.lastMessageId;
    api(path)
      .then(function (data) {
        if (data.messages && data.messages.length) {
          renderMessages(data.messages, true);
        }
      })
      .catch(function () {});
  }

  function sendMessage(text) {
    text = String(text || '').trim();
    if (!text || state.loading) return;
    state.loading = true;
    return api('messages', { method: 'POST', body: { message: text } })
      .then(function (data) {
        if (data.messages) {
          state.lastMessageId = 0;
          renderMessages(data.messages, false);
        }
      })
      .catch(function (err) {
        alert(err.message || 'Could not send message');
      })
      .finally(function () {
        state.loading = false;
      });
  }

  function openPanel() {
    state.open = true;
    panel.classList.add('is-open');
    launcher.setAttribute('aria-expanded', 'true');
    loadConversation();
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(pollMessages, dgSupportChat.pollMs || 4000);
  }

  function closePanel() {
    state.open = false;
    panel.classList.remove('is-open');
    launcher.setAttribute('aria-expanded', 'false');
    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }
  }

  var launcher = el('button', 'dg-support-launcher', '<i class="fas fa-comment-dots"></i>');
  launcher.setAttribute('type', 'button');
  launcher.setAttribute('aria-label', 'Open live support chat');
  launcher.setAttribute('aria-expanded', 'false');

  var panel = el('div', 'dg-support-panel');
  panel.innerHTML =
    '<div class="dg-support-header">' +
      '<div><h3>Live support</h3><p>Chat with DigitalGate — replies by email &amp; here</p></div>' +
      '<button type="button" class="dg-support-close" aria-label="Close chat">&times;</button>' +
    '</div>' +
    '<div class="dg-support-messages"></div>' +
    '<form class="dg-support-compose">' +
      '<textarea rows="2" placeholder="Type your message…" required></textarea>' +
      '<button type="submit">Send</button>' +
    '</form>';

  document.body.appendChild(launcher);
  document.body.appendChild(panel);

  var messagesEl = panel.querySelector('.dg-support-messages');
  var form = panel.querySelector('.dg-support-compose');
  var textarea = form.querySelector('textarea');

  launcher.addEventListener('click', function () {
    if (state.open) closePanel();
    else openPanel();
  });

  panel.querySelector('.dg-support-close').addEventListener('click', closePanel);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var value = textarea.value;
    textarea.value = '';
    sendMessage(value);
  });

  document.addEventListener('click', function (e) {
    var target = e.target.closest('.dg-support-open');
    if (target) {
      e.preventDefault();
      openPanel();
    }
  });

  window.DGSupportChat = {
    open: openPanel,
    close: closePanel,
  };
})();
