<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../main.inc.php';
	$res = 1;
}
if (!$res) die('Cannot find Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

if (!$user->id || !$user->admin) accessforbidden();

$langs->loadLangs(array('tickets@tickets', 'ticket', 'admin'));

class TicketTemplateExtraFields extends ExtraFields
{
	public function fetch_name_optionals_label($elementtype, $forceload = false, $attrname = '')
	{
		return 1;
	}
}

function ticketTemplateEmptyAttributes()
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
	);
}

function ticketTemplateFieldFromPost()
{
	$param = GETPOST('param', 'restricthtml');

	return array(
		'label' => GETPOST('label', 'alphanohtml'),
		'type' => GETPOST('type', 'alphanohtml'),
		'size' => GETPOST('size', 'alphanohtml'),
		'default' => GETPOST('default_value', 'restricthtml'),
		'computed' => GETPOST('computed_value', 'restricthtml'),
		'unique' => GETPOST('unique', 'alpha') ? 1 : 0,
		'required' => GETPOST('required', 'alpha') ? 1 : 0,
		'param' => $param,
		'perms' => GETPOST('perms', 'alphanohtml'),
		'list' => GETPOST('list', 'alphanohtml'),
		'pos' => GETPOSTINT('pos'),
		'totalizable' => GETPOST('totalizable', 'alpha') ? 1 : 0,
		'help' => GETPOST('help', 'restricthtml'),
		'printable' => GETPOSTINT('printable'),
		'langfile' => GETPOST('langfile', 'alphanohtml'),
		'css' => GETPOST('css', 'alphanohtml'),
		'cssview' => GETPOST('cssview', 'alphanohtml'),
		'csslist' => GETPOST('csslist', 'alphanohtml'),
		'alwayseditable' => GETPOST('alwayseditable', 'alpha') ? 1 : 0,
		'emptyonclone' => GETPOST('emptyonclone', 'alpha') ? 1 : 0,
		'enabled' => 1,
		'aiprompt' => GETPOST('ai_prompt', 'restricthtml'),
	);
}

function ticketTemplateParamToArray($param)
{
	$out = array('options' => array());

	if (is_array($param)) return $param;

	$lines = preg_split('/\r\n|\r|\n/', (string) $param);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '') continue;

		$tmp = explode(',', $line, 2);
		$key = trim($tmp[0]);
		$value = isset($tmp[1]) ? trim($tmp[1]) : null;
		$out['options'][$key] = $value;
	}

	return $out;
}

$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'aZ09');
$id = GETPOST('id', 'int');
$token = newToken();

if ($action == 'newmodel' && GETPOST('reset', 'int')) {
	unset($_SESSION['ticket_template_fields']);
	unset($_SESSION['ticket_template_label']);
	unset($_SESSION['ticket_template_edit_id']);
	unset($_SESSION['ticket_template_loaded_id']);
}

if (GETPOST('template_label', 'alphanohtml')) {
	$_SESSION['ticket_template_label'] = GETPOST('template_label', 'alphanohtml');
}

if (empty($_SESSION['ticket_template_fields'])) {
	$_SESSION['ticket_template_fields'] = array();
}

if (($action == 'editmodel' || $action == 'editmode') && $id > 0) {
	$resql = $db->query("SELECT label FROM ".MAIN_DB_PREFIX."tickets_template WHERE rowid=".(int) $id);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$_SESSION['ticket_template_label'] = $obj->label;
		$_SESSION['ticket_template_edit_id'] = $id;
		$_SESSION['ticket_template_loaded_id'] = $id;
		$_SESSION['ticket_template_fields'] = array();

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."tickets_template_field";
		$sql .= " WHERE fk_template=".(int) $id;
		$sql .= " ORDER BY position ASC, rowid ASC";
		$resfields = $db->query($sql);
		if ($resfields) {
			while ($line = $db->fetch_object($resfields)) {
				$field = json_decode($line->definition_json, true);
				if (!is_array($field)) {
					$field = array(
						'label' => $line->label,
						'type' => $line->type,
						'size' => $line->size,
						'default' => '',
						'computed' => '',
						'unique' => 0,
						'required' => (int) $line->required,
						'param' => '',
						'perms' => '',
						'list' => '1',
						'pos' => (int) $line->position,
						'totalizable' => 0,
						'help' => '',
						'printable' => 0,
						'langfile' => '',
						'css' => '',
						'cssview' => '',
						'csslist' => '',
						'alwayseditable' => 1,
						'emptyonclone' => 0,
						'enabled' => 1,
						'aiprompt' => '',
					);
				}

				$_SESSION['ticket_template_fields'][$line->attrname] = $field;
			}
		}
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?action=newmodel');
	exit;
}

