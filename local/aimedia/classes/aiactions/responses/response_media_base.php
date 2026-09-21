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

namespace local_aimedia\aiactions\responses;

use core_ai\aiactions\responses\response_base;

/**
 * A response that can report a failure on every Moodle this plugin supports.
 *
 * Moodle 5.1 changed what a failed response has to carry. Up to 5.0 the parent wants
 * an error message and knows nothing of an error name; from 5.1 it wants a short
 * error name and lets the message be empty. Core keeps its own response classes in
 * step with the release they ship in, but a plugin that declares its own action ships
 * one file that has to satisfy every supported release at once, and core calls it by
 * name: the processor passes error: on 5.1 and would not on 5.0.
 *
 * So the constructor takes both, passes the parent only what that parent accepts, and
 * fills the field it insists on from the other one when a caller supplied just one.
 * What the parent accepts is read from the parent itself rather than worked out from
 * the version, because the change has been backported into 5.0 point releases.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class response_media_base extends response_base {
    /** @var bool|null Whether the parent of this release carries an error name. */
    private static ?bool $parenttakeserror = null;

    /**
     * Constructor.
     *
     * @param bool $success The success status of the action.
     * @param string $actionname The name of the action that was processed.
     * @param int $errorcode Error code. Must exist if success is false.
     * @param string $error Short error name. Required by Moodle 5.1 and later.
     * @param string $errormessage Error message. Required by Moodle 5.0.
     */
    public function __construct(
        bool $success,
        string $actionname,
        int $errorcode = 0,
        string $error = '',
        string $errormessage = '',
    ) {
        $arguments = [
            'success' => $success,
            'actionname' => $actionname,
            'errorcode' => $errorcode,
        ];

        if (self::parent_takes_error_name()) {
            // The name is the one this parent refuses to do without. A caller that
            // only had a message would be turned away, so the message stands in.
            $arguments['error'] = $error !== '' ? $error : $errormessage;
            $arguments['errormessage'] = $errormessage;
        } else {
            // This parent has no error name, and the message is what it insists on.
            $arguments['errormessage'] = $errormessage !== '' ? $errormessage : $error;
        }

        parent::__construct(...$arguments);
    }

    /**
     * Whether the parent constructor of this release carries a separate error name.
     *
     * @return bool True when the parent accepts an error name.
     */
    private static function parent_takes_error_name(): bool {
        if (self::$parenttakeserror !== null) {
            return self::$parenttakeserror;
        }

        self::$parenttakeserror = false;
        foreach ((new \ReflectionMethod(response_base::class, '__construct'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'error') {
                self::$parenttakeserror = true;
                break;
            }
        }

        return self::$parenttakeserror;
    }
}
