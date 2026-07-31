<?php

require_once __DIR__.'/mondayinboundemailprocessor.class.php';

class MondayCollectorBootstrap
{
    public static function ensureEmailCollectorHookActions($db)
    {
        if (!class_exists('MondayInboundEmailProcessor')) {
            return false;
        }

        $processor = new MondayInboundEmailProcessor($db);
        return $processor->ensureEmailCollectorHookActions();
    }
}
