<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once __DIR__.'/mondayinboundemailprocessor.class.php';

class ActionsMonday extends CommonHookActions
{
    public $priority = 100;

    public function doCollectImapOneCollector($parameters, &$object, &$action, $hookmanager)
    {
        global $db;

        $dbHandler = is_object($object) && !empty($object->db) ? $object->db : $db;
        $processor = new MondayInboundEmailProcessor($dbHandler);
        $result = $processor->processCollectorMessage(is_array($parameters) ? $parameters : array());

        $this->results = $result;
        $this->resArray = $result;
        $this->resPrint = '';
        $this->resprints = '';
        $this->error = '';
        $this->errors = array();

        if (empty($result['success'])) {
            return 0;
        }

        return 0;
    }
}
