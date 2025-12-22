<?php

class TicketsHooks
{

	public function pageHeader($parameters, $object, $action, $hookmanager)
	{
		global $conf, $db, $user;

		if (empty($conf->tickets->enabled)) {
			return 0;
		}

		if (!$user->rights->ticket->read) {
			return 0;
		}

		?>
<script type="text/javascript">
(function() {
	"use strict";

	function initTicketRedirect() {
		var ticketLinks = document.querySelectorAll('a[href*="/ticket/card.php"][href*="action=create"]');
		
		ticketLinks.forEach(function(link) {
			var href = link.getAttribute('href');
			
			if (href && !href.includes('fk_project=')) {
				link.setAttribute('href', '<?php echo DOL_URL_ROOT; ?>/custom/tickets/select_project.php');
				link.setAttribute('data-modified', 'true');
			}
		});

		document.addEventListener('click', function(e) {
			var link = e.target.closest('a[href*="/ticket/card.php"][href*="action=create"]');
			
			if (link) {
				var href = link.getAttribute('href');
				
				if (href && !href.includes('fk_project=')) {
					e.preventDefault();
					window.location.href = '<?php echo DOL_URL_ROOT; ?>/custom/tickets/select_project.php';
					return false;
				}
			}
		}, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTicketRedirect);
	} else {
		initTicketRedirect();
	}

	if (window.hookmanager) {
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
