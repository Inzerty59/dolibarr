<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';

class FormTicketCustom extends FormTicket {
	
	public function showForm($mode_creation = 0, $mode_edit = '', $mode_view = 0, $mode_from_mass_action = null, $action = '', $object = null) {
		global $forced_project_id, $db, $langs, $conf;
		
		if (!empty($forced_project_id) && $object) {
			$object->fk_project = (int)$forced_project_id;
		}
		
		return parent::showForm($mode_creation, $mode_edit, $mode_view, $mode_from_mass_action, $action, $object);
	}
}

if (!class_exists('FormTicket_Original')) {
	class_alias('FormTicket', 'FormTicket_Original');
}
class_alias('FormTicketCustom', 'FormTicket');
