/**
 * Script de verrouillage du champ projet pour les tickets
 * Lit les infos depuis un cookie défini par custom/ticket/card.php
 */
(function() {
	'use strict';
	
	// Vérifier si on est sur ticket/card.php
	if (window.location.pathname.indexOf('/ticket/card.php') === -1) {
		return;
	}
	
	// Vérifier si on est en mode création
	var urlParams = new URLSearchParams(window.location.search);
	var action = urlParams.get('action');
	var projectidFromUrl = urlParams.get('projectid');
	
	// Ne verrouiller que si on est en création avec un projet
	if (action !== 'create' || !projectidFromUrl) {
		return;
	}
	
	// Fonction pour lire un cookie
	function getCookie(name) {
		var value = "; " + document.cookie;
		var parts = value.split("; " + name + "=");
		if (parts.length === 2) {
			return decodeURIComponent(parts.pop().split(";").shift());
		}
		return null;
	}
	
	// Récupérer les infos du projet depuis le cookie
	var cookieData = getCookie('ticket_lock_project');
	var project = null;
	
	if (cookieData) {
		try {
			project = JSON.parse(cookieData);
		} catch (e) {
			console.log('LockProject: Erreur parsing cookie', e);
		}
	}
	
	// Si pas de cookie mais projectid dans l'URL, utiliser les infos du Select
	if (!project || !project.id) {
		console.log('LockProject: Pas de cookie, utilisation du projectid URL:', projectidFromUrl);
		project = { id: projectidFromUrl, ref: '', title: '' };
	}
	
	console.log('LockProject: Verrouillage du projet', project);
	
	function lockProjectField() {
		var projectSelect = document.getElementById('projectid');
		
		if (!projectSelect) {
			return false;
		}
		
		// Vérifier si déjà verrouillé
		if (document.getElementById('locked-project-display')) {
			return true;
		}
		
		// Si on n'a pas les infos du projet, les récupérer du select
		var projectRef = project.ref;
		var projectTitle = project.title;
		
		if (!projectRef || !projectTitle) {
			// Chercher l'option sélectionnée
			var selectedOption = projectSelect.options[projectSelect.selectedIndex];
			if (selectedOption && selectedOption.text) {
				var fullText = selectedOption.text.trim();
				projectRef = fullText;
				projectTitle = '';
			}
		}
		
		var displayText = projectRef + (projectTitle ? ' - ' + projectTitle : '');
		
		// Trouver TOUS les éléments Select2 liés au projet
		var select2Containers = document.querySelectorAll(
			'.select2-container[id*="projectid"], ' +
			'span[id^="select2-projectid"], ' +
			'#projectid + .select2-container, ' +
			'#projectid ~ .select2-container'
		);
		
		// Aussi chercher le span parent qui contient tout
		var parentTd = projectSelect.closest('td');
		if (parentTd) {
			var allSelect2InTd = parentTd.querySelectorAll('.select2-container, .select2');
			select2Containers = allSelect2InTd.length > 0 ? allSelect2InTd : select2Containers;
		}
		
		console.log('LockProject: Select2 trouvés:', select2Containers.length);
		
		// Forcer la valeur du select
		projectSelect.value = project.id;
		
		// Créer l'affichage verrouillé style Dolibarr natif
		var lockedDisplay = document.createElement('span');
		lockedDisplay.id = 'locked-project-display';
		lockedDisplay.className = 'valeur inline-block';
		lockedDisplay.innerHTML = '<span class="classfortooltip" title="Projet lié au ticket">' + displayText + '</span>' +
			' <span class="fas fa-lock opacitymedium" title="Projet verrouillé" style="margin-left: 5px;"></span>';
		
		// Masquer le select original
		projectSelect.style.display = 'none';
		
		// Masquer TOUS les Select2 trouvés
		select2Containers.forEach(function(container) {
			container.style.display = 'none';
		});
		
		// Insérer l'affichage verrouillé
		projectSelect.parentNode.insertBefore(lockedDisplay, projectSelect);
		
		// Ajouter un champ hidden pour garantir la soumission
		var existingHidden = document.querySelector('input[type="hidden"][name="projectid"]');
		if (!existingHidden) {
			var hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			hiddenInput.name = 'projectid';
			hiddenInput.value = project.id;
			projectSelect.parentNode.appendChild(hiddenInput);
		}
		
		// Ajouter du CSS pour être sûr que Select2 reste caché
		var style = document.createElement('style');
		style.textContent = '#projectid + .select2-container, #projectid ~ .select2-container, .select2-container[id*="projectid"] { display: none !important; }';
		document.head.appendChild(style);
		
		console.log('LockProject: ✅ Projet verrouillé!');
		
		return true;
	}
	
	// Essayer de verrouiller plusieurs fois
	var attempts = 0;
	var maxAttempts = 50;
	
	function tryLock() {
		attempts++;
		if (lockProjectField()) {
			return;
		}
		if (attempts < maxAttempts) {
			setTimeout(tryLock, 100);
		} else {
			console.log('LockProject: ❌ Échec après ' + maxAttempts + ' tentatives');
		}
	}
	
	// Démarrer immédiatement si DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', tryLock);
	} else {
		tryLock();
	}
	
	// Aussi après le chargement complet (pour Select2)
	window.addEventListener('load', function() {
		setTimeout(tryLock, 500);
	});
})();
