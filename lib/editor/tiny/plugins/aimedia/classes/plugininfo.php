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

namespace tiny_aimedia;

use context;
use editor_tiny\editor;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_configuration;
use editor_tiny\plugin_with_menuitems;

/**
 * Describe a picture or transcribe a recording, from inside the editor.
 *
 * @package    tiny_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugininfo extends plugin implements
    plugin_with_buttons,
    plugin_with_configuration,
    plugin_with_menuitems {
    #[\Override]
    public static function is_enabled(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null,
    ): bool {
        // Both buttons work on a file the person has just put in this editor,
        // so an editor that cannot hold files has nothing for them to work on.
        // This is the same test Moodle's own recorder makes.
        if (empty($options['maxfiles'])) {
            return false;
        }

        return has_capability('local/aimedia:use', \context_system::instance());
    }

    #[\Override]
    public static function get_available_buttons(): array {
        return [
            'tiny_aimedia/tiny_aimedia_describe',
            'tiny_aimedia/tiny_aimedia_transcribe',
        ];
    }

    #[\Override]
    public static function get_available_menuitems(): array {
        return [
            'tiny_aimedia/tiny_aimedia_describe',
            'tiny_aimedia/tiny_aimedia_transcribe',
        ];
    }

    #[\Override]
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null,
    ): array {
        return ['contextid' => $context->id];
    }
}
