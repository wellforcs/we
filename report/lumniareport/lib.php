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
            'name' => get_string('colstatus', 'report_lumniareport'),
            'type' => 'native'
        ],
        'inicio' => (object)[
            'id' => 'inicio',
            'name' => get_string('colstart', 'report_lumniareport'),
            'type' => 'native'
        ],
        'fim' => (object)[
            'id' => 'fim',
            'name' => get_string('colend', 'report_lumniareport'),
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

function report_lumniareport_extend_navigation_course($navigation, $course, $coursecontext) {
    if (has_capability('report/lumniareport:view', $coursecontext)) {
        $url = new moodle_url('/report/lumniareport/index.php', ['course' => $course->id]);
        $name = get_string('pluginname', 'report_lumniareport');
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
