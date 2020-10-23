<?php

/**
 * @defgroup plugins_importexport_native
 */
 
/**
 * @file plugins/importexport/copernicus/index.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_native
 * 
 * @brief Wrapper for native XML import/export plugin.
 * @brief 20201023,Eko Didik Widianto: Rewrite code from DOAJ import/export plugin
 * @brief didik@live.undip.ac.id
 *
 */

require_once('CopernicusPlugin.inc.php');

return new CopernicusPlugin();

?>
