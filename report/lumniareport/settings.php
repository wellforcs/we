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
    // Para reports, em vez de admin_externalpage que tenta injetar settings sem layout,
    // criamos uma página de configuração real informando e direcionando o admin.
    $settings = new admin_settingpage('reportlumniareport', get_string('pluginname', 'report_lumniareport'));

    // Adicionamos um texto HTML no painel administrativo ensinando e direcionando o admin.
    $reporturl = new moodle_url('/report/lumniareport/index.php');
    $linkhtml = html_writer::link($reporturl, get_string('pluginname', 'report_lumniareport'), ['class' => 'btn btn-primary']);

    $settings->add(new admin_setting_heading(
        'reportlumniareport_heading',
        get_string('pluginname', 'report_lumniareport'),
        $linkhtml
    ));

    $ADMIN->add('reports', $settings);
}
