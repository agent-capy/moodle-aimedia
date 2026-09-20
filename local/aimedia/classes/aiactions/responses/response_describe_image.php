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
 * What asking about a picture came back with.
 *
 * The same shape a text generation returns, deliberately: a vision model answers
 * in text and counts tokens the same way, so everything downstream that costs a
 * request or checks it produced something works without knowing this action exists.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_describe_image extends response_base {
    /** @var string|null The answer. */
    private ?string $generatedcontent = null;

    /** @var string|null Why the model stopped. */
    private ?string $finishreason = null;

    /** @var int|null Tokens the question and the picture came to. */
    private ?int $prompttokens = null;

    /** @var int|null Tokens the answer came to. */
    private ?int $completiontokens = null;

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
            actionname: 'describe_image',
            errorcode: $errorcode,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->generatedcontent = $response['generatedcontent'] ?? null;
        $this->finishreason = $response['finishreason'] ?? null;
        $this->prompttokens = isset($response['prompttokens']) ? (int) $response['prompttokens'] : null;
        $this->completiontokens = isset($response['completiontokens']) ? (int) $response['completiontokens'] : null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'generatedcontent' => $this->generatedcontent,
            'finishreason' => $this->finishreason,
            'prompttokens' => $this->prompttokens,
            'completiontokens' => $this->completiontokens,
            'model' => $this->model,
        ];
    }
}
