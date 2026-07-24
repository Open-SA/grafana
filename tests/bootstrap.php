<?php

/**
* -------------------------------------------------------------------------
* openesqueleto plugin for GLPI
* -------------------------------------------------------------------------
*
* LICENSE
*
* This file is part of openesqueleto.
*
* openesqueleto is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* any later version.
*
* openesqueleto is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with openesqueleto. If not, see <http://www.gnu.org/licenses/>.
* -------------------------------------------------------------------------
* @copyright Copyright (C) 2013-2023 by openesqueleto plugin team.
* @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
* @link      https://github.com/pluginsGLPI/openesqueleto
*-------------------------------------------------------------------------
*/

global $CFG_GLPI, $PLUGIN_HOOKS;

define('GLPI_ROOT', __DIR__ . '/../../../');
define('GLPI_LOG_DIR', __DIR__ . '/files/_logs');

define('TU_USER', 'glpi');
define('TU_PASS', 'glpi');
define('GLPI_LOG_LVL', 'DEBUG');

require GLPI_ROOT . '/inc/includes.php';

if (!Plugin::isPluginActive("openesqueleto")) {
    throw new RuntimeException("Plugin openesqueleto is not active in the test database");
}
