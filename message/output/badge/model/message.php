<?php
/**
 * Alert Badge
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
 *
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @package message_badge
 * @author Mark Nielsen
 */

/**
 * Message Model
 *
 * @author Mark Nielsen
 * @package message_badge
 */
class message_output_badge_model_message implements renderable {
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $useridfrom;

    /**
     * @var int
     */
    public $useridto;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string
     */
    public $fullmessage;

    /**
     * @var int
     */
    public $fullmessageformat;

    /**
     * @var string
     */
    public $fullmessagehtml;

    /**
     * @var string
     */
    public $smallmessage;

    /**
     * @var int
     */
    public $notification;

    /**
     * @var string
     */
    public $contexturl;

    /**
     * @var string
     */
    public $contexturlname;

    /**
     * @var int
     */
    public $timecreated;

    /**
     * The user that the message is from (usually partial object)
     *
     * @var stdClass
     */
    protected $fromuser;

    public function __construct($options = array()) {
        $this->set_options($options);
    }

    /**
     * @param stdClass $user
     * @return message_output_badge_model_message
     */
    public function set_fromuser(stdClass $user) {
        if ($user->id != $this->useridfrom) {
            throw new coding_exception("The passed user->id ($user->id) != message->useridfrom ($this->useridfrom)");
        }
        $this->fromuser = $user;
        return $this;
    }

    /**
     * Will go to the DB and grab the user if not already set
     *
     * @throws coding_exception
     * @return stdClass
     */
    public function get_fromuser() {
        global $DB;

        if (is_null($this->fromuser)) {
            if (empty($this->useridfrom)) {
                throw new coding_exception('The message useridfrom is not set');
            }
            $this->set_fromuser(
                $DB->get_record('user', array('id' => $this->useridfrom), user_picture::fields(), MUST_EXIST)
            );
        }
        return $this->fromuser;
    }

    /**
     * A way to bulk set model properties
     *
     * @param array|object $options
     * @return message_output_badge_model_message
     */
    public function set_options($options) {
        foreach ($options as $name => $value) {
            // Ignore things that are not a property of this model
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
        return $this;
    }
}