<?php

/**
 * -------------------------------------------------------------------------
 * Grafana plugin for GLPI
 * -------------------------------------------------------------------------
 * Redirects the "Configure" button in Setup → Plugins directly to the
 * Grafana tab in Setup → General configuration.
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::redirect(
    Toolbox::getItemTypeFormURL('Config') . '?forcetab=' . urlencode('GlpiPlugin\Grafana\Config$1')
);
