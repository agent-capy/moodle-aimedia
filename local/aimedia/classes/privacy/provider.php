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

namespace local_aimedia\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_aimedia.
 *
 * What this plugin keeps is closer to a person than most plugin data: a transcript
 * is the words somebody said, and a picture somebody uploaded may show them.
 * Neither the recording nor the picture is kept, only its content hash.
 *
 * The tables used to hold no user id, on the reasoning that they named nobody.
 * That was wrong. Core's action register points at these rows and carries the
 * user, so the rows were reachable from a person while being invisible to every
 * privacy request. They now hold the user and the context themselves, which also
 * means export and deletion do not depend on core's register still being there
 * when this provider runs.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aimedia_transcript',
            [
                'userid' => 'privacy:metadata:userid',
                'filename' => 'privacy:metadata:transcript:filename',
                'transcript' => 'privacy:metadata:transcript:transcript',
                'timecreated' => 'privacy:metadata:transcript:timecreated',
            ],
            'privacy:metadata:transcript',
        );
        $collection->add_database_table(
            'local_aimedia_describe',
            [
                'userid' => 'privacy:metadata:userid',
                'prompt' => 'privacy:metadata:describe:prompt',
                'filename' => 'privacy:metadata:describe:filename',
                'generatedcontent' => 'privacy:metadata:describe:generatedcontent',
                'timecreated' => 'privacy:metadata:describe:timecreated',
            ],
            'privacy:metadata:describe',
        );

        return $collection;
    }

    /** @var array<string, string> The tables this plugin writes, and the string describing each. */
    protected const TABLES = [
        'local_aimedia_transcript' => 'privacy:metadata:transcript',
        'local_aimedia_describe' => 'privacy:metadata:describe',
    ];

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (array_keys(self::TABLES) as $i => $table) {
            $contextlist->add_from_sql(
                "SELECT contextid FROM {" . $table . "} WHERE userid = :userid{$i}",
                ["userid{$i}" => $userid],
            );
        }

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        foreach (array_keys(self::TABLES) as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {" . $table . "} WHERE contextid = :contextid",
                ['contextid' => $context->id],
            );
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            foreach (self::TABLES as $table => $stringid) {
                $records = $DB->get_records($table, ['userid' => $userid, 'contextid' => $context->id], 'timecreated');
                if (!$records) {
                    continue;
                }
                $rows = [];
                foreach ($records as $record) {
                    unset($record->id, $record->userid, $record->contextid);
                    $record->timecreated = transform::datetime($record->timecreated);
                    $rows[] = $record;
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_aimedia'), get_string($stringid, 'local_aimedia')],
                    (object) ['requests' => $rows],
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        foreach (array_keys(self::TABLES) as $table) {
            $DB->delete_records($table, ['contextid' => $context->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['contextid'] = $userlist->get_context()->id;
        foreach (array_keys(self::TABLES) as $table) {
            $DB->delete_records_select($table, "contextid = :contextid AND userid {$insql}", $params);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            foreach (array_keys(self::TABLES) as $table) {
                $DB->delete_records($table, ['userid' => $userid, 'contextid' => $context->id]);
            }
        }
    }
}
