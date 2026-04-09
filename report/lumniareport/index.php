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

    // Configurar o menu ativo para o Moodle 4.x (aba relatórios).
    $PAGE->set_secondary_active_tab('coursereports');

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

// Obter filtros da URL, se aplicáveis.
$filter_datainicio = optional_param('datainicio', '', PARAM_TEXT);
$filter_datafim = optional_param('datafim', '', PARAM_TEXT);

// Obter dados dinâmicos de conclusão para o dashboard, se em contexto de curso.
$notstarted = 0;
$inprogress = 0;
$certified = 0;

if ($courseid) {
    global $DB;

    // Preparar parâmetros e filtros da query SQL.
    $params = ['courseid' => $courseid];
    $datefiltersql = "";

    if (!empty($filter_datainicio)) {
        $timestamp_inicio = strtotime($filter_datainicio);
        if ($timestamp_inicio) {
            $datefiltersql .= " AND (cc.timestarted >= :datainicio OR cc.timecompleted >= :datainicio2) ";
            $params['datainicio'] = $timestamp_inicio;
            $params['datainicio2'] = $timestamp_inicio;
        }
    }

    if (!empty($filter_datafim)) {
        // Considera até o final do dia
        $timestamp_fim = strtotime($filter_datafim) + 86399;
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

    foreach ($users as $u) {
        if (!empty($u->timecompleted)) {
            $certified++;
        } elseif (!empty($u->timestarted)) {
            $inprogress++;
        } else {
            $notstarted++;
        }
    }
}

// Início do dashboard.
echo html_writer::start_div('dashboard-container row');

// Not Started
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-secondary mb-3');
echo html_writer::div(get_string('notstarted', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $notstarted, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// In Progress
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-primary mb-3');
echo html_writer::div(get_string('inprogress', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $inprogress, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Certified
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-success mb-3');
echo html_writer::div(get_string('certified', 'report_lumniareport'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $certified, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // Fim do dashboard.

if ($courseid) {
    // Adicionar seção de Opções de Exportação e Tabela de Dados.
    echo html_writer::start_div('mt-4');
    echo html_writer::tag('h3', get_string('dashboardandexport', 'report_lumniareport'));

    // Painel de Filtros e Exportação (HTML Nativo)
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', 'Filtros e Exportação', ['class' => 'card-title']);

    $form_action = new moodle_url('/report/lumniareport/export.php');
    echo '<form method="GET" action="' . $form_action . '" id="form-export-lumniareport">';
    echo '<input type="hidden" name="course" value="' . $courseid . '">';

    echo '<div class="row mb-3">';
    echo '  <div class="col-md-3">';
    echo '    <label for="datainicio" class="form-label">Data Inicial</label>';
    echo '    <input type="date" id="datainicio" name="datainicio" class="form-control" value="' . s($filter_datainicio) . '">';
    echo '  </div>';
    echo '  <div class="col-md-3">';
    echo '    <label for="datafim" class="form-label">Data Final</label>';
    echo '    <input type="date" id="datafim" name="datafim" class="form-control" value="' . s($filter_datafim) . '">';
    echo '  </div>';
    echo '  <div class="col-md-3">';
    echo '    <label for="format_export" class="form-label">Formato</label>';
    echo '    <select id="format_export" name="format_export" class="form-select">';
    echo '      <option value="csv">CSV</option>';
    echo '      <option value="xlsx">XLSX</option>';
    echo '      <option value="pdf">PDF</option>';
    echo '    </select>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="mb-3">';
    echo '  <p class="mb-1 fw-bold">Colunas Adicionais (além do Nome e Email)</p>';
    echo '  <div class="form-check form-switch form-check-inline">';
    echo '    <input class="form-check-input coluna-toggle" type="checkbox" id="col_status" name="colunas[]" value="status" checked>';
    echo '    <label class="form-check-label" for="col_status">Status</label>';
    echo '  </div>';
    echo '  <div class="form-check form-switch form-check-inline">';
    echo '    <input class="form-check-input coluna-toggle" type="checkbox" id="col_inicio" name="colunas[]" value="inicio">';
    echo '    <label class="form-check-label" for="col_inicio">Data de Início</label>';
    echo '  </div>';
    echo '  <div class="form-check form-switch form-check-inline">';
    echo '    <input class="form-check-input coluna-toggle" type="checkbox" id="col_fim" name="colunas[]" value="fim">';
    echo '    <label class="form-check-label" for="col_fim">Data de Conclusão</label>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="alert alert-warning d-none" id="pdf-limit-warning" role="alert">';
    echo get_string('maxpdfcolumns', 'report_lumniareport');
    echo '</div>';

    echo '<div class="d-flex gap-2">';
    echo '  <button type="submit" class="btn btn-primary">Exportar Relatório</button>';
    // Adicionar um botão auxiliar apenas para recarregar a tela com os filtros.
    $current_url = new moodle_url('/report/lumniareport/index.php');
    echo '  <button type="submit" formaction="' . $current_url . '" class="btn btn-outline-secondary">Aplicar Filtro em Tela</button>';
    echo '</div>';

    echo '</form>';
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Tabela de Dados Simplificada
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Nome');
    echo html_writer::tag('th', 'Email');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    foreach ($users as $u) {
        $status = get_string('notstarted', 'report_lumniareport');
        if (!empty($u->timecompleted)) {
            $status = get_string('certified', 'report_lumniareport');
        } elseif (!empty($u->timestarted)) {
            $status = get_string('inprogress', 'report_lumniareport');
        }

        $fullname = fullname($u);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $fullname);
        echo html_writer::tag('td', $u->email);
        echo html_writer::tag('td', $status);
        echo html_writer::end_tag('tr');
    }

    if (empty($users)) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', 'Nenhum dado encontrado para o curso.', ['colspan' => '3', 'class' => 'text-center']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    echo html_writer::end_div(); // Fim da seção de dados.
}

echo html_writer::start_tag('script');
?>
document.addEventListener('DOMContentLoaded', function() {
    const formatSelect = document.getElementById('format_export');
    const toggles = document.querySelectorAll('.coluna-toggle');
    const warningDiv = document.getElementById('pdf-limit-warning');

    function checkPdfLimit() {
        if (formatSelect.value === 'pdf') {
            let checkedCount = 0;
            toggles.forEach(t => { if (t.checked) checkedCount++; });

            if (checkedCount >= 2) {
                warningDiv.classList.remove('d-none');
                toggles.forEach(t => {
                    if (!t.checked) {
                        t.disabled = true;
                    }
                });
            } else {
                warningDiv.classList.add('d-none');
                toggles.forEach(t => { t.disabled = false; });
            }
        } else {
            warningDiv.classList.add('d-none');
            toggles.forEach(t => { t.disabled = false; });
        }
    }

    if (formatSelect) {
        formatSelect.addEventListener('change', checkPdfLimit);
    }

    toggles.forEach(t => {
        t.addEventListener('change', checkPdfLimit);
    });

    // Run on load
    if (formatSelect) {
        checkPdfLimit();
    }
});
<?php
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