if ($action == 'add') {
	if (GETPOST("button") != $langs->trans("Cancel")) {
		$attrname = GETPOST('attrname', 'aZ09');
		if ($attrname) {
			$_SESSION['ticket_template_fields'][$attrname] = ticketTemplateFieldFromPost();
		}
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?action=newmodel');
	exit;
}

if ($action == 'update') {
	$attrname = GETPOST('attrname', 'aZ09');
	if ($attrname && isset($_SESSION['ticket_template_fields'][$attrname])) {
		$_SESSION['ticket_template_fields'][$attrname] = ticketTemplateFieldFromPost();
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?action=newmodel');
	exit;
}

if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
	$attrname = GETPOST('attrname', 'aZ09');
	if ($attrname && isset($_SESSION['ticket_template_fields'][$attrname])) {
		unset($_SESSION['ticket_template_fields'][$attrname]);
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?action=newmodel');
	exit;
}

if ($action == 'confirm_delete_template' && GETPOST('confirm', 'alpha') == 'yes') {
	$templateid = !empty($_SESSION['ticket_template_edit_id']) ? (int) $_SESSION['ticket_template_edit_id'] : GETPOST('id', 'int');

	if ($templateid > 0) {
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."tickets_template_field WHERE fk_template=".(int) $templateid);
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."tickets_template WHERE rowid=".(int) $templateid);
	}

	unset($_SESSION['ticket_template_fields']);
	unset($_SESSION['ticket_template_label']);
	unset($_SESSION['ticket_template_edit_id']);
	unset($_SESSION['ticket_template_loaded_id']);

	header('Location: '.DOL_URL_ROOT.'/custom/tickets/template_list.php');
	exit;
}

if ($action == 'savefinal' && GETPOST('token', 'alphanohtml') == $_SESSION['newtoken']) {
	$label = trim(GETPOST('template_label', 'alphanohtml'));

	if ($label == '') {
		$label = !empty($_SESSION['ticket_template_label']) ? $_SESSION['ticket_template_label'] : '';
	}

	if ($label == '') {
		setEventMessages('Le nom du modèle est obligatoire.', null, 'errors');
	} else {
	if (!empty($_SESSION['ticket_template_edit_id'])) {
		$id = (int) $_SESSION['ticket_template_edit_id'];

		$sql = "UPDATE ".MAIN_DB_PREFIX."tickets_template SET";
		$sql .= " label='".$db->escape($label)."',";
		$sql .= " fk_user_modif=".(int) $user->id;
		$sql .= " WHERE rowid=".(int) $id;
		$db->query($sql);

		$db->query("DELETE FROM ".MAIN_DB_PREFIX."tickets_template_field WHERE fk_template=".(int) $id);
	} else {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."tickets_template";
			$sql .= " (entity, label, active, datec, fk_user_create)";
			$sql .= " VALUES (".$conf->entity.", '".$db->escape($label)."', 1, '".$db->idate(dol_now())."', ".(int) $user->id.")";
			$db->query($sql);

			$id = $db->last_insert_id(MAIN_DB_PREFIX."tickets_template");
	}


		foreach ($_SESSION['ticket_template_fields'] as $key => $field) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."tickets_template_field";
			$sql .= " (fk_template, attrname, label, type, size, required, position, enabled, definition_json)";
			$sql .= " VALUES (";
			$sql .= (int) $id.",";
			$sql .= " '".$db->escape($key)."',";
			$sql .= " '".$db->escape($field['label'])."',";
			$sql .= " '".$db->escape($field['type'])."',";
			$sql .= " '".$db->escape($field['size'])."',";
			$sql .= " ".(int) $field['required'].",";
			$sql .= " ".(int) $field['pos'].",";
			$sql .= " 1,";
			$sql .= " '".$db->escape(json_encode($field))."'";
			$sql .= ")";
			$db->query($sql);
		}

		unset($_SESSION['ticket_template_fields']);
		unset($_SESSION['ticket_template_label']);
		unset($_SESSION['ticket_template_edit_id']);
        unset($_SESSION['ticket_template_loaded_id']);


		header('Location: '.DOL_URL_ROOT.'/custom/tickets/template_list.php');
		exit;
	}
}


