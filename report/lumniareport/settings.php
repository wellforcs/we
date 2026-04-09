<?php
/**
 * Settings for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Registra a página como admin_externalpage para compatibilidade com admin_externalpage_setup no index.php
    $ADMIN->add('reports', new admin_externalpage(
        'reportlumniareport',
        get_string('pluginname', 'report_lumniareport'),
        new moodle_url('/report/lumniareport/index.php'),
        'report/lumniareport:view'
    ));
}
