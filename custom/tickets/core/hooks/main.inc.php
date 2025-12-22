<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/core/hooks/main.inc.php
 * \ingroup     tickets
 * \brief       Hooks HTML du module Tickets
 */

global $conf, $langs;

// Charger les traductions
if (is_object($langs)) {
	$langs->load('tickets@tickets');
}

// Ajouter le JavaScript de redirection
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
