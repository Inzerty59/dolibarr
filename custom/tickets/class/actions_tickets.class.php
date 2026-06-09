<?php
/*
 * T6 - Hook actions for custom ticket templates.
 *
 * This class is loaded by Dolibarr's HookManager when the custom tickets module
 * is enabled. Its job is to extend native pages without patching core files:
 * - projectcard: add the "ticket template" selector to project create/edit.
 * - ticketcard: let Dolibarr render the native ticket form and inject only the
 *   custom fields of the template attached to the selected project.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';

class ActionsTickets extends CommonHookActions
{
	/**
	 * @var DoliDB
	 */
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Main hook entry point for actions executed before native page processing.
	 *
	 * For ticket creation, it synchronizes template fields into Dolibarr
	 * extrafields storage, redirects ticket creation without project to the
	 * project selector, and validates required template fields before Dolibarr's
	 * native add action creates the ticket.
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $extrafields, $langs, $user;

		$langs->load('tickets@tickets');

		if (!$this->isTicketCard()) {
			return 0;
		}

		$ticketForOptionals = $this->fetchCurrentTicketForOptionals($object, $action);
		$projectid = $this->getProjectIdFromRequest($ticketForOptionals);

		if (in_array($action, array('add', 'update'), true)) {
			$this->syncAllTemplateExtraFieldsForStorage();
			if (is_object($extrafields)) {
				$extrafields->fetch_name_optionals_label($object->table_element, true);
			}
		}

		if ($action === 'create' && $projectid <= 0) {
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
			exit;
		}

		if (!in_array($action, array('add', 'update'), true) || $projectid <= 0) {
			return 0;
		}

		$template = $this->fetchProjectTemplate($projectid);
		if (empty($template)) {
			return 0;
		}

		$fields = $this->fetchTemplateFields((int) $template->rowid);
		if (empty($fields)) {
			return 0;
		}

		if (!$user->hasRight('ticket', 'write')) {
			accessforbidden('NotEnoughPermissions', 0, 1);
		}

		$templateExtraFields = new ExtraFields($this->db);
		$this->fillExtraFields($templateExtraFields, $fields, 1);

		if (!$this->validateRequiredFields($fields, $templateExtraFields)) {
			$action = ($action === 'add') ? 'create' : 'edit';
			return -1;
		}

		return 0;
	}

	/**
	 * True when the current request is handled by native ticket/card.php.
	 */
	private function isTicketCard()
	{
		return strpos($_SERVER['PHP_SELF'] ?? '', '/ticket/card.php') !== false;
	}

	/**
	 * Extend native Dolibarr forms without replacing them.
	 *
	 * - projectcard: inject the project -> ticket template selector.
	 * - ticketcard: inject only the selected template fields into FormTicket.
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if ($this->isProjectCard()) {
			if (!in_array($action, array('create', 'edit'), true)) {
				return 0;
			}

			$langs->load('tickets@tickets');
			$selectedTemplateId = GETPOSTISSET('fk_ticket_template') ? GETPOSTINT('fk_ticket_template') : $this->fetchProjectTemplateId((int) $object->id);

			$this->resprints = $this->renderProjectTemplateSelect($selectedTemplateId);

			return 0;
		}

		if ($this->isTicketCard() && in_array($action, array('create', 'edit'), true)) {
			$ticketForOptionals = $this->fetchCurrentTicketForOptionals($object, $action);
			$projectid = $this->getProjectIdFromRequest($ticketForOptionals);

			$template = $projectid > 0 ? $this->fetchProjectTemplate($projectid) : null;
			if (empty($template)) {
				return 0;
			}

			$templateFields = $this->fetchTemplateFields((int) $template->rowid);
			if (!empty($templateFields)) {
				$this->syncDolibarrExtraFields($templateFields);
			}

			print $this->renderTicketTemplateOptionals($ticketForOptionals, $templateFields);

			return 1;
		}

		return 0;
	}

	/**
	 * True when the current request is handled by native projet/card.php.
	 */
	private function isProjectCard()
	{
		return strpos($_SERVER['PHP_SELF'] ?? '', '/projet/card.php') !== false;
	}

	/**
	 * Fetch the template currently attached to a project.
	 *
	 * Used to preselect the project template when editing a project.
	 */
	private function fetchProjectTemplateId($projectid)
	{
		global $conf;

		if ($projectid <= 0) {
			return 0;
		}

		$sql = "SELECT fk_template";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_project_template";
		$sql .= " WHERE fk_project = ".((int) $projectid);
		$sql .= " AND entity = ".((int) $conf->entity);
		$sql .= " ORDER BY rowid DESC";

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->fk_template;
		}

		return 0;
	}

	/**
	 * Build the HTML row injected into Dolibarr's native project form.
	 *
	 * The field name is fk_ticket_template because the project trigger reads
	 * that POST value after Dolibarr saves the project.
	 */
	private function renderProjectTemplateSelect($selectedTemplateId)
	{
		global $conf, $langs;

		$out = '<tr><td>'.$langs->trans("TicketTemplate").'</td><td class="maxwidthonsmartphone">';
		$out .= '<select class="flat minwidth200" name="fk_ticket_template" id="fk_ticket_template">';
		$out .= '<option value="0">-- '.$langs->trans("Choose").' --</option>';

		$sql = "SELECT rowid, label";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_template";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " ORDER BY label";

		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$selected = ((int) $selectedTemplateId === (int) $obj->rowid) ? ' selected' : '';
			$out .= '<option value="'.((int) $obj->rowid).'"'.$selected.'>'.dol_escape_htmltag($obj->label).'</option>';
		}

		$out .= '</select>';
		$out .= '</td></tr>';

		return $out;
	}

	/**
	 * Render only the fields of the selected project template.
	 *
	 * FormTicket has a formObjectOptions hook, but does not print resPrint. For
	 * this native hook location we print from the hook and return 1, so Dolibarr
	 * does not also render the extrafields created manually from the interface.
	 */
	private function renderTicketTemplateOptionals($ticket, $templateFields)
	{
		$extrafields = new ExtraFields($this->db);
		$this->fillExtraFields($extrafields, $templateFields, 1);

		$out = '';
		foreach ($templateFields as $field) {
			$key = $this->getTechnicalAttrname($field);
			$type = $this->normalizeType($field->type);
			$value = $this->getTemplateFieldInputValue($ticket, $key, $type, $field->fielddefault);
			$requiredClass = !empty($field->fieldrequired) ? ' fieldrequired' : '';

			$out .= '<tr>';
			$out .= '<td class="titlefieldcreate'.$requiredClass.'">'.dol_escape_htmltag($field->label).'</td>';
			$out .= '<td>'.$extrafields->showInputField($key, $value, '', '', 'options_', '', $ticket, 'ticket').'</td>';
			$out .= '</tr>';
		}

		return $out;
	}

	/**
	 * Load the current ticket for edit mode so custom values are prefilled.
	 */
	private function fetchCurrentTicketForOptionals($ticket, $action)
	{
		if (!in_array($action, array('edit', 'update'), true)) {
			return $ticket;
		}

		$id = GETPOSTINT('id');
		$trackid = GETPOST('track_id', 'alphanohtml');
		if ($trackid === '') {
			$trackid = GETPOST('trackid', 'alphanohtml');
		}
		if ($id <= 0 && is_object($ticket) && !empty($ticket->id)) {
			$id = (int) $ticket->id;
		}
		if ($trackid === '' && is_object($ticket) && !empty($ticket->track_id)) {
			$trackid = $ticket->track_id;
		}

		$currentTicket = new Ticket($this->db);
		if ($currentTicket->fetch($id, '', $trackid) > 0) {
			if (method_exists($currentTicket, 'fetch_optionals')) {
				$currentTicket->fetch_optionals();
			}
			return $currentTicket;
		}

		return $ticket;
	}

	/**
	 * Read project id from the different parameter names used by Dolibarr pages.
	 */
	private function getProjectIdFromRequest($object = null)
	{
		$projectid = GETPOSTINT('projectid');
		if ($projectid <= 0) {
			$projectid = GETPOSTINT('fk_project');
		}
		if ($projectid <= 0) {
			$projectid = GETPOSTINT('project_id');
		}
		if ($projectid <= 0 && is_object($object) && !empty($object->fk_project)) {
			$projectid = (int) $object->fk_project;
		}

		return $projectid;
	}

	/**
	 * Fetch the template associated with a project.
	 */
	private function fetchProjectTemplate($projectid)
	{
		global $conf;

		$sql = "SELECT tt.*";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_template as tt";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."tickets_project_template as tpt ON tpt.fk_template = tt.rowid";
		$sql .= " WHERE tpt.fk_project = ".((int) $projectid);
		$sql .= " AND tpt.entity = ".((int) $conf->entity);
		$sql .= " AND tt.entity = ".((int) $conf->entity);
		$sql .= " ORDER BY tpt.rowid DESC";

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return $this->db->fetch_object($resql);
		}

		return null;
	}

	/**
	 * Fetch active fields for one ticket template, ordered for display.
	 */
	private function fetchTemplateFields($templateid)
	{
		$fields = array();

		$sql = "SELECT *";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_template_field";
		$sql .= " WHERE fk_template = ".((int) $templateid);
		$sql .= " AND enabled = 1";
		$sql .= " ORDER BY pos ASC, rowid ASC";

		$resql = $this->db->query($sql);
		while ($resql && ($field = $this->db->fetch_object($resql))) {
			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Validate required custom template fields before ticket creation.
	 */
	private function validateRequiredFields($fields, $extrafields)
	{
		global $langs;

		$missing = array();

		foreach ($fields as $field) {
			if (empty($field->fieldrequired)) {
				continue;
			}

			$key = $this->getTechnicalAttrname($field);
			$type = $this->normalizeType($field->type);

			if ($this->isPostedFieldEmpty($key, $type, $extrafields)) {
				$missing[] = $field->label;
			}
		}

		if (!empty($missing)) {
			$langs->load('errors');
			setEventMessages($langs->trans('ErrorFieldsRequired').' : '.implode(', ', $missing), null, 'errors');
			return false;
		}

		return true;
	}

	/**
	 * Check whether a posted custom field is empty according to its Dolibarr type.
	 */
	private function isPostedFieldEmpty($key, $type, $extrafields)
	{
		if ($type === 'date' || $type === 'datetime' || $type === 'datetimegmt') {
			return !GETPOSTINT('options_'.$key.'year') || !GETPOSTINT('options_'.$key.'month') || !GETPOSTINT('options_'.$key.'day');
		}

		if ($type === 'duration') {
			return !GETPOSTINT('options_'.$key.'hour') && !GETPOSTINT('options_'.$key.'min');
		}

		$value = $_POST['options_'.$key] ?? null;

		return ExtraFields::isEmptyValue($value, $type);
	}

	/**
	 * Resolve the value to display for one template field.
	 *
	 * POST has priority so validation errors keep the user's input. Existing
	 * ticket values are used on edit, then the template default is used last.
	 */
	private function getTemplateFieldInputValue($ticket, $key, $type, $default)
	{
		if (GETPOSTISSET('options_'.$key)) {
			$postvalue = GETPOST('options_'.$key, ($type == 'html' || $type == 'text') ? 'restricthtml' : 'alphanohtml', 3);
			return is_array($postvalue) ? implode(',', $postvalue) : $postvalue;
		}

		if ($type == 'date' && (GETPOSTISSET('options_'.$key.'day') || GETPOSTISSET('options_'.$key.'month') || GETPOSTISSET('options_'.$key.'year'))) {
			return dol_mktime(12, 0, 0, GETPOSTINT('options_'.$key.'month'), GETPOSTINT('options_'.$key.'day'), GETPOSTINT('options_'.$key.'year'));
		}

		if ($type == 'datetime' && (GETPOSTISSET('options_'.$key.'day') || GETPOSTISSET('options_'.$key.'month') || GETPOSTISSET('options_'.$key.'year'))) {
			return dol_mktime(GETPOSTINT('options_'.$key.'hour'), GETPOSTINT('options_'.$key.'min'), GETPOSTINT('options_'.$key.'sec'), GETPOSTINT('options_'.$key.'month'), GETPOSTINT('options_'.$key.'day'), GETPOSTINT('options_'.$key.'year'), 'tzuserrel');
		}

		if (isset($ticket->array_options['options_'.$key])) {
			return $ticket->array_options['options_'.$key];
		}

		if (isset($ticket->{'options_'.$key})) {
			return $ticket->{'options_'.$key};
		}

		return $default;
	}

	/**
	 * Build an in-memory ExtraFields definition from template field rows.
	 *
	 * This lets Dolibarr render inputs for fields that are defined by our custom
	 * model table, while still using Dolibarr's native extrafield rendering code.
	 */
	private function fillExtraFields($extrafields, $fields, $visible)
	{
		global $conf;

		$elementtype = 'ticket';
		$extrafields->attributes[$elementtype] = $this->emptyAttributes();
		$extrafields->attributes[$elementtype]['count'] = count($fields);

		foreach ($fields as $field) {
			$key = $this->getTechnicalAttrname($field);
			$options = $this->decodeOptions($field->options_json);

			$extrafields->attributes[$elementtype]['label'][$key] = $field->label;
			$extrafields->attributes[$elementtype]['type'][$key] = $this->normalizeType($field->type);
			$extrafields->attributes[$elementtype]['size'][$key] = $field->size;
			$extrafields->attributes[$elementtype]['default'][$key] = $field->fielddefault;
			$extrafields->attributes[$elementtype]['computed'][$key] = !empty($options['computed']) ? $options['computed'] : '';
			$extrafields->attributes[$elementtype]['unique'][$key] = !empty($options['unique']) ? 1 : 0;
			$extrafields->attributes[$elementtype]['required'][$key] = (int) $field->fieldrequired;
			$extrafields->attributes[$elementtype]['param'][$key] = $this->paramToArray($field->param);
			$extrafields->attributes[$elementtype]['perms'][$key] = !empty($options['perms']) ? $options['perms'] : '';
			$extrafields->attributes[$elementtype]['list'][$key] = $visible ? '1' : '0';
			$extrafields->attributes[$elementtype]['pos'][$key] = (int) $field->pos;
			$extrafields->attributes[$elementtype]['totalizable'][$key] = !empty($options['totalizable']) ? 1 : 0;
			$extrafields->attributes[$elementtype]['help'][$key] = !empty($options['help']) ? $options['help'] : '';
			$extrafields->attributes[$elementtype]['printable'][$key] = !empty($options['printable']) ? (int) $options['printable'] : 0;
			$extrafields->attributes[$elementtype]['enabled'][$key] = '1';
			$extrafields->attributes[$elementtype]['langfile'][$key] = !empty($options['langfile']) ? $options['langfile'] : '';
			$extrafields->attributes[$elementtype]['css'][$key] = !empty($options['css']) ? $options['css'] : '';
			$extrafields->attributes[$elementtype]['cssview'][$key] = !empty($options['cssview']) ? $options['cssview'] : '';
			$extrafields->attributes[$elementtype]['csslist'][$key] = !empty($options['csslist']) ? $options['csslist'] : '';
			$extrafields->attributes[$elementtype]['alwayseditable'][$key] = isset($options['alwayseditable']) ? (int) $options['alwayseditable'] : 1;
			$extrafields->attributes[$elementtype]['emptyonclone'][$key] = !empty($options['emptyonclone']) ? 1 : 0;
			$extrafields->attributes[$elementtype]['entityid'][$key] = $conf->entity;
			$extrafields->attributes[$elementtype]['aiprompt'][$key] = !empty($options['aiprompt']) ? $options['aiprompt'] : '';
		}
	}

	/**
	 * Ensure custom template fields also exist in Dolibarr extrafields storage.
	 *
	 * The ticket object persists extra fields through Dolibarr's normal
	 * extrafields mechanism, so the template definitions must be mirrored there
	 * before creating a ticket.
	 */
	private function syncDolibarrExtraFields($fields)
	{
		global $conf;

		$extrafields = new ExtraFields($this->db);

		foreach ($fields as $field) {
			$options = $this->decodeOptions($field->options_json);
			$type = $this->normalizeType($field->type);
			$param = $this->paramToArray($field->param);
			$attrname = $this->getTechnicalAttrname($field);
			$storageRequired = 0;
			$moreparams = array(
				'css' => !empty($options['css']) ? $options['css'] : '',
				'cssview' => !empty($options['cssview']) ? $options['cssview'] : '',
				'csslist' => !empty($options['csslist']) ? $options['csslist'] : '',
				'tickets_template_id' => (int) $field->fk_template,
				'tickets_template_field_id' => (int) $field->rowid,
				'tickets_template_attrname' => $field->attrname,
			);

			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."extrafields";
			$sql .= " WHERE name = '".$this->db->escape($attrname)."'";
			$sql .= " AND elementtype = 'ticket'";
			$sql .= " AND entity = ".((int) $conf->entity);
			$resql = $this->db->query($sql);
			$exists = ($resql && $this->db->num_rows($resql) > 0);

			if ($exists) {
				$extrafields->updateExtraField($attrname, $field->label, $type, (int) $field->pos, $field->size, 'ticket', !empty($options['unique']) ? 1 : 0, $storageRequired, $field->fielddefault, $param, isset($options['alwayseditable']) ? (int) $options['alwayseditable'] : 1, !empty($options['perms']) ? $options['perms'] : '', '0', !empty($options['help']) ? $options['help'] : '', !empty($options['computed']) ? $options['computed'] : '', $conf->entity, !empty($options['langfile']) ? $options['langfile'] : '', '1', !empty($options['totalizable']) ? 1 : 0, !empty($options['printable']) ? (int) $options['printable'] : 0, $moreparams, !empty($options['emptyonclone']) ? 1 : 0);
			} else {
				$extrafields->addExtraField($attrname, $field->label, $type, (int) $field->pos, $field->size, 'ticket', !empty($options['unique']) ? 1 : 0, $storageRequired, $field->fielddefault, $param, isset($options['alwayseditable']) ? (int) $options['alwayseditable'] : 1, !empty($options['perms']) ? $options['perms'] : '', '0', !empty($options['help']) ? $options['help'] : '', !empty($options['computed']) ? $options['computed'] : '', $conf->entity, !empty($options['langfile']) ? $options['langfile'] : '', '1', !empty($options['totalizable']) ? 1 : 0, !empty($options['printable']) ? (int) $options['printable'] : 0, $moreparams, !empty($options['aiprompt']) ? $options['aiprompt'] : '', !empty($options['emptyonclone']) ? 1 : 0);
			}
		}
	}

	/**
	 * Synchronize every active template field before native ticket processing.
	 *
	 * This protects the save path: when Dolibarr later reads options_* fields,
	 * the corresponding extrafields already exist in its metadata table.
	 */
	private function syncAllTemplateExtraFieldsForStorage()
	{
		$fields = array();

		$sql = "SELECT *";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_template_field";
		$sql .= " WHERE enabled = 1";
		$sql .= " ORDER BY fk_template ASC, pos ASC, rowid ASC";

		$resql = $this->db->query($sql);
		while ($resql && ($field = $this->db->fetch_object($resql))) {
			$fields[] = $field;
		}

		if (!empty($fields)) {
			$this->syncDolibarrExtraFields($fields);
		}
	}

	/**
	 * Generate a stable Dolibarr extrafield technical name for a template field.
	 *
	 * The template id is included to avoid collisions between two templates that
	 * use the same business field name.
	 */
	private function getTechnicalAttrname($field)
	{
		$templateid = !empty($field->fk_template) ? (int) $field->fk_template : 0;
		$prefix = 'ttpl'.$templateid.'_';
		$base = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $field->attrname);
		$base = trim((string) $base, '_');

		if ($base === '') {
			$base = 'field';
		}
		if (preg_match('/^[0-9]/', $base)) {
			$base = 'f_'.$base;
		}
		if (strpos($base, $prefix) === 0) {
			return substr($base, 0, 64);
		}

		$suffix = '_'.substr(md5($base), 0, 6);
		$maxlength = 64 - strlen($prefix) - strlen($suffix);
		if ($maxlength < 1) {
			$maxlength = 1;
		}

		return $prefix.substr($base, 0, $maxlength).$suffix;
	}

	/**
	 * Convert option text stored on a template field into ExtraFields parameters.
	 */
	private function paramToArray($param)
	{
		$out = array('options' => array());
		$lines = preg_split('/\r\n|\r|\n/', (string) $param);

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$parts = preg_split('/[,=]/', $line, 2);
			$key = trim($parts[0]);
			$label = isset($parts[1]) ? trim($parts[1]) : $key;

			if ($key !== '') {
				$out['options'][$key] = ($label !== '' ? $label : $key);
			}
		}

		return $out;
	}

	/**
	 * Decode optional field metadata stored as JSON.
	 */
	private function decodeOptions($json)
	{
		$options = json_decode((string) $json, true);
		return is_array($options) ? $options : array();
	}

	/**
	 * Normalize an empty/custom type to a Dolibarr-compatible extrafield type.
	 */
	private function normalizeType($type)
	{
		return ($type === '' || $type === '0') ? 'varchar' : $type;
	}

	/**
	 * Return an empty ExtraFields attributes structure.
	 */
	private function emptyAttributes()
	{
		return array(
			'label' => array(),
			'type' => array(),
			'size' => array(),
			'default' => array(),
			'computed' => array(),
			'unique' => array(),
			'required' => array(),
			'param' => array(),
			'perms' => array(),
			'list' => array(),
			'pos' => array(),
			'totalizable' => array(),
			'help' => array(),
			'printable' => array(),
			'enabled' => array(),
			'langfile' => array(),
			'css' => array(),
			'cssview' => array(),
			'csslist' => array(),
			'alwayseditable' => array(),
			'emptyonclone' => array(),
			'entityid' => array(),
			'aiprompt' => array(),
			'count' => 0,
		);
	}
}
