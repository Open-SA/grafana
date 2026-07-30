<?php

/**
 * -------------------------------------------------------------------------
 * Grafana plugin for GLPI
 * -------------------------------------------------------------------------
 * Returns the second-level dropdown HTML for the permissions add form.
 * Called via jQuery .load() when the actor type selector changes.
 *
 * POST params:
 *   type  string  One of: Profile, User, Group, Entity
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$type = $_POST['type'] ?? '';

switch ($type) {
    case 'Profile':
        Profile::dropdown(['name' => 'actor_id', 'display_emptychoice' => true]);
        break;
    case 'User':
        User::dropdown(['name' => 'actor_id', 'right' => 'all', 'display_emptychoice' => true]);
        break;
    case 'Group':
        Group::dropdown(['name' => 'actor_id', 'display_emptychoice' => true]);
        break;
    case 'Entity':
        Entity::dropdown(['name' => 'actor_id', 'display_emptychoice' => true]);
        break;
}
