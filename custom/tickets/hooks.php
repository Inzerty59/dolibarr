<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/hooks.php
 * \ingroup     tickets
 * \brief       Déclaration des hooks du module Tickets
 */

// Inclure la classe des hooks
require_once __DIR__.'/class/hooks.class.php';

// Instancier la classe pour que les hooks soient exécutés
$ticketsHooks = new TicketsHooks();
