<?php
/* Fichier JS dynamique pour verrouiller le projet sur ticket/card.php */

// Headers pour JavaScript
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Charger Dolibarr
$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../../main.inc.php')) {
	$res = @include dirname(__FILE__) . '/../../../main.inc.php';
}
if (!$res && file_exists(dirname(__FILE__) . '/../../../../main.inc.php')) {
	$res = @include dirname(__FILE__) . '/../../../../main.inc.php';
}

// Récupérer le projet forcé
$forced_project_id = isset($_SESSION['ticket_forced_project']) ? intval($_SESSION['ticket_forced_project']) : 0;
$forced_project_ref = '';
$forced_project_title = '';

if ($forced_project_id > 0) {
	$sql = "SELECT ref, title FROM ".MAIN_DB_PREFIX."projet WHERE rowid = ".$forced_project_id;
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		$forced_project_ref = addslashes($obj->ref);
		$forced_project_title = addslashes($obj->title);
	}
}
?>

(function() {
	'use strict';
	
	var forcedProjectId = <?php echo $forced_project_id; ?>;
	var forcedProjectRef = '<?php echo $forced_project_ref; ?>';
	var forcedProjectTitle = '<?php echo $forced_project_title; ?>';
	
	console.log('LockProject.js chargé - Projet forcé: ' + forcedProjectId);
	
	// Vérifier si on est sur ticket/card.php
	if (window.location.pathname.indexOf('/ticket/card.php') === -1) {
		console.log('Pas sur ticket/card.php, script ignoré');
		return;
	}
	
	if (forcedProjectId <= 0) {
		console.log('Aucun projet forcé en session');
		return;
	}
	
	function lockProjectField() {
		var projectSelect = document.getElementById('projectid');
		
		if (!projectSelect) {
			console.log('Select projectid non trouvé');
			return false;
		}
		
		// Vérifier si déjà verrouillé
		if (document.getElementById('locked-project-display')) {
			console.log('Déjà verrouillé');
			return true;
		}
		
		// Trouver le conteneur Select2
		var select2Container = projectSelect.nextElementSibling;
		if (!select2Container || !select2Container.classList.contains('select2-container')) {
			// Chercher autrement
			select2Container = document.querySelector('.select2-container[id*="projectid"]');
		}
		
		console.log('Select2 container:', select2Container);
		
		// Forcer la valeur du select
		projectSelect.value = forcedProjectId;
		
		// Créer l'affichage verrouillé
		var lockedDisplay = document.createElement('div');
		lockedDisplay.id = 'locked-project-display';
		lockedDisplay.innerHTML = '<span style="color: #2e7d32; font-weight: bold; font-size: 14px;">🔒 ' + forcedProjectRef + ' - ' + forcedProjectTitle + '</span>';
		lockedDisplay.style.cssText = 'padding: 8px 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 4px; display: inline-block; margin: 2px 0;';
		
		// Masquer le select original
		projectSelect.style.display = 'none';
		
		// Masquer Select2 si présent
		if (select2Container) {
			select2Container.style.display = 'none';
		}
		
		// Insérer l'affichage verrouillé avant le select
		projectSelect.parentNode.insertBefore(lockedDisplay, projectSelect);
		
		// Ajouter un champ hidden pour garantir la soumission
		var existingHidden = document.querySelector('input[type="hidden"][name="projectid"]');
		if (!existingHidden) {
			var hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			hiddenInput.name = 'projectid';
			hiddenInput.value = forcedProjectId;
			projectSelect.parentNode.appendChild(hiddenInput);
		}
		
		console.log('✅ Projet verrouillé avec succès!');
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
			console.log('❌ Échec du verrouillage après ' + maxAttempts + ' tentatives');
		}
	}
	
	// Démarrer immédiatement
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', tryLock);
	} else {
		tryLock();
	}
	
	// Aussi après le chargement complet
	window.addEventListener('load', function() {
		setTimeout(tryLock, 300);
	});
})();
