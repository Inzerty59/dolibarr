<?php

if (defined('TICKETS_REDIRECT_LOADED')) {
	return;
}
define('TICKETS_REDIRECT_LOADED', true);

global $conf, $user;

if (!empty($conf->tickets->enabled) && !empty($user->id)) {
	if (!isset($GLOBALS['dolibarr_footerscripts'])) {
		$GLOBALS['dolibarr_footerscripts'] = array();
	}

	$GLOBALS['dolibarr_footerscripts'][] = '/custom/tickets/js/tickets.js';

	if (!isset($GLOBALS['_JS_INLINE'])) {
		$GLOBALS['_JS_INLINE'] = array();
	}

	$GLOBALS['_JS_INLINE']['tickets_intercept'] = <<<'JS'
(function() {
	'use strict';

	function interceptTicketCreation() {
		var newTicketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		newTicketLinks.forEach(function(link) {
			var href = link.href || '';
			
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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', interceptTicketCreation);
	} else {
		interceptTicketCreation();
	}
})();
JS;
}