$extrafields = new TicketTemplateExtraFields($db);
$form = new Form($db);
$formadmin = new FormAdmin($db);

$type2label = ExtraFields::getListOfTypesLabels();
$elementtype = 'ticket';
$textobject = $langs->transnoentitiesnoconv("Ticket");

$extrafields->attributes[$elementtype] = ticketTemplateEmptyAttributes();

foreach ($_SESSION['ticket_template_fields'] as $key => $field) {
	$extrafields->attributes[$elementtype]['label'][$key] = $field['label'];
	$extrafields->attributes[$elementtype]['type'][$key] = $field['type'];
	$extrafields->attributes[$elementtype]['size'][$key] = $field['size'];
	$extrafields->attributes[$elementtype]['default'][$key] = $field['default'];
	$extrafields->attributes[$elementtype]['computed'][$key] = $field['computed'];
	$extrafields->attributes[$elementtype]['unique'][$key] = $field['unique'];
	$extrafields->attributes[$elementtype]['required'][$key] = $field['required'];
	$extrafields->attributes[$elementtype]['param'][$key] = ticketTemplateParamToArray($field['param']);
	$extrafields->attributes[$elementtype]['perms'][$key] = $field['perms'];
	$extrafields->attributes[$elementtype]['list'][$key] = $field['list'];
	$extrafields->attributes[$elementtype]['pos'][$key] = $field['pos'];
	$extrafields->attributes[$elementtype]['totalizable'][$key] = $field['totalizable'];
	$extrafields->attributes[$elementtype]['help'][$key] = $field['help'];
	$extrafields->attributes[$elementtype]['printable'][$key] = $field['printable'];
	$extrafields->attributes[$elementtype]['enabled'][$key] = 1;
	$extrafields->attributes[$elementtype]['langfile'][$key] = $field['langfile'];
	$extrafields->attributes[$elementtype]['css'][$key] = $field['css'];
	$extrafields->attributes[$elementtype]['cssview'][$key] = $field['cssview'];
	$extrafields->attributes[$elementtype]['csslist'][$key] = $field['csslist'];
	$extrafields->attributes[$elementtype]['alwayseditable'][$key] = $field['alwayseditable'];
	$extrafields->attributes[$elementtype]['emptyonclone'][$key] = $field['emptyonclone'];
	$extrafields->attributes[$elementtype]['entityid'][$key] = $conf->entity;
	$extrafields->attributes[$elementtype]['aiprompt'][$key] = $field['aiprompt'];
}
if (GETPOST('template_label', 'alphanohtml')) {
	$_SESSION['ticket_template_label'] = GETPOST('template_label', 'alphanohtml');
}

llxHeader('', 'Créer un modèle', '');

if ($action == 'delete') {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?action=confirm_delete&attrname='.$attrname,
		$langs->trans("DeleteExtrafield"),
		$langs->trans("ConfirmDeleteExtrafield", $attrname),
		"confirm_delete",
		'',
		0,
		1
	);
}

