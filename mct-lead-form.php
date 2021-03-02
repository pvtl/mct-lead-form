<?php
/**
 * Plugin Name: MCT Lead Form
 * Plugin URI: https://www.pivotalagency.com.au/
 * Description: Include Lead Forms on your website.
 * Author: Pivotal Agency
 * Author URI: https://pivotal.agency
 * Version: 1.0.0
 * Requires at least: 5.4
 * Tested up to: 5.4
 * Requires PHP: 7.2
 * Text Domain: mct-lead-form
 *
 * @package MCT_Lead_Form
 */

defined( 'ABSPATH' ) || die();

define( 'MCT_PATH', dirname( __FILE__ ) );
define( 'MCT_TEXT_DOMAIN', 'mct-lead-form' );

require_once MCT_PATH . '/autoload.php';

MCT_Lead_Form\Classes\Form::init();
