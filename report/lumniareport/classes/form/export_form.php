<?php
/**
 * Export form definition for Lumniareport.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lumniareport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class export_form
 */
class export_form extends \moodleform {

    /**
     * Define the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        // Hidden element for the course ID.
        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        require_once(__DIR__ . '/../../lib.php');

        $mform->addElement('header', 'filtersexport_hdr', get_string('filtersexport', 'report_lumniareport'));

        // Date Selectors
        $mform->addElement('date_selector', 'datainicio', get_string('startdate', 'report_lumniareport'), ['optional' => true]);
        $mform->addElement('date_selector', 'datafim', get_string('enddate', 'report_lumniareport'), ['optional' => true]);

        // Export Format
        $formats = [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
            'pdf' => 'PDF'
        ];
        $mform->addElement('select', 'format_export', get_string('format', 'report_lumniareport'), $formats);
        $mform->setDefault('format_export', 'csv');

        // Checkboxes for extra columns (dinâmicos do banco e do core)
        $mform->addElement('header', 'cols_hdr', get_string('additionalcols', 'report_lumniareport'));

        $available_cols = report_lumniareport_get_available_columns();
        foreach ($available_cols as $colkey => $col) {
            // Em HTML, Moodle injeta as classes de form elements. Adicionaremos o attr data-is-toggle para o nosso AMD customizar.
            $mform->addElement('advcheckbox', 'col_' . $col->id, $col->name, '', ['group' => 1, 'data-is-toggle' => '1'], [0, 1]);

            // O Status vem checado por padrão (ou outros base, se necessário)
            if ($col->id === 'status') {
                $mform->setDefault('col_' . $col->id, 1);
            }
        }

        // PDF Background Upload
        $mform->addElement('header', 'pdf_hdr', get_string('pdfsettings', 'report_lumniareport'));
        $mform->addElement('filemanager', 'pdfbackground_filemanager', get_string('pdfbackground', 'report_lumniareport'), null, ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.jpg', '.png', '.jpeg']]);

        // Hide PDF settings if format is not PDF.
        $mform->hideIf('pdf_hdr', 'format_export', 'neq', 'pdf');
        $mform->hideIf('pdfbackground_filemanager', 'format_export', 'neq', 'pdf');

        // Action Buttons
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('exportreport', 'report_lumniareport'));
        $buttonarray[] = &$mform->createElement('submit', 'applyfilters', get_string('applyfilter', 'report_lumniareport'), ['class' => 'btn-secondary']);
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);

        $mform->closeHeaderBefore('buttonar');
    }
}
