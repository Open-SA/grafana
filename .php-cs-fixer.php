<?php

/**
* -------------------------------------------------------------------------
* opentareasproyectos plugin for GLPI
* -------------------------------------------------------------------------
*
* LICENSE
*
* This file is part of opentareasproyectos.
*
* opentareasproyectos is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* any later version.
*
* opentareasproyectos is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with opentareasproyectos. If not, see <http://www.gnu.org/licenses/>.
* -------------------------------------------------------------------------
* @copyright Copyright (C) 2013-2023 by opentareasproyectos plugin team.
* @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
* @link      https://github.com/pluginsGLPI/opentareasproyectos
*-------------------------------------------------------------------------
*/

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->ignoreVCSIgnored(true)
    ->name('*.php');

$config = new Config();

$rules = [
    '@PER-CS' => true, // Latest PER rules.
];

return $config
    ->setRules($rules)
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer/.php-cs-fixer.cache')
;
