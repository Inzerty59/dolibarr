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
	
	// Fonction pour lire un cookie
	function getCookie(name) {
		var value = "; " + document.cookie;
		var parts = value.split("; " + name + "=");
		if (parts.length === 2) {
			return decodeURIComponent(parts.pop().split(";").shift());
		}
		return null;
	}
	
	// Fonction pour supprimer un cookie
	function deleteCookie(name) {
		document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
	}
	
	// Récupérer les infos du projet depuis le cookie
	var cookieData = getCookie('ticket_lock_project');
	if (!cookieData) {
		console.log('LockProject: Aucun cookie de verrouillage trouvé');
		return;
	}
	
	var project;
	try {
		project = JSON.parse(cookieData);
	} catch (e) {
		console.log('LockProject: Erreur parsing cookie', e);
		return;
	}
	
	if (!project || !project.id) {
		console.log('LockProject: Données projet invalides');
		return;
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
		
		// Créer l'affichage verrouillé
		var lockedDisplay = document.createElement('span');
		lockedDisplay.id = 'locked-project-display';
		lockedDisplay.innerHTML = '🔒 <strong>' + project.ref + ' - ' + project.title + '</strong>';
		lockedDisplay.style.cssText = 'padding: 6px 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 4px; display: inline-block; color: #2e7d32; font-size: 14px;';
		
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
		
		// Supprimer le cookie après utilisation
		deleteCookie('ticket_lock_project');
		
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
