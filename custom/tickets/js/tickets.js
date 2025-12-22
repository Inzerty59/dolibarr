(function() {
	'use strict';


	document.addEventListener('click', function(e) {
		var link = e.target.closest('a[href*="/ticket/card.php"][href*="action=create"]');
		
		if (link) {
			var href = link.getAttribute('href');
			
			if (href && !href.includes('fk_project=')) {
				e.preventDefault();
				window.location.href = '/custom/tickets/select_project.php';
				return false;
			}
		}
	}, true);

	window.addEventListener('load', function() {
		var ticketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		ticketLinks.forEach(function(link) {
			var href = link.getAttribute('href');
			
			if (href && !href.includes('fk_project=')) {
				link.setAttribute('href', '/custom/tickets/select_project.php');
				link.setAttribute('data-original-href', href);
			}
		});
	});
})();
