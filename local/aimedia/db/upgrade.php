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

    if ($oldversion < 2026092007) {
        // Who asked, and where. These were left out on the grounds that the tables
        // named nobody, which was wrong: core's action register points at these rows
        // and carries the user, so the rows were personal data that no privacy
        // request could reach. Holding the user here as well makes export and
        // deletion self-contained, rather than depending on which privacy provider
        // runs first and whether core's register still exists when ours runs.
        $dbman = $DB->get_manager();
        foreach (['local_aimedia_transcript', 'local_aimedia_describe'] as $tablename) {
            $table = new xmldb_table($tablename);
            foreach (['userid', 'contextid'] as $fieldname) {
                $field = new xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
                $index = new xmldb_index($fieldname, XMLDB_INDEX_NOTUNIQUE, [$fieldname]);
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }

        // Fill in the rows written before this version from core's action register,
        // which is exactly the link that made these rows personal data while they
        // looked anonymous. Doing it this way keeps what people already have rather
        // than throwing it away to tidy up.
        $tables = [
            'transcript_audio' => 'local_aimedia_transcript',
            'describe_image' => 'local_aimedia_describe',
        ];
        [$insql, $params] = $DB->get_in_or_equal(array_keys($tables), SQL_PARAMS_NAMED);
        $registered = $DB->get_recordset_select(
            'ai_action_register',
            "actionname {$insql}",
            $params,
            '',
            'id, actionname, actionid, userid, contextid',
        );
        foreach ($registered as $row) {
            $table = $tables[$row->actionname];
            if (empty($row->actionid) || !$DB->record_exists($table, ['id' => $row->actionid])) {
                continue;
            }
            $DB->update_record($table, (object) [
                'id' => $row->actionid,
                'userid' => (int) $row->userid,
                'contextid' => (int) $row->contextid,
            ]);
        }
        $registered->close();

        // Whatever is left cannot be attributed to anybody, and a row whose owner is
        // unknown is one a person can neither see nor ask to have removed.
        $DB->delete_records_select('local_aimedia_transcript', 'userid = 0');
        $DB->delete_records_select('local_aimedia_describe', 'userid = 0');

        upgrade_plugin_savepoint(true, 2026092007, 'local', 'aimedia');
    }

    return true;
}
