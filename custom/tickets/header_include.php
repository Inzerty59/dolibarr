<?php
/**
 * Auto-loaded header file for custom tickets module
 * This adds JavaScript to intercept ticket creation links
 */

// Only include once
if (defined('TICKETS_REDIRECT_LOADED')) {
	return;
}
define('TICKETS_REDIRECT_LOADED', true);

// Check if tickets module is enabled
global $conf, $user;

if (!empty($conf->tickets->enabled) && !empty($user->id)) {
	// Add inline JavaScript to footer or header
	if (!isset($GLOBALS['dolibarr_footerscripts'])) {
		$GLOBALS['dolibarr_footerscripts'] = array();
	}

	// Add the ticket redirect script
	$GLOBALS['dolibarr_footerscripts'][] = '/custom/tickets/js/tickets.js';

	// Add inline script
	if (!isset($GLOBALS['_JS_INLINE'])) {
		$GLOBALS['_JS_INLINE'] = array();
	}

	$GLOBALS['_JS_INLINE']['tickets_intercept'] = <<<'JS'
(function() {
	'use strict';

	// Override the "New Ticket" button behavior
	function interceptTicketCreation() {
		// Find all "New Ticket" links in the sidebar
		var newTicketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		newTicketLinks.forEach(function(link) {
			var href = link.href || '';
			
			// If no project ID in the URL, redirect to our project selection page
			if (!href.includes('fk_project=') && !href.includes('project_id=')) {
				link.href = location.origin + '/custom/tickets/select_project.php';
				link.addEventListener('click', function(e) {
					e.preventDefault();
					window.location.href = location.origin + '/custom/tickets/select_project.php';
					return false;
				});
			}
		});
	}

	// Run on page load
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', interceptTicketCreation);
	} else {
		interceptTicketCreation();
	}
})();
JS;
}
