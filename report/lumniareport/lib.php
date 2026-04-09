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
/**
 * Helper para atuar como fallback de strings. Em alguns ambientes que não
 * rodaram o "Purge Caches", o Moodle exibe as chaves entre [[colchetes]].
 * Esta função tenta pegar a string oficial, se retornar a string de placeholder, usa um fallback.
 *
 * @param string $identifier The string identifier
 * @param string $fallback The text to show if the string is not found
 * @return string
 */
function report_lumniareport_get_string($identifier, $fallback) {
    $str = get_string($identifier, 'report_lumniareport');
    if (strpos($str, '[[') === 0 && strpos($str, ']]') !== false) {
        return $fallback;
    }
    return $str;
}

/**
 * Retorna as colunas disponíveis para exportação, incluindo campos personalizados (profile fields).
 *
 * @return array Array de objetos detalhando cada coluna.
 */
function report_lumniareport_get_available_columns() {
    global $DB;

    // Colunas nativas padrão disponíveis (Nome e Email são fixos, então só os extras)
    $columns = [
        'status' => (object)[
            'id' => 'status',
            'name' => report_lumniareport_get_string('colstatus', 'Status'),
            'type' => 'native'
        ],
        'inicio' => (object)[
            'id' => 'inicio',
            'name' => report_lumniareport_get_string('colstart', 'Data de Início'),
            'type' => 'native'
        ],
        'fim' => (object)[
            'id' => 'fim',
            'name' => report_lumniareport_get_string('colend', 'Data de Conclusão'),
            'type' => 'native'
        ],
        'tempo' => (object)[
            'id' => 'tempo',
            'name' => report_lumniareport_get_string('coltimeelapsed', 'Tempo de Curso'),
            'type' => 'native'
        ]
    ];

    // Buscar campos de perfil personalizados do Moodle (user_info_field)
    if ($DB->get_manager()->table_exists('user_info_field')) {
        $profilefields = $DB->get_records('user_info_field', null, 'sortorder ASC', 'id, shortname, name');
        foreach ($profilefields as $pf) {
            // Usa o ID e marca como custom para cruzar na query depois
            $colkey = 'custom_' . $pf->id;
            $columns[$colkey] = (object)[
                'id' => $colkey,
                'name' => $pf->name,
                'type' => 'custom',
                'fieldid' => $pf->id
            ];
        }
    }

    return $columns;
}

/**
 * Formata os segundos decorridos em um formato legível (ex: X dias, Y horas)
 */
function report_lumniareport_format_time_elapsed($seconds) {
    if (!$seconds || $seconds <= 0) {
        return '-';
    }
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' ' . report_lumniareport_get_string('days', 'dias');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' ' . report_lumniareport_get_string('hours', 'horas');
    }
    if ($minutes > 0 && $days == 0) {
        $parts[] = $minutes . ' ' . report_lumniareport_get_string('minutes', 'min');
    }

    if (empty($parts)) {
        return report_lumniareport_get_string('lessthanaminute', '< 1 min');
    }

    return implode(', ', $parts);
}

function report_lumniareport_extend_navigation_course($navigation, $course, $coursecontext) {
    if (has_capability('report/lumniareport:view', $coursecontext)) {
        $url = new moodle_url('/report/lumniareport/index.php', ['course' => $course->id]);
        $name = report_lumniareport_get_string('pluginname', 'Lumniareport');
        $reportnode = $navigation->get('coursereports');
        if ($reportnode) {
            $node = $reportnode->add(
                $name,
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'report_lumniareport',
                new pix_icon('i/report', $name)
            );
        } else {
            // Fallback caso o nó coursereports não exista.
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
}
