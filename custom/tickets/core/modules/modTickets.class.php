<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   tickets     Module Tickets Personnalisé
 * \brief      Module de gestion personnalisée des tickets
 *
 * \file       htdocs/custom/tickets/core/modules/modTickets.class.php
 * \ingroup    tickets
 * \brief      Description et activation du module Tickets
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description et activation du module Tickets
 */
class modTickets extends DolibarrModules
{
	/**
	 * Constructeur. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id pour le module (doit être unique)
		$this->numero = 500001;

		// Clé texte pour identifier le module (permissions, menus, etc...)
		$this->rights_class = 'tickets';

		// Famille du module
		$this->family = "other";

		// Position du module dans la famille
		$this->module_position = '91';

		// Nom du module
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Description du module
		$this->description = "TicketsDescription";

		// Auteur
		$this->editor_name = 'Florent';
		$this->editor_url = '';

		// Version
		$this->version = '1.0';

		// Clé utilisée pour sauvegarder le statut du module
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Pictogramme
		$this->picto = 'fa-ticket';

		// Définie les fonctionnalités supportées par le module
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'css' => 0,
			'js' => 1,
			'hooks' => array(
				'pageheader' => 'pageHeader',
			),
			'models' => 0,
		);

		// Droits du module
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.$r;
		$this->rights[$r][1] = 'Accès au module Tickets personnalisé';
		$this->rights[$r][4] = 'tickets';
		$this->rights[$r][5] = 'read';

		// Les répertoires doivent être relatifs à htdocs
		$this->dictionaries = array();

		// Les dépendances avec les autres modules
		$this->depends = array('modTicket'); // Dépend du module Ticket natif
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('tickets@tickets');
		$this->config_page_url = array('setup.php@tickets');
		$this->hidden = 0;
		$this->disabled = 0;
		$this->url_models_to_document = 'sample%;model_form.tpl';
		$this->url_models_to_document_list = 'sample%;list.tpl';

		if (is_object($langs)) {
			$langs->load('tickets@tickets');
		}

		// Inclure le fichier d'initialisation pour intercepter les redirections
		if (file_exists(__DIR__.'/../../../custom/tickets/init_redirect.php')) {
			require_once __DIR__.'/../../../custom/tickets/init_redirect.php';
		}
	}

	/**
	 * Fonction appelée lors de l'activation du module
	 *
	 * @param  Translate $langs Lang object
	 * @return boolean          True si tout est OK, False sinon
	 */
	public function init($langs = null)
	{
		$sql = array();

		return $this->_init($sql, $langs);
	}

	/**
	 * Function called when module is enabled
	 * The *enable* function is never called if *always_enabled* is set to 1 in descriptor file (modDescriptor).
	 *
	 * @param  Translate $langs Lang object
	 * @return boolean          True
	 */
	public function remove($langs = null)
	{
		$sql = array();

		return $this->_remove($sql, $langs);
	}
}
