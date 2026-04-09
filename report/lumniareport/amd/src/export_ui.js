/**
 * AMD module for Lumniareport export UI interactions.
 *
 * @module     report_lumniareport/export_ui
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    var init = function() {
        var formatSelect = $('#id_format_export');
        var toggles = $('input[type="checkbox"][name^="col_"]');

        function checkPdfLimit() {
            if (formatSelect.val() === 'pdf') {
                var checkedCount = toggles.filter(':checked').length;

                if (checkedCount >= 2) {
                    toggles.not(':checked').prop('disabled', true);
                    // Emit a toast or show warning div if needed,
                    // though for simple limits keeping them disabled is functional.
                } else {
                    toggles.prop('disabled', false);
                }
            } else {
                toggles.prop('disabled', false);
            }
        }

        if (formatSelect.length > 0) {
            formatSelect.on('change', checkPdfLimit);
            toggles.on('change', checkPdfLimit);

            // Initial check
            checkPdfLimit();
        }
    };

    return {
        init: init
    };
});
