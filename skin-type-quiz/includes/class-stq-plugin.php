<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STQ_Plugin {
    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stq_quiz_users';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id varchar(100) NOT NULL,
            user_name varchar(190) NOT NULL,
            user_email varchar(190) NOT NULL,
            result_letter varchar(10) NOT NULL,
            result_title varchar(190) NOT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY user_email (user_email)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function init() {
        $this->register_hooks();
    }

    private function register_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'register_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'admin_post_stq_submit_quiz', array( $this, 'handle_form_submit' ) );
        add_action( 'admin_post_nopriv_stq_submit_quiz', array( $this, 'handle_form_submit' ) );
        add_action( 'wp_ajax_stq_submit_quiz', array( $this, 'handle_ajax_submit' ) );
        add_action( 'wp_ajax_nopriv_stq_submit_quiz', array( $this, 'handle_ajax_submit' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'skin-type-quiz', false, dirname( plugin_basename( STQ_PLUGIN_FILE ) ) . '/languages' );
    }

    public function register_settings() {
        if ( get_option( STQ_OPTION_SETTINGS ) === false ) {
            add_option( STQ_OPTION_SETTINGS, STQ_Admin::get_default_settings() );
        }

        if ( get_option( STQ_OPTION_QUIZZES ) === false ) {
            add_option( STQ_OPTION_QUIZZES, array( STQ_Admin::get_default_quiz() ) );
        }
    }

    public function register_assets() {
        wp_register_style(
            'stq-frontend',
            STQ_PLUGIN_URL . 'assets/skin-type-quiz.css',
            array(),
            STQ_PLUGIN_VERSION
        );

        wp_register_script(
            'stq-frontend',
            STQ_PLUGIN_URL . 'assets/skin-type-quiz.js',
            array(),
            STQ_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'stq-frontend',
            'STQQuiz',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'stq_quiz_submit' ),
            )
        );
    }

    public function register_shortcode() {
        $shortcode = new STQ_Shortcode();
        $shortcode->register();
    }

    public function handle_form_submit() {
        $handler = new STQ_Shortcode();
        $handler->handle_submission();
    }

    public function handle_ajax_submit() {
        $handler = new STQ_Shortcode();
        $handler->handle_submission( true );
    }
}
