<?php
/**
 * Dashboard page for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = optional_param('course', 0, PARAM_INT);

if ($courseid) {
    // Acesso dentro do curso.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('report/lumniareport:view', $context);

    $PAGE->set_url('/report/lumniareport/index.php', ['course' => $courseid]);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_title($course->shortname . ': ' . get_string('dashboardandexport', 'report_lumniareport'));
    $PAGE->set_heading($course->fullname);

} else {
    // Acesso como administrador (nível de sistema) - fora de um curso específico.
    require_login();
    $context = context_system::instance();
    require_capability('report/lumniareport:view', $context);
    admin_externalpage_setup('reportlumniareport');

    $PAGE->set_url('/report/lumniareport/index.php');
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('pluginname', 'report_lumniareport'));
    $PAGE->set_heading(get_string('pluginname', 'report_lumniareport'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboardandexport', 'report_lumniareport'));

// Placeholder para o dashboard.
echo html_writer::start_div('dashboard-container row');

// Not Started
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-secondary mb-3');
echo html_writer::div(get_string('notstarted', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', '0', ['class' => 'card-title']); // Placeholder
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// In Progress
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-primary mb-3');
echo html_writer::div(get_string('inprogress', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', '0', ['class' => 'card-title']); // Placeholder
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Certified
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-success mb-3');
echo html_writer::div(get_string('certified', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', '0', ['class' => 'card-title']); // Placeholder
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
