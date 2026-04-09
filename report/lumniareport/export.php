<?php
/**
 * Export processor for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('course', PARAM_INT);
$format = optional_param('format_export', 'csv', PARAM_ALPHA);
$datainicio = optional_param('datainicio', '', PARAM_TEXT);
$datafim = optional_param('datafim', '', PARAM_TEXT);
$colunas = optional_param_array('colunas', [], PARAM_ALPHA); // Array de colunas extras (status, inicio, fim)

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('report/lumniareport:export', $context);

// Prepara SQL de dados e filtros de data (igual index.php).
$params = ['courseid' => $courseid];
$datefiltersql = "";

if (!empty($datainicio)) {
    $timestamp_inicio = strtotime($datainicio);
    if ($timestamp_inicio) {
        $datefiltersql .= " AND (cc.timestarted >= :datainicio OR cc.timecompleted >= :datainicio2) ";
        $params['datainicio'] = $timestamp_inicio;
        $params['datainicio2'] = $timestamp_inicio;
    }
}

if (!empty($datafim)) {
    $timestamp_fim = strtotime($datafim) + 86399;
    if ($timestamp_fim) {
        $datefiltersql .= " AND (cc.timestarted <= :datafim OR cc.timecompleted <= :datafim2) ";
        $params['datafim'] = $timestamp_fim;
        $params['datafim2'] = $timestamp_fim;
    }
}

$sql = "
    SELECT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        cc.timestarted,
        cc.timecompleted
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid
    LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
    WHERE e.courseid = :courseid
      AND u.deleted = 0
      AND u.suspended = 0
      $datefiltersql
";

$users = $DB->get_records_sql($sql, $params);

// Preparar os dados tubulares para exportar.
$export_data = [];

// Header Dinâmico
$header = ['Nome', 'Email'];
if (in_array('status', $colunas)) { $header[] = 'Status'; }
if (in_array('inicio', $colunas)) { $header[] = 'Data de Início'; }
if (in_array('fim', $colunas)) { $header[] = 'Data de Conclusão'; }

$export_data[] = $header;

// Validação Backend do limite PDF
if ($format === 'pdf' && count($colunas) > 2) {
    print_error('maxpdfcolumns', 'report_lumniareport');
}

foreach ($users as $u) {
    $status = get_string('notstarted', 'report_lumniareport');
    if (!empty($u->timecompleted)) {
        $status = get_string('certified', 'report_lumniareport');
    } elseif (!empty($u->timestarted)) {
        $status = get_string('inprogress', 'report_lumniareport');
    }

    $row = [fullname($u), $u->email];

    if (in_array('status', $colunas)) {
        $row[] = $status;
    }
    if (in_array('inicio', $colunas)) {
        $row[] = !empty($u->timestarted) ? userdate($u->timestarted) : '-';
    }
    if (in_array('fim', $colunas)) {
        $row[] = !empty($u->timecompleted) ? userdate($u->timecompleted) : '-';
    }

    $export_data[] = $row;
}

$filename = 'relatorio_' . $course->shortname . '_' . date('Ymd_His');

// Tratamento de tipos de arquivo (MVP apenas CSV via raw streaming)
if ($format === 'csv' || $format === 'xlsx' || $format === 'pdf') {
    // Para simplificar no MVP de CSV, faremos streaming do arquivo CSV para download
    // XLSX e PDF podem requerer as classes do Moodle (ex: \core\dataformat ou pdf/tcpdf)
    // mas vamos servir um CSV universalmente para propósitos deste commit.

    $filename .= '.csv';

    // Configura headers para baixar CSV.
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Adiciona BOM para UTF-8 conforme requisito.
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    foreach ($export_data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    die();
}
