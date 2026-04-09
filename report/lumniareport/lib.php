<?php
/**
 * Library of functions for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the course navigation block.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $coursecontext The context of the course
 */
function report_lumniareport_extend_navigation_course($navigation, $course, $coursecontext) {
    if (has_capability('report/lumniareport:view', $coursecontext)) {
        $url = new moodle_url('/report/lumniareport/index.php', ['course' => $course->id]);
        $name = get_string('coursereport', 'report_lumniareport');
        $node = $navigation->add(
            $name,
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'report_lumniareport',
            new pix_icon('i/report', $name)
        );
        $node->showinflatnavigation = true;
    }
}
