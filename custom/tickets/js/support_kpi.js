(function () {
	'use strict';

	function getConfig() {
		return window.supportKpiConfig || {};
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function getFilters() {
		return {
			project_id: $('#support-kpi-project').val() || '',
			start_date: $('#support-kpi-start-date').val() || '',
			end_date: $('#support-kpi-end-date').val() || '',
			status: $('#support-kpi-status').val() || '',
			assignee_id: $('#support-kpi-assignee').val() || '',
		};
	}

	function buildQuery() {
		const config = getConfig();
		const params = new URLSearchParams();
		params.set('token', config.token || '');

		const filters = getFilters();
		Object.keys(filters).forEach(key => {
			if (filters[key]) {
				params.set(key, filters[key]);
			}
		});

		return params.toString();
	}

	function setSelectOptions(selector, options, emptyLabel, selectedValue) {
		const rows = [`<option value="">${escapeHtml(emptyLabel)}</option>`];
		(options || []).forEach(option => {
			const value = String(option.id);
			rows.push(`<option value="${escapeHtml(value)}" ${value === String(selectedValue || '') ? 'selected' : ''}>${escapeHtml(option.label)}</option>`);
		});
		$(selector).html(rows.join(''));
	}

	function formatPercent(value) {
		return `${Number(value || 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 })}%`;
	}

	function buildPieGradient(series) {
		if (!series.length) {
			return '#eef1f4';
		}

		let cursor = 0;
		const stops = [];
		series.forEach(item => {
			const start = cursor;
			const end = cursor + Number(item.percentage || 0);
			stops.push(`${item.color || '#cccccc'} ${start}% ${end}%`);
			cursor = end;
		});
		if (cursor < 100) {
			stops.push(`#f2f4f7 ${cursor}% 100%`);
		}

		return `conic-gradient(${stops.join(', ')})`;
	}

	function renderStatusMetric(metric) {
		const series = metric.series || [];
		const legend = series.map(item => `
			<div class="kpi-legend-row">
				<span class="kpi-legend-color" style="background:${escapeHtml(item.color || '#cccccc')}"></span>
				<span class="kpi-legend-label">${escapeHtml(item.label)}</span>
				<strong>${formatPercent(item.percentage)}</strong>
				<span class="kpi-legend-count">${Number(item.count || 0)}</span>
			</div>
		`).join('');

		const labels = series
			.filter(item => Number(item.percentage) >= 5)
			.map(item => `<span style="background:${escapeHtml(item.color || '#cccccc')}">${formatPercent(item.percentage)}</span>`)
			.join('');

		return `
			<section class="kpi-card">
				<h3>${escapeHtml(metric.title || 'Repartition des tickets par statut')}</h3>
				<div class="kpi-chart-body">
					<div class="kpi-donut-wrap">
						<div class="kpi-donut" style="background:${buildPieGradient(series)}">
							<div class="kpi-donut-hole"></div>
						</div>
						<div class="kpi-donut-labels">${labels}</div>
					</div>
					<div class="kpi-legend">
						${legend || '<div class="kpi-empty">Aucune donnée</div>'}
					</div>
				</div>
			</section>
		`;
	}

	function renderBars(series) {
		return series.map(item => {
			const percentage = Number(item.percentage || 0);
			const width = Math.max(percentage, percentage > 0 ? 5 : 0);
			return `
				<div class="kpi-bar-row">
					<span class="kpi-bar-label" title="${escapeHtml(item.label)}">${escapeHtml(item.label)}</span>
					<div class="kpi-bar-track">
						<div class="kpi-bar-fill" style="width:${width}%;background:${escapeHtml(item.color || '#655aa8')}"></div>
					</div>
					<strong title="${escapeHtml(item.count)}">${escapeHtml(item.count)}</strong>
				</div>
			`;
		}).join('');
	}

	function renderResolutionMetric(metric) {
		const series = metric.series || [];
		return `
			<section class="kpi-wide-card">
				<div class="kpi-section-title">
					<h3>${escapeHtml(metric.title || 'Délai moyen de résolution')}</h3>
					<span>${Number(metric.valid_rows || 0)} ticket(s) fermé(s) avec dates valides</span>
				</div>
				<div class="kpi-delay-layout">
					<div class="kpi-stat-tile">
						<strong>${escapeHtml(metric.average_label || 'Aucune donnée')}</strong>
						<span>Délai moyen</span>
					</div>
					<div class="kpi-top-bars kpi-scroll-bars">
						${series.length ? renderBars(series) : '<div class="kpi-empty">Aucun ticket fermé avec date de résolution.</div>'}
					</div>
				</div>
			</section>
		`;
	}

	function renderProjectRows(projects) {
		if (!projects.length) {
			return '<tr><td colspan="7" class="kpi-empty">Aucun projet ticket sur cette période.</td></tr>';
		}

		return projects.map(project => `
			<tr>
				<td>
					<strong>${escapeHtml(project.project_ref)}</strong><br>
					<span>${escapeHtml(project.project_title)}</span>
				</td>
				<td>${escapeHtml(project.project_type_label)}</td>
				<td>${escapeHtml(project.thirdparty_name || '-')}</td>
				<td class="right">${Number(project.open || 0)}</td>
				<td class="right">${Number(project.in_progress || 0)}</td>
				<td class="right">${Number(project.closed || 0)}</td>
				<td>${escapeHtml(project.avg_resolution_label)}</td>
			</tr>
		`).join('');
	}

	function renderTicketRows(tickets) {
		if (!tickets.length) {
			return '<tr><td colspan="9" class="kpi-empty">Aucun ticket ne correspond aux filtres.</td></tr>';
		}

		return tickets.map(ticket => `
			<tr>
				<td><a href="${escapeHtml(ticket.ticket_url)}">${escapeHtml(ticket.ref)}</a></td>
				<td>${escapeHtml(ticket.subject)}</td>
				<td>${escapeHtml(ticket.project_ref)} - ${escapeHtml(ticket.project_title)}</td>
				<td>${escapeHtml(ticket.status_label)}</td>
				<td>${escapeHtml(ticket.assignee_name)}</td>
				<td>${escapeHtml(ticket.date_creation_label)}</td>
				<td>${escapeHtml(ticket.date_close_label || '-')}</td>
				<td>${escapeHtml(ticket.resolution_label)}</td>
				<td>${escapeHtml(ticket.thirdparty_name || '-')}</td>
			</tr>
		`).join('');
	}

	function renderTables(data) {
		return `
			<section class="kpi-wide-card">
				<div class="kpi-section-title">
					<h3>Projets Dolibarr</h3>
					<span>${Number((data.projects || []).length)} projet(s)</span>
				</div>
				<div class="div-table-responsive">
					<table class="liste centpercent">
						<thead>
							<tr class="liste_titre">
								<th>Projet</th>
								<th>Type</th>
								<th>Client</th>
								<th class="right">Ouverts</th>
								<th class="right">En cours</th>
								<th class="right">Fermés</th>
								<th>Délai moyen</th>
							</tr>
						</thead>
						<tbody>${renderProjectRows(data.projects || [])}</tbody>
					</table>
				</div>
			</section>

			<section class="kpi-wide-card">
				<div class="kpi-section-title">
					<h3>Tickets filtrés</h3>
					<span>${Number((data.tickets || []).length)} ticket(s)</span>
				</div>
				<div class="div-table-responsive">
					<table class="liste centpercent">
						<thead>
							<tr class="liste_titre">
								<th>Ticket</th>
								<th>Sujet</th>
								<th>Projet</th>
								<th>Statut</th>
								<th>Assigné</th>
								<th>Création</th>
								<th>Fermeture</th>
								<th>Délai</th>
								<th>Client</th>
							</tr>
						</thead>
						<tbody>${renderTicketRows(data.tickets || [])}</tbody>
					</table>
				</div>
			</section>
		`;
	}

	function loadDashboard() {
		const config = getConfig();
		const selected = getFilters();
		$('#support-kpi-results').html('<div class="kpi-loading">Chargement des KPI...</div>');

		fetch(`${config.endpoint}?${buildQuery()}`)
			.then(response => {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(data => {
				const options = data.filter_options || {};
				setSelectOptions('#support-kpi-project', options.projects || [], 'Tous les projets', selected.project_id);
				setSelectOptions('#support-kpi-status', options.statuses || [], 'Tous les statuts', selected.status);
				setSelectOptions('#support-kpi-assignee', options.assignees || [], 'Tous les assignés', selected.assignee_id);

				$('#support-kpi-results').html(`
					<div class="kpi-summary">
						<strong>${Number(data.summary?.total || 0)}</strong>
						<span>tickets utilisés pour ces KPI</span>
					</div>
					<div class="kpi-analytics-grid">
						${renderStatusMetric(data.status_metric || {})}
						${renderResolutionMetric(data.resolution_metric || {})}
					</div>
					${renderTables(data)}
				`);
			})
			.catch(error => {
				console.error('Support KPI error:', error);
				$('#support-kpi-results').html('<div class="kpi-error">Impossible de charger les KPI support.</div>');
			});
	}

	function exportDashboard() {
		const config = getConfig();
		window.location.href = `${config.exportEndpoint}?${buildQuery()}`;
	}

	$(function () {
		$('#support-kpi-apply').on('click', loadDashboard);
		$('#support-kpi-export').on('click', exportDashboard);
		$('#support-kpi-project, #support-kpi-status, #support-kpi-assignee').on('change', loadDashboard);
		$('#kpi-reset-filter').on('click', function () {
			$('#support-kpi-project, #support-kpi-start-date, #support-kpi-end-date, #support-kpi-status, #support-kpi-assignee').val('');
			loadDashboard();
		});

		loadDashboard();
	});
})();
