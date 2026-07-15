<?php
/*
 * T6 - Hook class alias.
 *
 * Dolibarr loads hook classes by module naming convention. This lightweight
 * class exposes ActionsTickets under the expected custom module hook class
 * while keeping the real implementation in actions_tickets.class.php.
 */

require_once __DIR__.'/actions_tickets.class.php';

class TicketsHooks extends ActionsTickets
{
}
