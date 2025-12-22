/**
 * JavaScript pour le module Tickets
 * Modifie les liens de création de tickets
 */

(function() {
	'use strict';

	/**
	 * Intercepte les clics sur les liens de création de tickets
	 */
	document.addEventListener('click', function(e) {
		var link = e.target.closest('a[href*="/ticket/card.php"][href*="action=create"]');
		
		if (link) {
			var href = link.getAttribute('href');
			
			// Si le lien ne contient pas de fk_project, rediriger vers la sélection
			if (href && !href.includes('fk_project=')) {
				e.preventDefault();
				window.location.href = '/custom/tickets/select_project.php';
				return false;
			}
		}
	}, true);

	/**
	 * Modifie aussi les liens lors du chargement de la page
	 */
	window.addEventListener('load', function() {
		var ticketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		ticketLinks.forEach(function(link) {
			var href = link.getAttribute('href');
			
			// Si le lien ne contient pas de fk_project, le modifier
			if (href && !href.includes('fk_project=')) {
				link.setAttribute('href', '/custom/tickets/select_project.php');
				link.setAttribute('data-original-href', href);
			}
		});
	});
})();
