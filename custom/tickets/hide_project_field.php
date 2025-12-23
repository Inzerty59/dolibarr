<?php

if (!empty($GLOBALS['forced_project_id'])) {
	echo '<style type="text/css">
	/* Masquer le champ projet et tous ses éléments associés */
	[id*="projectid"],
	[name*="projectid"]:not([type="hidden"]),
	.select2-container#select2-projectid,
	.form-group:has(select[name="projectid"]),
	tr:has(select[name="projectid"]),
	tr:has([name*="projectid"]:not([type="hidden"])) {
		display: none !important;
	}
	</style>';
	
	echo '<script type="text/javascript">
	(function() {
		if (typeof document !== "undefined") {
			// Attendre que le DOM soit chargé
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", function() {
					injectHiddenProject();
				});
			} else {
				injectHiddenProject();
			}
			
			function injectHiddenProject() {
				var forcedId = ' . (int)$GLOBALS['forced_project_id'] . ';
				
				// Chercher le formulaire de création de ticket
				var forms = document.querySelectorAll("form");
				forms.forEach(function(form) {
					// Vérifier si c\'est un formulaire de ticket
					var hasProjectField = form.querySelector("select[name=\"projectid\"]");
					if (hasProjectField) {
						// Créer et ajouter un input hidden
						var hidden = document.createElement("input");
						hidden.type = "hidden";
						hidden.name = "projectid";
						hidden.value = forcedId;
						form.appendChild(hidden);
						
						// Masquer le select et son wrapper
						hasProjectField.style.display = "none";
						var select2 = hasProjectField.previousElementSibling;
						if (select2 && select2.classList && select2.classList.contains("select2-container")) {
							select2.style.display = "none";
						}
					}
				});
			}
		}
	})();
	</script>';
}
