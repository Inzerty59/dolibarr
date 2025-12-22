<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../main.inc.php')) {
	require_once dirname(__FILE__) . '/../../main.inc.php';
	$res = 1;
}

if (!empty($conf->tickets->enabled)) {
	$action = GETPOST('action', 'alpha');
	$fk_project = GETPOST('fk_project', 'int');
	
	if ($action === 'create' && empty($fk_project)) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php', true, 302);
		exit;
	}
	
	if ($action === 'create' && !empty($fk_project)) {
		$_SESSION['ticket_forced_project'] = $fk_project;
	}
}

require_once DOL_DOCUMENT_ROOT.'/ticket/card.php';

if (!empty($conf->tickets->enabled) && !empty($_SESSION['ticket_forced_project'])) {
	$forced_project_id = (int)$_SESSION['ticket_forced_project'];
	?>
	<script type="text/javascript">
	(function() {
		function lockProjectField() {
			var projectSelect = document.querySelector('select[name*="fk_project"]');
			
			if (!projectSelect) {
				setTimeout(lockProjectField, 100);
				return;
			}
			
			projectSelect.value = <?php echo $forced_project_id; ?>;
			projectSelect.setAttribute('data-forced-project', <?php echo $forced_project_id; ?>);
			
			projectSelect.disabled = true;
			projectSelect.style.display = 'none';
			
			var selectedOption = projectSelect.options[projectSelect.selectedIndex];
			var projectText = selectedOption ? selectedOption.text : 'Projet sélectionné';
			
			var select2Container = projectSelect.parentElement.querySelector('.select2-container');
			
			if (select2Container) {
				var projectDisplay = document.createElement('div');
				projectDisplay.style.padding = '8px 12px';
				projectDisplay.style.border = '1px solid #ddd';
				projectDisplay.style.backgroundColor = '#f5f5f5';
				projectDisplay.style.borderRadius = '4px';
				projectDisplay.style.fontFamily = 'sans-serif';
				projectDisplay.style.fontSize = '14px';
				projectDisplay.style.color = '#333';
				projectDisplay.style.cursor = 'not-allowed';
				projectDisplay.style.userSelect = 'none';
				projectDisplay.style.webkitUserSelect = 'none';
				projectDisplay.style.minHeight = '36px';
				projectDisplay.style.display = 'flex';
				projectDisplay.style.alignItems = 'center';
				projectDisplay.style.width = '100%';
				projectDisplay.textContent = projectText;
				
				select2Container.style.display = 'none';
				select2Container.parentElement.insertBefore(projectDisplay, select2Container);
				
				select2Container.style.pointerEvents = 'none';
			}
			
			var form = projectSelect.closest('form');
			if (form) {
				form.addEventListener('submit', function(e) {
					projectSelect.disabled = false;
					projectSelect.value = <?php echo $forced_project_id; ?>;
					projectSelect.disabled = true;
				});
			}
			
			document.addEventListener('click', function(e) {
				if (projectSelect.contains(e.target) || (select2Container && select2Container.contains(e.target))) {
					e.preventDefault();
					e.stopPropagation();
					return false;
				}
			}, true);
		}
		
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', lockProjectField);
		} else {
			setTimeout(lockProjectField, 100);
		}
	})();
	</script>
	<?php
	unset($_SESSION['ticket_forced_project']);
}

