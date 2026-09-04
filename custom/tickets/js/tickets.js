(function() {
	'use strict';

	function isTicketListPage() {
		return document.body
			&& document.body.classList.contains('mod-ticket')
			&& document.body.classList.contains('page-list')
			&& /\/ticket\/list\.php$/.test(window.location.pathname);
	}

	function fixTicketListHorizontalScroll() {
		if (!isTicketListPage()) {
			return;
		}

		var form = document.getElementById('searchFormList');
		var scroller = document.querySelector('#searchFormList > .div-table-responsive');
		var inside = document.querySelector('#searchFormList > .div-table-responsive > .div-table-responsive-inside');
		var table = document.querySelector('#searchFormList table.liste');

		if (!form || !scroller || !inside || !table) {
			return;
		}

		document.documentElement.style.overflowX = 'hidden';
		document.body.style.overflowX = 'hidden';

		var scrollerLeft = scroller.getBoundingClientRect().left;
		var availableWidth = Math.max(320, window.innerWidth - scrollerLeft - 20);

		form.style.minWidth = '0';
		form.style.overflowX = 'hidden';

		scroller.style.display = 'block';
		scroller.style.width = availableWidth + 'px';
		scroller.style.maxWidth = availableWidth + 'px';
		scroller.style.minWidth = '0';
		scroller.style.overflowX = 'auto';
		scroller.style.overflowY = 'visible';

		inside.style.width = 'max-content';
		inside.style.minWidth = '100%';

		table.style.width = 'max-content';
		table.style.minWidth = '100%';
		table.style.tableLayout = 'auto';

		if (window.scrollX !== 0) {
			window.scrollTo(0, window.scrollY);
		}
	}

	function initTicketListHorizontalScrollFix() {
		fixTicketListHorizontalScroll();
		window.addEventListener('resize', fixTicketListHorizontalScroll);
		window.addEventListener('load', fixTicketListHorizontalScroll);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTicketListHorizontalScrollFix);
	} else {
		initTicketListHorizontalScrollFix();
	}

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
