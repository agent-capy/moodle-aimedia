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

namespace local_aiaudio\aiactions\responses;

use core_ai\aiactions\responses\response_base;

/**
 * What a transcription came back with.
 *
 * @package    local_aiaudio
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_transcript_audio extends response_base {
    /** @var string|null The text the model heard. */
    private ?string $transcript = null;

    /** @var int|null How long the recording was, in milliseconds, where the provider said. */
    private ?int $durationms = null;

    /**
     * Constructor.
     *
     * @param bool $success The success status of the action.
     * @param int $errorcode Error code. Must exist if success is false.
     * @param string $errormessage Error message. Must exist if success is false.
     */
    public function __construct(
        bool $success,
        int $errorcode = 0,
        string $errormessage = '',
    ) {
        parent::__construct(
            success: $success,
            actionname: 'transcript_audio',
            errorcode: $errorcode,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->transcript = $response['transcript'] ?? null;
        $this->durationms = isset($response['durationms']) ? (int) $response['durationms'] : null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'transcript' => $this->transcript,
            'durationms' => $this->durationms,
            'model' => $this->model,
        ];
    }
}