if ($action == 'delete_template') {
	$templateid = !empty($_SESSION['ticket_template_edit_id']) ? (int) $_SESSION['ticket_template_edit_id'] : GETPOST('id', 'int');
	if ($templateid > 0) {
		$confirmurl = $_SERVER['PHP_SELF'].'?action=confirm_delete_template&id='.$templateid.'&confirm=yes&token='.newToken();
		$cancelurl = $_SERVER['PHP_SELF'].'?action=newmodel';

		print '<div class="ticket-template-confirm-backdrop">';
		print '<div class="ticket-template-confirm">';
		print '<div class="ticket-template-confirm-title">Supprimer le modèle</div>';
		print '<div class="ticket-template-confirm-body">Voulez-vous vraiment supprimer ce modèle ?</div>';
		print '<div class="ticket-template-confirm-actions">';
		print '<a class="button" href="'.$confirmurl.'">Oui</a>';
		print '<a class="button" href="'.$cancelurl.'">Non</a>';
		print '</div>';
		print '</div>';
		print '</div>';
	}
}

print '<style>
.ticket-template-confirm-backdrop {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.18);
	z-index: 1000;
	display: flex;
	align-items: center;
	justify-content: center;
}
.ticket-template-confirm {
	width: 360px;
	max-width: calc(100vw - 40px);
	background: #fff;
	border-radius: 4px;
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22);
	padding: 16px;
}
.ticket-template-confirm-title {
	font-weight: 600;
	margin-bottom: 12px;
}
.ticket-template-confirm-body {
	margin-bottom: 18px;
}
.ticket-template-confirm-actions {
	text-align: right;
}
.ticket-template-confirm-actions .button {
	margin-left: 8px;
}
</style>';

print load_fiche_titre('Créer un modèle', '', 'ticket');

$valueLabel = GETPOST('template_label', 'alphanohtml');
if ($valueLabel == '' && !empty($_SESSION['ticket_template_label'])) {
	$valueLabel = $_SESSION['ticket_template_label'];
}


print '<table class="border centpercent">';
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">Nom du modèle</td>';
print '<td><input id="template_label_visible" class="flat minwidth300" name="template_label_visible" value="'.dol_escape_htmltag($valueLabel).'"></td>';
print '</tr>';
print '</table>';

print '<br>';

require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';


if ($action == 'create') {
	print '<br>';
	print load_fiche_titre($langs->trans('NewAttribute'));
	include DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}

if ($action == 'edit' && !empty($attrname)) {
	print '<br>';
	print load_fiche_titre($langs->trans("FieldEdition", $attrname));
	include DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}
print '<br>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="save-template-form">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="action" value="savefinal">';
print '<input type="hidden" name="template_label" id="template_label_hidden" value="'.dol_escape_htmltag($valueLabel).'">';
print '<div class="right ticket-template-actions">';
print '<a class="button" href="'.DOL_URL_ROOT.'/custom/tickets/template_list.php">Annuler</a> ';
print '<input type="submit" class="button" value="Sauvegarder">';
if (!empty($_SESSION['ticket_template_edit_id'])) {
	print ' <a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?action=delete_template&id='.(int) $_SESSION['ticket_template_edit_id'].'&token='.newToken().'">Supprimer</a>';
}
print '</div>';
print '</form>';

?>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var visibleLabel = document.getElementById('template_label_visible');
	var hiddenLabel = document.getElementById('template_label_hidden');
	var saveForm = document.getElementById('save-template-form');

	if (saveForm && visibleLabel && hiddenLabel) {
		saveForm.addEventListener('submit', function () {
			hiddenLabel.value = visibleLabel.value;
		});
	}

	document.querySelectorAll('a[href*="action=create"]').forEach(function (link) {
		link.addEventListener('click', function () {
			if (!visibleLabel) return;

			var separator = link.href.indexOf('?') === -1 ? '?' : '&';
			link.href = link.href + separator + 'template_label=' + encodeURIComponent(visibleLabel.value);
		});
	});
});
</script>
<?php

llxFooter();
$db->close();
