<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/class/hooks.class.php
 * \ingroup     tickets
 * \brief       Classe pour les hooks du module Tickets
 */

/**
 * Hooks class for Tickets module
 */
class TicketsHooks
{
	/**
	 * Ajoute le JavaScript de redirection dans le header
	 *
	 * @param array $parameters Paramètres du hook
	 * @param object $object Objet courant
	 * @param string $action Action courante
	 * @param HookManager $hookmanager HookManager
	 * @return int 0 ou 1 selon le succès
	 */
	public function pageHeader($parameters, $object, $action, $hookmanager)
	{
		global $conf, $db, $user;

		// Vérifier que le module est activé
		if (empty($conf->tickets->enabled)) {
			return 0;
		}

		// Vérifier les droits de l'utilisateur
		if (!$user->rights->ticket->read) {
			return 0;
		}

		// Ajouter le JavaScript
		?>
<script type="text/javascript">
(function() {
	"use strict";

	// Fonction pour initialiser la redirection des liens de tickets
	function initTicketRedirect() {
		// Modifier les liens existants
		var ticketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		ticketLinks.forEach(function(link) {
			var href = link.getAttribute('href');
			
			// Si le lien ne contient pas de fk_project, le modifier
			if (href && !href.includes('fk_project=')) {
				link.setAttribute('href', '<?php echo DOL_URL_ROOT; ?>/custom/tickets/select_project.php');
				// Ajouter un data attribute pour tracer
				link.setAttribute('data-modified', 'true');
			}
		});

		// Intercepter les clics sur les liens
		document.addEventListener('click', function(e) {
			var link = e.target.closest('a[href*="/ticket/card.php"][href*="action=create"]');
			
			if (link) {
				var href = link.getAttribute('href');
				
				// Si le lien ne contient pas de fk_project, rediriger
				if (href && !href.includes('fk_project=')) {
					e.preventDefault();
					window.location.href = '<?php echo DOL_URL_ROOT; ?>/custom/tickets/select_project.php';
					return false;
				}
			}
		}, true);
	}

	// Exécuter quand le DOM est prêt
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTicketRedirect);
	} else {
		initTicketRedirect();
	}

	// Ré-exécuter quand de nouveaux contenus sont chargés (AJAX)
	if (window.hookmanager) {
		// Observer les changements du DOM
		var observer = new MutationObserver(function(mutations) {
			initTicketRedirect();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}
})();
</script>
		<?php

		return 0;
	}
}
