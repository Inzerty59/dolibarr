<?php

define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../class/bootstrap.inc.php';

/**
 * @var User $user
 */

header('Content-Type: application/javascript; charset=UTF-8');

$config = array(
	'root' => DOL_URL_ROOT,
	'token' => currentToken(),
	'isAdmin' => !empty($user->admin),
		'urls' => array(
			'saveUsers' => dol_buildpath('/thirdpartynotify/ajax/save_users.php', 1),
			'sendEvent' => dol_buildpath('/thirdpartynotify/ajax/send_event.php', 1),
			'eligibleEvents' => dol_buildpath('/thirdpartynotify/ajax/list_eligible_events.php', 1),
		),
);
?>
(function () {
	'use strict';

	var config = <?php echo json_encode($config); ?>;
	var token = config.token || '';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	function currentPath() {
		return window.location.pathname.replace(/\/+$/, '');
	}

	function postForm(url, data) {
		data.token = token;
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: new URLSearchParams(data).toString()
		}).then(function (response) {
			return response.json().then(function (json) {
				if (json && json.newToken) token = json.newToken;
				if (!response.ok || !json.success) {
					throw new Error((json && json.error) || 'Erreur serveur');
				}
				return json;
			});
		});
	}

	function notify(message, isError) {
		if (window.jQuery && window.jQuery.jnotify) {
			window.jQuery.jnotify(message, isError ? 'error' : 'message');
			return;
		}
		if (window.console) {
			(isError ? console.error : console.log)(message);
		}
	}

	function escapeText(value) {
		return String(value == null ? '' : value);
	}

	function initialsFor(user) {
		return escapeText(user.initials || 'U').slice(0, 2).toUpperCase();
	}

	function buildAdminPanel() {
		if (!config.isAdmin || currentPath().indexOf('/thirdpartynotify/admin/setup.php') === -1) {
			return;
		}
		if (document.getElementById('thirdpartynotify-admin-panel')) {
			return;
		}

		var mount = document.getElementById('thirdpartynotify-admin-panel-mount');
		if (!mount) {
			return;
		}

		var panel = document.createElement('section');
		panel.id = 'thirdpartynotify-admin-panel';
		panel.className = 'thirdpartynotify-panel';
		panel.innerHTML = [
			'<div class="thirdpartynotify-header">',
			'  <span class="fa fa-bell-o thirdpartynotify-title-icon" aria-hidden="true"></span>',
			'  <h3>Utilisateurs à notifier par email</h3>',
			'</div>',
			'<div class="thirdpartynotify-separator"></div>',
			'<div class="thirdpartynotify-picker">',
			'  <select id="thirdpartynotify-user-select" class="flat minwidth300">',
			'    <option value="">Selectionner un utilisateur à ajouter...</option>',
			'  </select>',
			'  <button type="button" class="button" id="thirdpartynotify-add-user"><span class="fa fa-plus"></span> Ajouter</button>',
			'</div>',
			'<div class="thirdpartynotify-help"><span class="fa fa-info-circle"></span> Sélectionnez les utilisateurs qui recevront les notifications par email concernant les événements des tiers.</div>',
			'<div class="thirdpartynotify-selected-box">',
			'  <div class="thirdpartynotify-box-title">UTILISATEURS SELECTIONNÉS</div>',
			'  <div id="thirdpartynotify-selected-users" class="thirdpartynotify-selected-users"></div>',
			'</div>',
			'<div class="thirdpartynotify-separator"></div>',
			'<div class="thirdpartynotify-actions">',
			'  <button type="button" class="button" id="thirdpartynotify-save-users"><span class="fa fa-check"></span> Enregistrer</button>',
			'  <span id="thirdpartynotify-status" class="thirdpartynotify-status"></span>',
			'</div>'
		].join('');

		mount.appendChild(panel);

		var select = panel.querySelector('#thirdpartynotify-user-select');
		var selectedContainer = panel.querySelector('#thirdpartynotify-selected-users');
		var status = panel.querySelector('#thirdpartynotify-status');
		var allUsers = [];
		var selected = [];

		function setStatus(message, isError) {
			status.textContent = message || '';
			status.classList.toggle('thirdpartynotify-error', !!isError);
		}

		function selectedIds() {
			return selected.map(function (user) { return Number(user.id); });
		}

		function renderSelect() {
			var ids = selectedIds();
			select.innerHTML = '<option value="">Selectionner un utilisateur a ajouter...</option>';
			allUsers.forEach(function (user) {
				if (ids.indexOf(Number(user.id)) !== -1) return;
				var option = document.createElement('option');
				option.value = user.id;
				option.textContent = user.name + (user.email ? ' (' + user.email + ')' : '');
				select.appendChild(option);
			});
		}

		function renderSelected() {
			selectedContainer.innerHTML = '';
			if (!selected.length) {
				var empty = document.createElement('div');
				empty.className = 'thirdpartynotify-empty';
				empty.textContent = 'Aucun utilisateur selectionne.';
				selectedContainer.appendChild(empty);
				renderSelect();
				return;
			}

			selected.forEach(function (user) {
				var item = document.createElement('div');
				item.className = 'thirdpartynotify-user-row';
				if (!user.email) item.className += ' thirdpartynotify-user-row-no-email';
				item.dataset.userId = user.id;

				var avatar = document.createElement('div');
				avatar.className = 'thirdpartynotify-avatar';
				avatar.textContent = initialsFor(user);

				var text = document.createElement('div');
				text.className = 'thirdpartynotify-user-text';
				var name = document.createElement('strong');
				name.textContent = escapeText(user.name);
				var email = document.createElement('span');
				email.className = user.email ? '' : 'thirdpartynotify-email-warning';
				email.textContent = escapeText(user.email || 'Email non renseigne - cet utilisateur ne recevra pas de mail');
				text.appendChild(name);
				text.appendChild(email);

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'button thirdpartynotify-remove';
				remove.title = 'Supprimer';
				remove.innerHTML = '<span class="fa fa-trash" aria-hidden="true"></span>';
				remove.addEventListener('click', function () {
					selected = selected.filter(function (entry) { return Number(entry.id) !== Number(user.id); });
					renderSelected();
				});

				item.appendChild(avatar);
				item.appendChild(text);
				item.appendChild(remove);
				selectedContainer.appendChild(item);
			});
			renderSelect();
		}

		panel.querySelector('#thirdpartynotify-add-user').addEventListener('click', function () {
			var id = Number(select.value || 0);
			if (!id) return;
			var user = allUsers.find(function (entry) { return Number(entry.id) === id; });
			if (!user) return;
			selected.push(user);
			select.value = '';
			setStatus('');
			renderSelected();
		});

		panel.querySelector('#thirdpartynotify-save-users').addEventListener('click', function () {
			setStatus('Enregistrement...');
			postForm(config.urls.saveUsers, {
				mode: 'save',
				users: selectedIds().join(',')
			}).then(function (json) {
				selected = json.selected || selected;
				renderSelected();
				setStatus('Configuration enregistree.');
				notify('Configuration enregistree.', false);
			}).catch(function (error) {
				setStatus(error.message, true);
				notify(error.message, true);
			});
		});

		setStatus('Chargement...');
		postForm(config.urls.saveUsers, {mode: 'list'}).then(function (json) {
			allUsers = json.users || [];
			selected = json.selected || [];
			renderSelected();
			setStatus('');
		}).catch(function (error) {
			setStatus(error.message, true);
		});
	}

	function extractActionId(item) {
		var link = item.querySelector('a[href*="/comm/action/card.php?id="]');
		if (!link) return 0;
		try {
			var url = new URL(link.getAttribute('href'), window.location.origin);
			return Number(url.searchParams.get('id') || 0);
		} catch (e) {
			var match = link.getAttribute('href').match(/[?&]id=([0-9]+)/);
			return match ? Number(match[1]) : 0;
		}
	}

	function buildMessagingButtons(eligibleIds) {
		if (currentPath().indexOf('/societe/messaging.php') === -1) {
			return;
		}

		var socid = Number(new URLSearchParams(window.location.search).get('socid') || 0);
		if (!socid) return;

		document.querySelectorAll('ul.timeline li .timeline-item').forEach(function (item) {
			if (item.querySelector('.thirdpartynotify-send-event')) return;
			var actionId = extractActionId(item);
			if (!actionId) return;
			if (Array.isArray(eligibleIds) && eligibleIds.indexOf(actionId) === -1) return;

			var target = item.querySelector('.timeline-header-action2') || item.querySelector('.timeline-header');
			if (!target) return;

			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'button thirdpartynotify-send-event';
			button.title = 'Envoyer cet evenement aux utilisateurs notifies';
			button.dataset.actioncommId = actionId;
			button.innerHTML = '<span class="fa fa-envelope-o" aria-hidden="true"></span> Notifier';
			button.addEventListener('click', function () {
				var original = button.innerHTML;
				button.disabled = true;
				button.innerHTML = '<span class="fa fa-spinner fa-spin" aria-hidden="true"></span> Notifier...';
				postForm(config.urls.sendEvent, {
					socid: socid,
					actioncomm_id: actionId
				}).then(function (json) {
					button.innerHTML = '<span class="fa fa-check" aria-hidden="true"></span> Notifié';
					notify(json.message || 'Email envoye.', false);
					window.setTimeout(function () {
						button.disabled = false;
						button.innerHTML = original;
					}, 2500);
				}).catch(function (error) {
					button.disabled = false;
					button.innerHTML = original;
					notify(error.message, true);
				});
			});

			target.appendChild(button);
		});
	}

	function relabelActionProgressSelect() {
		if (currentPath().indexOf('/comm/action/card.php') === -1) {
			return;
		}

		var select = document.querySelector('select[name="complete"], select#selectcomplete');
		if (!select) {
			return;
		}

		var labels = {
			'0': 'En attente',
			'50': 'En cours',
			'100': 'Archives'
		};
		Array.prototype.forEach.call(select.options, function (option) {
			if (Object.prototype.hasOwnProperty.call(labels, option.value)) {
				option.textContent = labels[option.value];
			}
		});

		if (window.jQuery) {
			window.jQuery(select).trigger('change.select2');
		}
	}

	onReady(function () {
		buildAdminPanel();
		relabelActionProgressSelect();
		window.setTimeout(relabelActionProgressSelect, 500);
		if (currentPath().indexOf('/societe/messaging.php') !== -1) {
			var socid = Number(new URLSearchParams(window.location.search).get('socid') || 0);
			if (socid) {
				postForm(config.urls.eligibleEvents, {socid: socid}).then(function (json) {
					buildMessagingButtons((json.ids || []).map(Number));
				}).catch(function (error) {
					notify(error.message, true);
				});
			}
		}
	});
})();
