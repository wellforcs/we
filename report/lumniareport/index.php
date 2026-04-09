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
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/form/export_form.php');

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
    $PAGE->set_title($course->shortname . ': ' . report_lumniareport_get_string('dashboardandexport', 'Dashboard e Exportação'));
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
    $PAGE->set_title(report_lumniareport_get_string('pluginname', 'Lumniareport'));
    $PAGE->set_heading(report_lumniareport_get_string('pluginname', 'Lumniareport'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(report_lumniareport_get_string('dashboardandexport', 'Dashboard e Exportação'));

// Obter filtros da URL, se aplicáveis.
$filter_datainicio = optional_param('datainicio', '', PARAM_TEXT);
$filter_datafim = optional_param('datafim', '', PARAM_TEXT);

// Obter dinamicamente as colunas do formulário
$available_cols = report_lumniareport_get_available_columns();
$filter_cols = [];
foreach ($available_cols as $colkey => $col) {
    // Por padrão o status vem ativado
    $default = ($colkey === 'status') ? 1 : 0;
    $filter_cols[$colkey] = optional_param('col_' . $colkey, $default, PARAM_INT);
}

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

    $customfields_sql_select = "";
    $customfields_sql_join = "";
    foreach ($available_cols as $col) {
        if ($col->type === 'custom') {
            $join_alias = "ud_" . $col->fieldid;
            $customfields_sql_select .= ", {$join_alias}.data AS custom_{$col->fieldid} ";
            $customfields_sql_join .= " LEFT JOIN {user_info_data} {$join_alias} ON {$join_alias}.userid = u.id AND {$join_alias}.fieldid = {$col->fieldid} ";
        }
    }

    $userextended_sql_select = "";
    foreach ($available_cols as $col) {
        if ($col->type === 'user_extended') {
            $userextended_sql_select .= ", u.{$col->fieldid} AS user_{$col->fieldid} ";
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
            {$customfields_sql_select}
            {$userextended_sql_select}
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
        {$customfields_sql_join}
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
echo html_writer::div(report_lumniareport_get_string('notstarted', 'Não Iniciados'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $notstarted, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// In Progress
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-primary mb-3');
echo html_writer::div(report_lumniareport_get_string('inprogress', 'Em Progresso'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $inprogress, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Certified
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-white bg-success mb-3');
echo html_writer::div(report_lumniareport_get_string('certified', 'Certificados (Concluídos)'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $certified, ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // Fim do dashboard.

if ($courseid) {
    // Adicionar seção de Opções de Exportação e Tabela de Dados.
    echo html_writer::start_div('mt-4');
    echo html_writer::tag('h3', report_lumniareport_get_string('dashboardandexport', 'Dashboard e Exportação'));

    // Painel de Filtros e Exportação (Usando Moodle Form API)
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    $form_action = new moodle_url('/report/lumniareport/export.php');
    $mform = new \report_lumniareport\form\export_form($form_action, null, 'post');

    // Set initial data for form
    $initialdata = [
        'course' => $courseid,
        'datainicio' => $filter_datainicio ? strtotime($filter_datainicio) : 0,
        'datafim' => $filter_datafim ? strtotime($filter_datafim) : 0,
    ];
    foreach ($filter_cols as $colkey => $val) {
        $initialdata['col_' . $colkey] = $val;
    }
    $mform->set_data($initialdata);

    $mform->display();

    echo html_writer::end_div();
    echo html_writer::end_div();

    // Carregar estilos CSS do plugin e módulo JS AMD
    $PAGE->requires->css(new moodle_url('/report/lumniareport/styles.css'));
    $PAGE->requires->js_call_amd('report_lumniareport/export_ui', 'init');

    // Tabela de Dados Simplificada com colunas dinâmicas
    echo html_writer::start_tag('div', ['class' => 'table-responsive']);
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', report_lumniareport_get_string('colname', 'Nome'));
    echo html_writer::tag('th', report_lumniareport_get_string('colemail', 'Email'));

    // Adicionar cabeçalhos opcionais baseados na seleção do usuário (incluindo custom fields).
    $colspan = 2;
    foreach ($available_cols as $colkey => $col) {
        if (!empty($filter_cols[$colkey])) {
            echo html_writer::tag('th', $col->name);
            $colspan++;
        }
    }

    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    foreach ($users as $u) {
        $status = report_lumniareport_get_string('notstarted', 'Não Iniciados');
        if (!empty($u->timecompleted)) {
            $status = report_lumniareport_get_string('certified', 'Certificados (Concluídos)');
        } elseif (!empty($u->timestarted)) {
            $status = report_lumniareport_get_string('inprogress', 'Em Progresso');
        }

        $fullname = fullname($u);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $fullname);
        echo html_writer::tag('td', $u->email);

        // Exibir células das colunas opcionais (nativas e custom fields)
        foreach ($available_cols as $colkey => $col) {
            if (!empty($filter_cols[$colkey])) {
                if ($colkey === 'status') {
                    echo html_writer::tag('td', $status);
                } elseif ($colkey === 'inicio') {
                    $inicio_text = !empty($u->timestarted) ? userdate($u->timestarted) : '-';
                    echo html_writer::tag('td', $inicio_text);
                } elseif ($colkey === 'fim') {
                    $fim_text = !empty($u->timecompleted) ? userdate($u->timecompleted) : '-';
                    echo html_writer::tag('td', $fim_text);
                } elseif ($colkey === 'tempo') {
                    if (!empty($u->timestarted) && !empty($u->timecompleted)) {
                        $seconds = $u->timecompleted - $u->timestarted;
                    } elseif (!empty($u->timestarted)) {
                        $seconds = time() - $u->timestarted;
                    } else {
                        $seconds = 0;
                    }
                    echo html_writer::tag('td', report_lumniareport_format_time_elapsed($seconds));
                } elseif ($col->type === 'custom') {
                    $custom_field_prop = 'custom_' . $col->fieldid;
                    $custom_text = isset($u->$custom_field_prop) ? $u->$custom_field_prop : '-';
                    echo html_writer::tag('td', $custom_text);
                } elseif ($col->type === 'user_extended') {
                    $extended_field_prop = 'user_' . $col->fieldid;
                    $extended_text = isset($u->$extended_field_prop) ? $u->$extended_field_prop : '-';
                    echo html_writer::tag('td', $extended_text);
                }
            }
        }

        echo html_writer::end_tag('tr');
    }

    if (empty($users)) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', report_lumniareport_get_string('nodatafound', 'Nenhum dado encontrado para o curso.'), ['colspan' => $colspan, 'class' => 'text-center']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // Fim do div.table-responsive

    echo html_writer::end_div(); // Fim da seção de dados.

} else {
    // Modo Administrador no nível do sistema: Exigir seletor de cursos.
    echo html_writer::start_div('alert alert-info mt-4');
    echo report_lumniareport_get_string('selectcourse', 'Selecione um curso para visualizar o relatório');

    // Exibir um select básico de cursos para redirecionar o admin
    global $DB;
    $allcourses = $DB->get_records('course', ['id' => SITEID], '', 'id', '<>'); // Ignorar o frontpage site
    $allcourses = $DB->get_records_sql('SELECT id, shortname, fullname FROM {course} WHERE id != :siteid ORDER BY fullname ASC', ['siteid' => SITEID]);

    $courselist = [];
    foreach ($allcourses as $c) {
        $courselist[$c->id] = $c->fullname;
    }

    $selecturl = new moodle_url('/report/lumniareport/index.php');
    $select = new single_select($selecturl, 'course', $courselist);
    echo $OUTPUT->render($select);

    echo html_writer::end_div();
}

echo $OUTPUT->footer();
