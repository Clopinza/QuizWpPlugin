<?php
/**
 * Plugin Name: Skin Type Quiz
 * Description: Quiz tipo di pelle con shortcode, invio email e risultati.
 * Version: 1.0.0
 * Author: ChatGPT
 * Text Domain: skin-type-quiz
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STQ_PLUGIN_VERSION', '1.0.0' );
define( 'STQ_PLUGIN_FILE', __FILE__ );
define( 'STQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'STQ_OPTION_SETTINGS', 'stq_settings' );
define( 'STQ_OPTION_QUIZZES', 'stq_quizzes' );

require_once STQ_PLUGIN_DIR . 'includes/class-stq-plugin.php';
require_once STQ_PLUGIN_DIR . 'includes/class-stq-admin.php';
require_once STQ_PLUGIN_DIR . 'includes/class-stq-shortcode.php';

function stq_bootstrap() {
    $plugin = new STQ_Plugin();
    $plugin->init();
}

stq_bootstrap();
