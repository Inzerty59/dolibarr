function mondayEscapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function openCandidateStatusMailModal(draft) {
  $('#candidate-mail-modal').remove();

  const modal = $(`
    <div id="candidate-mail-modal" class="candidate-mail-modal">
      <div class="candidate-mail-dialog">
        <h3>Email candidat</h3>

        <label>Destinataire</label>
        <input type="email" id="candidate-mail-recipient" value="${mondayEscapeHtml(draft.recipient || '')}">

        <label>Sujet</label>
        <input type="text" id="candidate-mail-subject" value="${mondayEscapeHtml(draft.subject || '')}">

        <label>Message</label>
        <textarea id="candidate-mail-body">${mondayEscapeHtml(draft.body || '')}</textarea>

        <div id="candidate-mail-feedback" class="candidate-mail-feedback" style="display:none;"></div>

        <div class="candidate-mail-actions">
          <button type="button" class="button" id="candidate-mail-cancel">Annuler</button>
          <button type="button" class="button button-primary" id="candidate-mail-send">Envoyer</button>
        </div>
      </div>
    </div>
  `);

  $('body').append(modal);

  $('#candidate-mail-cancel').on('click', function () {
    modal.remove();
  });

  $('#candidate-mail-send').on('click', function () {
    sendCandidateStatusMail(draft);
  });
}

function showCandidateMailFeedback(type, message) {
  $('#candidate-mail-feedback')
    .removeClass('candidate-mail-feedback-success candidate-mail-feedback-error')
    .addClass(type === 'success' ? 'candidate-mail-feedback-success' : 'candidate-mail-feedback-error')
    .text(message)
    .show();
}

function showCandidateMailNotification(type, message) {
  let container = $('#candidate-mail-notifications');
  if (!container.length) {
    container = $('<div id="candidate-mail-notifications" class="candidate-mail-notifications"></div>');
    $('body').append(container);
  }

  const notification = $(`
    <div class="candidate-mail-notification ${type === 'success' ? 'candidate-mail-notification-success' : 'candidate-mail-notification-error'}">
      ${mondayEscapeHtml(message)}
    </div>
  `);

  container.append(notification);
  setTimeout(() => notification.addClass('visible'), 10);
  setTimeout(() => {
    notification.removeClass('visible');
    setTimeout(() => notification.remove(), 250);
  }, 5000);
}

function sendCandidateStatusMail(draft) {
  const recipient = $('#candidate-mail-recipient').val().trim();
  const subject = $('#candidate-mail-subject').val().trim();
  const body = $('#candidate-mail-body').val().trim();

  if (!recipient) {
    showCandidateMailFeedback('error', 'Le destinataire est obligatoire.');
    return;
  }

  const fd = new FormData();
  fd.append('send_candidate_status_mail', '1');
  fd.append('task_id', draft.task_id || '');
  fd.append('column_id', draft.column_id || '');
  fd.append('event_type', draft.event_type || '');
  fd.append('recipient', recipient);
  fd.append('subject', subject);
  fd.append('body', body);
  fd.append('token', window.formtoken || '');

  const $sendButton = $('#candidate-mail-send');
  $sendButton.prop('disabled', true).text('Envoi...');
  showCandidateMailFeedback('success', 'Envoi du mail en cours...');

  fetch('', { method: 'POST', body: fd })
    .then(response => response.json().then(data => ({ ok: response.ok, data })))
    .then(({ ok, data }) => {
      if (ok && data.success) {
        $('#candidate-mail-modal').remove();
        showCandidateMailNotification(data.comment_added === false ? 'error' : 'success', data.message || 'Email envoyé avec succès.');
        if (window.currentMondayTaskId == data.task_id && typeof window.reloadMondayTaskComments === 'function') {
          window.reloadMondayTaskComments(window.currentMondayTaskId);
        }
      } else {
        showCandidateMailFeedback('error', data.message || 'Erreur lors de l’envoi du mail.');
        $sendButton.prop('disabled', false).text('Envoyer');
      }
    })
    .catch(() => {
      showCandidateMailFeedback('error', 'Erreur réseau lors de l’envoi du mail.');
      $sendButton.prop('disabled', false).text('Envoyer');
    });
}
