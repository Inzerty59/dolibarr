<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

class FormTicketWrapper extends FormTicket {
	private $forced_project_id = 0;
	
	public function __construct($db) {
		parent::__construct($db);
		$this->forced_project_id = !empty($GLOBALS['forced_project_id']) ? (int)$GLOBALS['forced_project_id'] : 0;
	}
	
	public function showForm($mode_creation = 0, $mode_edit = '', $mode_view = 0, $mode_from_mass_action = null, $action = '', $object = null) {
		global $db, $conf;
		
		if ($this->forced_project_id && $object) {
			$object->fk_project = $this->forced_project_id;
		}
		
		ob_start();
		parent::showForm($mode_creation, $mode_edit, $mode_view, $mode_from_mass_action, $action, $object);
		$html = ob_get_clean();
		
		if ($this->forced_project_id) {
			$projectName = 'Projet';
			$projectObj = new Project($db);
			if ($projectObj->fetch($this->forced_project_id) > 0) {
				$projectName = $projectObj->ref . ' - ' . $projectObj->title;
			}
			
			$projectDisplay = '<div style="padding: 8px 12px; border: 1px solid #ddd; background-color: #f5f5f5; border-radius: 4px; font-size: 14px; color: #333; cursor: default; min-height: 36px; display: flex; align-items: center; font-weight: 500;">' . htmlspecialchars($projectName) . '</div>';
			
			$html = preg_replace(
				'/<span[^>]*class="select2-container[^"]*"[^>]*id="select2-projectid"[^>]*>.*?<\/span>\s*<select[^>]*name="projectid"[^>]*>.*?<\/select>/is',
				$projectDisplay . '<input type="hidden" name="projectid" value="' . (int)$this->forced_project_id . '" />',
				$html
			);
			
			if (strpos($html, 'name="projectid"') !== false && strpos($html, 'type="hidden" name="projectid"') === false) {
				$html = preg_replace(
					'/<select[^>]*name="projectid"[^>]*>.*?<\/select>/is',
					'<input type="hidden" name="projectid" value="' . (int)$this->forced_project_id . '" />',
					$html
				);
				
				$html = preg_replace(
					'/<span[^>]*class="select2-container[^"]*"[^>]*>.*?<\/span>\s*<input type="hidden" name="projectid"/is',
					$projectDisplay . '<input type="hidden" name="projectid"',
					$html
				);
			}
		}
		
		echo $html;
	}
}
