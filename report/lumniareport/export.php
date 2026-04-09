<?php
/**
 * Export processor for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php'); // Carrega TCPDF (pdf class) do Moodle
require_once(__DIR__ . '/classes/form/export_form.php');

$courseid = required_param('course', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('report/lumniareport:export', $context);

// Processa o form
$form_action = new moodle_url('/report/lumniareport/export.php');
$mform = new \report_lumniareport\form\export_form($form_action, null, 'post');

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/report/lumniareport/index.php', ['course' => $courseid]));
} else if ($data = $mform->get_data()) {
    $datainicio = isset($data->datainicio) ? $data->datainicio : 0;
    $datafim = isset($data->datafim) ? $data->datafim : 0;

    // Interceptar ação "Aplicar Filtros" para atualizar a tela em vez de exportar
    if (!empty($data->applyfilters)) {
        $urlparams = ['course' => $courseid];
        if (!empty($datainicio)) {
            $urlparams['datainicio'] = date('Y-m-d', $datainicio);
        }
        if (!empty($datafim)) {
            $urlparams['datafim'] = date('Y-m-d', $datafim);
        }
        if (!empty($data->col_status)) { $urlparams['col_status'] = 1; }
        if (!empty($data->col_inicio)) { $urlparams['col_inicio'] = 1; }
        if (!empty($data->col_fim)) { $urlparams['col_fim'] = 1; }

        redirect(new moodle_url('/report/lumniareport/index.php', $urlparams));
    }

    $format = isset($data->format_export) ? $data->format_export : 'csv';

    // Toggles colunas
    $colunas = [];
    if (!empty($data->col_status)) { $colunas[] = 'status'; }
    if (!empty($data->col_inicio)) { $colunas[] = 'inicio'; }
    if (!empty($data->col_fim)) { $colunas[] = 'fim'; }
} else {
    redirect(new moodle_url('/report/lumniareport/index.php', ['course' => $courseid]));
}

// Prepara SQL de dados e filtros de data.
$params = ['courseid' => $courseid];
$datefiltersql = "";

if (!empty($datainicio)) {
    $timestamp_inicio = $datainicio;
    if ($timestamp_inicio) {
        $datefiltersql .= " AND (cc.timestarted >= :datainicio OR cc.timecompleted >= :datainicio2) ";
        $params['datainicio'] = $timestamp_inicio;
        $params['datainicio2'] = $timestamp_inicio;
    }
}

if (!empty($datafim)) {
    $timestamp_fim = $datafim + 86399; // Final do dia
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
$header = [
    get_string('colname', 'report_lumniareport'),
    get_string('colemail', 'report_lumniareport')
];
if (in_array('status', $colunas)) { $header[] = get_string('colstatus', 'report_lumniareport'); }
if (in_array('inicio', $colunas)) { $header[] = get_string('colstart', 'report_lumniareport'); }
if (in_array('fim', $colunas)) { $header[] = get_string('colend', 'report_lumniareport'); }

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

// Tratamento de tipos de arquivo (Production Ready)
if ($format === 'xlsx') {
    \core\dataformat::download_data($filename, 'excel', $header, $export_data);
    die();

} elseif ($format === 'pdf') {
    // Gerar o PDF usando a classe nativa TCPDF (pdf) do Moodle.
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Tratamento da imagem de fundo
    $draftitemid = file_get_submitted_draft_itemid('pdfbackground_filemanager');
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);

    if (!empty($files)) {
        $file = reset($files);
        // Usa temporary file handling for PDF image processing
        $tmpfile = make_request_directory() . '/' . $file->get_filename();
        $file->copy_content_to($tmpfile);

        // Aplica a imagem como background cobrindo a página A4 (210x297mm)
        $pdf->Image($tmpfile, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
    }

    // Configurar Fonte PDF
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, get_string('dashboardandexport', 'report_lumniareport'), 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', 'B', 12);

    // Tabela: Cabeçalho
    // Dividir a largura da página (ex: 190mm) com base nas colunas (2 fixas + extras).
    $colwidth = 190 / count($header);
    foreach ($header as $col_title) {
        $pdf->Cell($colwidth, 7, $col_title, 1, 0, 'C');
    }
    $pdf->Ln();

    // Tabela: Linhas
    $pdf->SetFont('helvetica', '', 10);
    foreach ($export_data as $index => $row) {
        // Pular o cabeçalho que foi pro export_data (posição 0)
        if ($index === 0) continue;

        foreach ($row as $cell_data) {
            // Usar MultiCell para lidar com nomes longos/quebra de linha conforme especificado
            // Simulando a mesma altura na linha inteira para MVP de PDF
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell($colwidth, 10, $cell_data, 1, 'L', false, 0);
        }
        $pdf->Ln();
    }

    $pdf->Output($filename . '.pdf', 'D');
    die();

} else {
    // CSV usando dataformat do Core
    \core\dataformat::download_data($filename, 'csv', $header, $export_data);
    die();
}
