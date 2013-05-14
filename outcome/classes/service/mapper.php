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
 * Mapper Service
 *
 * @package   core_outcome
 * @category  outcome
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__).'/model/area_repository.php');
require_once(dirname(__DIR__).'/model/filter_repository.php');
require_once(dirname(__DIR__).'/model/outcome_repository.php');
require_once(dirname(__DIR__).'/model/outcome_set_repository.php');

/**
 * Mapper Service
 *
 * This class assists with common use cases
 * when mapping outcomes and outcome sets to
 * content.
 *
 * @package   core_outcome
 * @category  outcome
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Mark Nielsen
 */
class outcome_service_mapper {
    /**
     * @var outcome_model_outcome_repository
     */
    protected $outcomes;

    /**
     * @var outcome_model_outcome_set_repository
     */
    protected $outcomesets;

    /**
     * @var outcome_model_filter_repository
     */
    protected $filters;

    /**
     * @var outcome_model_area_repository
     */
    protected $areas;

    /**
     * @param outcome_model_outcome_repository $outcomes
     * @param outcome_model_outcome_set_repository $outcomesets
     * @param outcome_model_filter_repository $filters
     * @param outcome_model_area_repository $areas
     */
    public function __construct(outcome_model_outcome_repository $outcomes = null,
                                 outcome_model_outcome_set_repository $outcomesets = null,
                                 outcome_model_filter_repository $filters = null,
                                 outcome_model_area_repository $areas = null) {

        if (is_null($outcomes)) {
            $outcomes = new outcome_model_outcome_repository();
        }
        if (is_null($outcomesets)) {
            $outcomesets = new outcome_model_outcome_set_repository();
        }
        if (is_null($filters)) {
            $filters = new outcome_model_filter_repository();
        }
        if (is_null($areas)) {
            $areas = new outcome_model_area_repository();
        }
        $this->outcomes = $outcomes;
        $this->outcomesets = $outcomesets;
        $this->filters = $filters;
        $this->areas = $areas;
    }

    /**
     * Finds outcomes for a given area but are also filtered
     * by outcome set filters against the course.
     *
     * Also, only outcomes that are not deleted and are assessable
     * are return as well.
     *
     * @param outcome_model_area $model
     * @param null|int $courseid Defaults to $COURSE global if not passed
     * @return array
     */
    protected function get_filtered_area_outcomes(outcome_model_area $model, $courseid = null) {
        global $COURSE;

        if (is_null($courseid)) {
            $courseid = $COURSE->id;
        }
        $filters  = $this->filters->find_by_course($courseid);
        $areasets = $this->outcomesets->find_by_area($model);

        $outcomes    = array();
        $outcomesets = array();
        foreach ($areasets as $outcomeset) {
            foreach ($filters as $filter) {
                if ($filter->outcomesetid == $outcomeset->id) {
                    $outcomes += $this->outcomes->find_by_area_and_filter($model, $filter);
                    $outcomesets[] = $outcomeset;
                }
            }
        }
        return array($outcomesets, $outcomes);
    }

    /**
     * Returns data suitable for setting to MoodleQuickForm_map_outcome_set
     *
     * @param int $courseid
     * @return string
     */
    public function get_outcome_set_mappings($courseid) {

        $filterlist  = array();
        $outcomesets = $this->outcomesets->find_used_by_course($courseid);
        $filters     = $this->filters->find_by(array('courseid' => $courseid));

        foreach ($filters as $filter) {
            if (!array_key_exists($filter->outcomesetid, $outcomesets)) {
                continue;
            }
            $outcomeset = $outcomesets[$filter->outcomesetid];
            foreach ($filter->filter as $data) {
                $filterlist[] = array(
                    'outcomesetid' => $outcomeset->id,
                    'name'         => format_string($outcomeset->name),
                    'edulevels'    => (isset($data['edulevels']) ? format_string($data['edulevels']) : null),
                    'subjects'     => (isset($data['subjects']) ? format_string($data['subjects']) : null),
                );
            }
        }
        if (!empty($filterlist)) {
            return json_encode($filterlist);
        }
        return '';
    }

    /**
     * Saves the form data from the MoodleQuickForm_map_outcome_set element
     *
     * @param int $courseid
     * @param outcome_model_filter[] $filters Data from MoodleQuickForm_map_outcome_set
     * @throws coding_exception
     */
    public function save_outcome_set_mappings($courseid, array $filters) {

        foreach ($filters as $filter) {
            if (!$filter instanceof outcome_model_filter) {
                throw new coding_exception('The $filters parameter should be an array of outcome_model_filter instances');
            }
            $filter->courseid = $courseid;
        }
        $this->filters->sync($courseid, $filters);
    }

