(function () {
  const config = window.mondayCandidateEmailConfig || {};
  const labels = config.labels || {};
  const endpoint = config.endpoint || '';
  const token = config.token || '';

  let activeTaskId = null;

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function hideBlock() {
    const $block = $('#candidate-email-block');
    $block.attr('hidden', true).empty().removeClass('is-loading is-error is-empty');
  }

  function renderMessage(message, className) {
    const $block = $('#candidate-email-block');
    $block.removeAttr('hidden').removeClass('is-loading is-error is-empty');
    $block.html(`<span class="candidate-email-message ${className || ''}">${escapeHtml(message)}</span>`);
  }

  function renderEmail(email) {
    const $block = $('#candidate-email-block');
    const copyLabel = labels.copy || 'Copier';
    $block.removeAttr('hidden').removeClass('is-loading is-error is-empty');
    $block.html(`
      <span class="candidate-email-inline-text" id="candidate-email-address">${escapeHtml(email)}</span>
      <button type="button"
              class="candidate-email-copy-btn"
              data-copy-value="${escapeHtml(email)}"
              data-copy-label="${escapeHtml(copyLabel)}"
              data-default-icon="fa-clipboard"
              title="${escapeHtml(copyLabel)}"
              aria-label="${escapeHtml(copyLabel)}"
              style="background:none;border:0;padding:0;margin-left:4px;cursor:pointer;">
        <span class="fa fa-clipboard" aria-hidden="true" style="color:#007cba;"></span>
      </button>
    `);
  }

  async function loadCandidateEmail(taskId) {
    if (!endpoint || !token || !taskId) {
      hideBlock();
      return;
    }

    activeTaskId = taskId;
    const $block = $('#candidate-email-block');
    const loadingLabel = labels.loading || 'Chargement...';
    const title = labels.title || 'Adresse e-mail unique';

    $block.removeAttr('hidden').removeClass('is-error is-empty');
    $block.html(`
      <div class="candidate-email-card is-loading">
        <div class="candidate-email-title">${escapeHtml(title)}</div>
        <div class="candidate-email-message">${escapeHtml(loadingLabel)}</div>
      </div>
    `);

    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('token', token);

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const payload = await response.json();
      if (activeTaskId !== taskId) {
        return;
      }

      if (!payload || payload.success === false) {
        renderMessage((payload && payload.message) || 'Erreur lors du chargement de l’adresse e-mail.', 'is-error');
        return;
      }

      if (!payload.enabled) {
        hideBlock();
        return;
      }

      if (!payload.configured) {
        renderMessage(payload.message || labels.notConfigured || 'Configuration manquante.', 'is-error');
        return;
      }

      if (!payload.email) {
        renderMessage(payload.message || 'Adresse e-mail indisponible.', 'is-error');
        return;
      }

      renderEmail(payload.email);
    } catch (error) {
      if (activeTaskId !== taskId) {
        return;
      }
      renderMessage('Erreur lors du chargement de l’adresse e-mail.', 'is-error');
      console.error('candidate-email:', error);
    }
  }

  window.mondayLoadCandidateEmail = loadCandidateEmail;

  async function copyCandidateEmail(value, $button) {
    const $icon = $button.find('span.fa').first();
    const defaultIcon = $button.data('default-icon') || 'fa-clipboard';

    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        const $temp = $('<textarea readonly class="candidate-email-copy-buffer"></textarea>');
        $temp.val(value);
        $('body').append($temp);
        $temp[0].select();
        document.execCommand('copy');
        $temp.remove();
      }

      if ($icon.length) {
        $icon.removeClass('fa-clipboard fa-copy fa-check').addClass('fa-check');
      }

      const existingTimer = $button.data('copy-reset-timer');
      if (existingTimer) {
        window.clearTimeout(existingTimer);
      }

      const resetTimer = window.setTimeout(() => {
        if ($icon.length) {
          $icon.removeClass('fa-check').addClass(defaultIcon);
        }
        $button.removeData('copy-reset-timer');
      }, 4000);

      $button.data('copy-reset-timer', resetTimer);
    } catch (error) {
      console.error('candidate-email-copy:', error);
    }
  }

  $(document).on('click', '.candidate-email-copy-btn', function () {
    const value = String($(this).data('copy-value') || '');
    if (!value) {
      return;
    }
    copyCandidateEmail(value, $(this));
  });

  hideBlock();
})();
