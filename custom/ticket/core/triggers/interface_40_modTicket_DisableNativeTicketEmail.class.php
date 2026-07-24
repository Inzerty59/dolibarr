<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceDisableNativeTicketEmail extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'ticket';
		$this->description = 'Disable native ticket emails so custom templates are the only source of notifications.';
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'ticket';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('ticket') || empty($object->element) || $object->element !== 'ticket') {
			return 0;
		}

		if (in_array($action, array('TICKET_CREATE', 'TICKET_CLOSE'), true)) {
			$object->context['disableticketemail'] = 1;
		}

		if ($action === 'TICKET_ASSIGNED') {
			$conf->global->TICKET_DISABLE_ALL_MAILS = 1;
			$conf->global->TICKET_NOTIFY_CUSTOMER_TICKET_ASSIGNED = 0;
		}

		return 1;
	}
}