    /**
     * Returns data suitable for setting to MoodleQuickForm_map_outcome
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param null|int $courseid Defaults to $COURSE global if not passed
     * @return string
     */
    public function get_outcome_mappings_for_form($component, $area, $itemid, $courseid = null) {
        $model = $this->areas->find_one($component, $area, $itemid);

        if ($model instanceof outcome_model_area) {
            list($outcomesets, $outcomes) = $this->get_filtered_area_outcomes($model, $courseid);

            return json_encode(array(
                'outcomesets' => array_values($outcomesets),
                'outcomes'    => array_values($outcomes),
            ));
        }
        return '';
    }

    /**
     * Get outcome mappings for an area.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return outcome_model_outcome[]
     */
    public function get_outcome_mappings($component, $area, $itemid) {
        $results = $this->outcomes->find_by_area_itemids($component, $area, array($itemid));

        if (array_key_exists($itemid, $results)) {
            return $results[$itemid];
        }
        return array();
    }

    /**
     * Get outcome mappings for several items in a given component & area.
     *
     * @param string $component
     * @param string $area
     * @param array $itemids
     * @return array An array indexed by item IDs.  Each element in the
     * array is an array of outcomes.  Not all passed item IDs may
     * exist in the returned array (AKA not mapped to any outcomes)
     */
    public function get_many_outcome_mappings($component, $area, array $itemids) {
        return $this->outcomes->find_by_area_itemids($component, $area, $itemids);
    }

    /**
     * Save single outcome mapping to an area.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int|null $outcomeid The outcome ID to map or null if unmapping or nothing to save
     * @return outcome_model_area|boolean Returns false when there is nothing to save
     */
    public function save_outcome_mapping($component, $area, $itemid, $outcomeid) {
        $model = $this->areas->find_one($component, $area, $itemid);

        if (!$model instanceof outcome_model_area) {
            if (empty($outcomeid)) {
                return false; // Nothing to do!
            }
            $model            = new outcome_model_area();
            $model->component = $component;
            $model->area      = $area;
            $model->itemid    = $itemid;

            $this->areas->save($model);
        }
        if (!empty($outcomeid)) {
            $outcomes = $this->outcomes->find_by_area($model, false);

            if (array_key_exists($outcomeid, $outcomes)) {
                // It's already mapped.
                $outcome = $outcomes[$outcomeid];
                unset($outcomes[$outcomeid]);
            } else {
                $outcome = $this->outcomes->find($outcomeid, MUST_EXIST);
            }
            $this->areas->remove_area_outcomes($model, $outcomes);
            $this->areas->save_area_outcomes($model, array($outcome));
            return $model;
        } else {
            // No outcomes, so stop tracking this area.
            $this->areas->remove($model);
            return false;
        }
    }

    /**
     * Save outcome mappings to an area.
     *
     * Can be used to save data from the MoodleQuickForm_map_outcome element
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param array $outcomes An array of outcome IDs
     * @param null|int $courseid Defaults to $COURSE global if not passed
     * @return outcome_model_area|boolean Returns false when there is nothing to save
     */
    public function save_outcome_mappings($component, $area, $itemid, array $outcomes, $courseid = null) {

        $model = $this->areas->find_one($component, $area, $itemid);

        if (!$model instanceof outcome_model_area) {
            if (empty($outcomes)) {
                return false; // Nothing to do!
            }
            $model            = new outcome_model_area();
            $model->component = $component;
            $model->area      = $area;
            $model->itemid    = $itemid;

            $this->areas->save($model);
        }
        $outcomes = $this->outcomes->find_by_ids($outcomes);
        $result   = $this->get_filtered_area_outcomes($model, $courseid);
        $choices  = $result[1];

        foreach ($outcomes as $outcome) {
            unset($choices[$outcome->id]);
        }
        $this->areas->remove_area_outcomes($model, $choices);
        $this->areas->save_area_outcomes($model, $outcomes);

        // After saving, check to see if any outcomes remain.
        $areaoutcomes = $this->outcomes->find_by_area($model, false);
        if (empty($areaoutcomes)) {
            // No outcomes, so stop tracking this area.
            $this->areas->remove($model);
            return false;
        } else {
            return $model;
        }
    }

    /**
     * Remove an outcome area and ALL data associated with it.
     *
     * @param outcome_model_area $model
     */
    public function remove_area(outcome_model_area $model) {
        $this->areas->remove($model);
    }

    /**
     * This will delete ALL outcome sets mapped to a course
     *
     * @param $courseid
     */
    public function remove_used_outcome_sets($courseid) {
        $this->filters->remove_by_course($courseid);
    }
}
