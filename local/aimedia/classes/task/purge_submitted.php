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

namespace local_aimedia\task;

/**
 * Removes recordings and pictures that were sent to an AI and never cleaned up.
 *
 * The page deletes what it uploaded as soon as it is done with it, and does so in a
 * finally so that a failure on the way through is not a reason to keep somebody's
 * voice on the server. A finally still needs the process to reach it: a fatal error,
 * a timeout, or a browser that gave up mid-request all leave the copy behind, and
 * this area is the plugin's own, so nothing else in Moodle ever comes for it.
 *
 * Nothing here is a user's draft. A draft belongs to the person who made it and
 * Moodle removes drafts on its own schedule; what this removes is the copy the page
 * made in order to hand the file to an AI, which has no owner and no purpose once
 * the request it belonged to has ended.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_submitted extends \core\task\scheduled_task {
    /** @var string The file area the page copies into. */
    public const AREA = 'submitted';

    /**
     * @var int How long a copy may sit there before it is certainly abandoned.
     *
     * Far longer than any request can run: PHP gives up long before this, and a
     * generous margin is cheap. Being too eager is the one mistake worth avoiding,
     * because the file it would take is one somebody is still waiting on.
     */
    public const MAX_AGE = 6 * HOURSECS;

    #[\Override]
    public function get_name(): string {
        return get_string('task:purgesubmitted', 'local_aimedia');
    }

    #[\Override]
    public function execute(): void {
        global $DB;

        $storage = get_file_storage();
        $areas = $DB->get_records_sql(
            "SELECT contextid, itemid, MAX(timecreated) AS newest
               FROM {files}
              WHERE component = :component AND filearea = :filearea
           GROUP BY contextid, itemid
             HAVING MAX(timecreated) < :cutoff",
            [
                'component' => 'local_aimedia',
                'filearea' => self::AREA,
                'cutoff' => time() - self::MAX_AGE,
            ],
        );

        // By area rather than by file, so that the directory record goes with the
        // file it belonged to, and so that a request that left several behind is
        // cleaned up as the one thing it was.
        $removed = 0;
        foreach ($areas as $area) {
            $storage->delete_area_files(
                (int) $area->contextid,
                'local_aimedia',
                self::AREA,
                (int) $area->itemid,
            );
            $removed++;
        }

        if ($removed > 0) {
            mtrace("local_aimedia: removed {$removed} abandoned upload(s).");
        }
    }
}
