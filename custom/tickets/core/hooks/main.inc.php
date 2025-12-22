<?php

global $conf, $langs;

if (is_object($langs)) {
	$langs->load('tickets@tickets');
}

$GLOBALS['_JS_INLINE']['tickets_redirect'] = '
<script type="text/javascript">
(function() {
	"use strict";

	// Attendre que le DOM soit prêt
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function() {
			initTicketRedirect();
		});
	} else {
		initTicketRedirect();
	}

	function initTicketRedirect() {
		// Modifier les liens existants
		var ticketLinks = document.querySelectorAll("a[href*=\"/ticket/card.php\"][href*=\"action=create\"]");
		
		ticketLinks.forEach(function(link) {
			var href = link.getAttribute("href");
			
			// Si le lien ne contient pas de fk_project, le modifier
			if (href && !href.includes("fk_project=")) {
				link.setAttribute("href", "' . DOL_URL_ROOT . '/custom/tickets/select_project.php");
			}
		});

		// Intercepter les clics futurs
		document.addEventListener("click", function(e) {
			var link = e.target.closest("a[href*=\"/ticket/card.php\"][href*=\"action=create\"]");
			
			if (link) {
				var href = link.getAttribute("href");
				
				// Si le lien ne contient pas de fk_project, le rediriger
				if (href && !href.includes("fk_project=")) {
					e.preventDefault();
					window.location.href = "' . DOL_URL_ROOT . '/custom/tickets/select_project.php";
					return false;
				}
			}
		}, true);
	}
})();
</script>
';
