<?php

class ActionsTickets
{
	public $error = '';
	public $errors = array();
	public $resprints = '';
	public $results = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db, $user;
		
		// Injecter le JS pour verrouiller le projet
		$this->injectProjectLockJS();
		
		return 0;
	}

	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db, $user;
		
		// Injecter le JS pour verrouiller le projet
		$this->injectProjectLockJS();
		
		return 0;
	}

	private function injectProjectLockJS()
	{
		global $db;
		
		// Ne s'exécuter qu'une fois
		static $already_injected = false;
		if ($already_injected) return;
		
		// Seulement sur ticket/card.php
		if (strpos($_SERVER['PHP_SELF'], '/ticket/card.php') === false) {
			return;
		}
		
		$forced_project_id = isset($_SESSION['ticket_forced_project']) ? intval($_SESSION['ticket_forced_project']) : 0;
		
		if ($forced_project_id <= 0) {
			return;
		}
		
		// Récupérer les infos du projet
		$forced_project_ref = '';
		$forced_project_title = '';
		
		$sql = "SELECT ref, title FROM ".MAIN_DB_PREFIX."projet WHERE rowid = ".$forced_project_id;
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			$obj = $db->fetch_object($resql);
			$forced_project_ref = addslashes($obj->ref);
			$forced_project_title = addslashes($obj->title);
		}
		
		$already_injected = true;
		
		?>
		<script type="text/javascript">
		(function() {
			console.log('Hook Tickets: Verrouillage du projet <?php echo $forced_project_id; ?>');
			
			function lockProjectField() {
				var projectSelect = document.getElementById('projectid');
				var select2Container = document.querySelector('.select2-container[id*="projectid"]') || 
				                       document.querySelector('#projectid + .select2-container') ||
				                       document.querySelector('span[id^="select2-projectid"]')?.closest('.select2-container');
				
				console.log('projectSelect:', projectSelect);
				console.log('select2Container:', select2Container);
				
				if (projectSelect) {
					// Forcer la valeur
					projectSelect.value = '<?php echo $forced_project_id; ?>';
					
					// Créer un affichage verrouillé
					var lockedDisplay = document.createElement('div');
					lockedDisplay.id = 'locked-project-display';
					lockedDisplay.innerHTML = '<strong style="color: #333; font-size: 14px;">🔒 <?php echo $forced_project_ref; ?> - <?php echo $forced_project_title; ?></strong>';
					lockedDisplay.style.cssText = 'padding: 8px 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 4px; display: inline-block;';
					
					// Masquer le select et select2
					projectSelect.style.display = 'none';
					if (select2Container) {
						select2Container.style.display = 'none';
					}
					
					// Insérer l'affichage verrouillé
					var parent = projectSelect.parentNode;
					if (!document.getElementById('locked-project-display')) {
						parent.insertBefore(lockedDisplay, projectSelect);
					}
					
					// Ajouter un champ hidden pour garantir la soumission
					if (!document.querySelector('input[name="projectid"][type="hidden"]')) {
						var hiddenInput = document.createElement('input');
						hiddenInput.type = 'hidden';
						hiddenInput.name = 'projectid';
						hiddenInput.value = '<?php echo $forced_project_id; ?>';
						parent.appendChild(hiddenInput);
					}
					
					console.log('Projet verrouillé avec succès!');
					return true;
				}
				return false;
			}

			// Essayer plusieurs fois car Select2 peut prendre du temps
			var attempts = 0;
			var maxAttempts = 30;
			var lockInterval = setInterval(function() {
				attempts++;
				if (lockProjectField() || attempts >= maxAttempts) {
					clearInterval(lockInterval);
					if (attempts >= maxAttempts) {
						console.log('Échec du verrouillage après ' + maxAttempts + ' tentatives');
					}
				}
			}, 100);
			
			// Aussi essayer au chargement complet
			window.addEventListener('load', function() {
				setTimeout(lockProjectField, 500);
			});
		})();
		</script>
		<?php
	}
}
