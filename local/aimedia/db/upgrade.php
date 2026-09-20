<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_aimedia.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade this plugin.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool Always true.
 */
function xmldb_local_aimedia_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026092006) {
        // The roles that write course content are allowed this from now on.
        // Moodle applies the defaults in db/access.php only when a capability
        // is first installed, so a site that already has this one would never
        // see them, and every administrator would have to find the tick.
        $context = context_system::instance();
        foreach (['manager', 'editingteacher', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $set = $DB->record_exists('role_capabilities', [
                    'roleid' => $role->id,
                    'capability' => 'local/aimedia:use',
                ]);
                if ($set) {
                    // Somebody has already said what this role may do, and a
                    // default is not a reason to overrule them.
                    continue;
                }
                assign_capability('local/aimedia:use', CAP_ALLOW, $role->id, $context->id);
            }
        }

        upgrade_plugin_savepoint(true, 2026092006, 'local', 'aimedia');
    }

    return true;
}
