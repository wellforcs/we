<?php
/**
 * Version information for the Lumniareport plugin.
 *
 * @package    report_lumniareport
 * @copyright  2023 Seu Nome/Empresa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->component = 'report_lumniareport'; // Nome do plugin (tipo_nomedoplugin).
$plugin->version   = 2023102400;           // Versão atual do plugin (YYYYMMDDXX).
$plugin->requires  = 2022112800;           // Requer Moodle 4.1 ou superior (2022112800 é a build do 4.1).
$plugin->supported = [401, 503];           // Declarando suporte da versão 4.1 até a 5.3 (LTS/branches atuais).
$plugin->maturity  = MATURITY_ALPHA;       // Maturidade do código (ALPHA, BETA, RC, STABLE).
$plugin->release   = '0.1.0';              // Versão de release legível.
