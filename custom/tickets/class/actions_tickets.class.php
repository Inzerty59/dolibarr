<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';

if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
}

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

	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (!$this->isTicketCard()) {
			return 0;
		}

		$projectid = $this->getProjectIdFromRequest();

		if ($action === 'create' && $projectid <= 0) {
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
			exit;
		}

		if ($action === 'add_project_template_ticket') {
			if (!$user->hasRight('ticket', 'write')) {
				accessforbidden('NotEnoughPermissions', 0, 1);
			}

			$this->createTicketFromTemplate($object, $projectid);
			return 1;
		}

		if ($action !== 'create' || $projectid <= 0) {
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

		$this->renderTemplateCreateForm($object, $projectid, $template, $fields);
		exit;
	}

	private function isTicketCard()
	{
		return strpos($_SERVER['PHP_SELF'] ?? '', '/ticket/card.php') !== false;
	}

	private function getProjectIdFromRequest()
	{
		$projectid = GETPOSTINT('projectid');
		if ($projectid <= 0) {
			$projectid = GETPOSTINT('fk_project');
		}
		if ($projectid <= 0) {
			$projectid = GETPOSTINT('project_id');
		}

		return $projectid;
	}

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

	private function createTicketFromTemplate(&$object, $projectid)
	{
		global $langs, $user;

		$token = GETPOST('token', 'alphanohtml');
		if (empty($token) || $token !== currentToken()) {
			accessforbidden('Invalid token', 0, 1);
		}

		$templateid = GETPOSTINT('template_id');
		$template = $this->fetchProjectTemplate($projectid);
		if (empty($template) || (int) $template->rowid !== $templateid) {
			accessforbidden('Invalid ticket template', 0, 1);
		}

		$fields = $this->fetchTemplateFields($templateid);
		if (empty($fields)) {
			setEventMessages('Aucun champ actif sur le modèle de ticket.', null, 'errors');
			$this->renderTemplateCreateForm($object, $projectid, $template, $fields);
			exit;
		}

		$this->syncDolibarrExtraFields($fields);

		$templateExtraFields = new ExtraFields($this->db);
		$this->fillExtraFields($templateExtraFields, $fields, 1);

		if (!$this->validateRequiredFields($fields, $templateExtraFields)) {
			$this->renderTemplateCreateForm($object, $projectid, $template, $fields);
			exit;
		}

		$ret = $templateExtraFields->setOptionalsFromPost(null, $object);
		if ($ret < 0) {
			setEventMessages($templateExtraFields->error, $templateExtraFields->errors, 'errors');
			$this->renderTemplateCreateForm($object, $projectid, $template, $fields);
			exit;
		}

		$object->fk_project = $projectid;
		$object->fk_user_create = $user->id;
		$object->ref = $object->getDefaultRef();
		$object->subject = $this->getTicketSubjectFromFields($fields, $template);
		$object->message = '';

		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans("TicketCreated"), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/ticket/card.php?track_id='.urlencode($object->track_id));
			exit;
		}

		setEventMessages($object->error, $object->errors, 'errors');
		$this->renderTemplateCreateForm($object, $projectid, $template, $fields);
		exit;
	}

	private function getTicketSubjectFromFields($fields, $template)
	{
		foreach ($fields as $field) {
			$key = 'options_'.$this->getTechnicalAttrname($field);
			if (GETPOSTISSET($key)) {
				$value = GETPOST($key, 'alphanohtml');
				if (is_array($value)) {
					$value = implode(', ', $value);
				}
				$value = trim((string) $value);
				if ($value !== '') {
					return dol_trunc($value, 255);
				}
			}
		}

		return dol_trunc(!empty($template->label) ? $template->label : 'Ticket', 255);
	}

	private function renderTemplateCreateForm(&$object, $projectid, $template, $fields)
	{
		global $conf, $langs;

		$this->syncDolibarrExtraFields($fields);

		$templateExtraFields = new ExtraFields($this->db);
		$this->fillExtraFields($templateExtraFields, $fields, 1);

		$title = $langs->trans('NewTicket');
		llxHeader('', $title, 'EN:Module_Ticket|FR:DocumentationModuleTicket', '', 0, 0, '', '', '', 'mod-ticket page-card');

		print load_fiche_titre($title, '', 'ticket');

		$projectlabel = '';
		if (isModEnabled('project') && class_exists('Project')) {
			$project = new Project($this->db);
			if ($project->fetch($projectid) > 0) {
				$projectlabel = $project->ref.' - '.$project->title;
			}
		}

		print '<form action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add_project_template_ticket">';
		print '<input type="hidden" name="projectid" value="'.((int) $projectid).'">';
		print '<input type="hidden" name="template_id" value="'.((int) $template->rowid).'">';

		print dol_get_fiche_head();
		print '<table class="border centpercent tableforfieldcreate">';

		if ($projectlabel !== '') {
			print '<tr><td class="titlefieldcreate">'.$langs->trans('Project').'</td>';
			print '<td><strong>'.dol_escape_htmltag($projectlabel).'</strong></td></tr>';
		}

		print $this->showNativeFields($object, $templateExtraFields, $fields);

		print '</table>';
		print dol_get_fiche_end();

		$form = new Form($this->db);
		print $form->buttonsSaveCancel('CreateTicket', 'Cancel');
		print '</form>';

		llxFooter();
	}

	private function showNativeFields($object, $extrafields, $fields)
	{
		$out = '';

		foreach ($fields as $field) {
			$key = $this->getTechnicalAttrname($field);
			$type = $this->normalizeType($field->type);
			$value = $this->getInputValue($key, $type, $field->fielddefault);
			$requiredClass = !empty($field->fieldrequired) ? ' fieldrequired' : '';

			$out .= '<tr>';
			$out .= '<td class="titlefieldcreate'.$requiredClass.'">'.dol_escape_htmltag($field->label).'</td>';
			$out .= '<td>'.$extrafields->showInputField($key, $value, '', '', 'options_', '', $object, 'ticket').'</td>';
			$out .= '</tr>';
		}

		return $out;
	}

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

	private function getInputValue($key, $type, $default)
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

		return $default;
	}

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

	private function syncDolibarrExtraFields($fields)
	{
		global $conf;

		$extrafields = new ExtraFields($this->db);

		foreach ($fields as $field) {
			$options = $this->decodeOptions($field->options_json);
			$type = $this->normalizeType($field->type);
			$param = $this->paramToArray($field->param);
			$attrname = $this->getTechnicalAttrname($field);
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
				$extrafields->updateExtraField($attrname, $field->label, $type, (int) $field->pos, $field->size, 'ticket', !empty($options['unique']) ? 1 : 0, (int) $field->fieldrequired, $field->fielddefault, $param, isset($options['alwayseditable']) ? (int) $options['alwayseditable'] : 1, !empty($options['perms']) ? $options['perms'] : '', '0', !empty($options['help']) ? $options['help'] : '', !empty($options['computed']) ? $options['computed'] : '', $conf->entity, !empty($options['langfile']) ? $options['langfile'] : '', '1', !empty($options['totalizable']) ? 1 : 0, !empty($options['printable']) ? (int) $options['printable'] : 0, $moreparams, !empty($options['emptyonclone']) ? 1 : 0);
			} else {
				$extrafields->addExtraField($attrname, $field->label, $type, (int) $field->pos, $field->size, 'ticket', !empty($options['unique']) ? 1 : 0, (int) $field->fieldrequired, $field->fielddefault, $param, isset($options['alwayseditable']) ? (int) $options['alwayseditable'] : 1, !empty($options['perms']) ? $options['perms'] : '', '0', !empty($options['help']) ? $options['help'] : '', !empty($options['computed']) ? $options['computed'] : '', $conf->entity, !empty($options['langfile']) ? $options['langfile'] : '', '1', !empty($options['totalizable']) ? 1 : 0, !empty($options['printable']) ? (int) $options['printable'] : 0, $moreparams, !empty($options['aiprompt']) ? $options['aiprompt'] : '', !empty($options['emptyonclone']) ? 1 : 0);
			}
		}
	}

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

	private function decodeOptions($json)
	{
		$options = json_decode((string) $json, true);
		return is_array($options) ? $options : array();
	}

	private function normalizeType($type)
	{
		return ($type === '' || $type === '0') ? 'varchar' : $type;
	}

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
