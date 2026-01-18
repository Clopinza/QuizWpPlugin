<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STQ_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    public function register_menu() {
        add_options_page(
            __( 'Skin Type Quiz', 'skin-type-quiz' ),
            __( 'Skin Type Quiz', 'skin-type-quiz' ),
            'manage_options',
            'skin-type-quiz',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();
        $quizzes  = self::get_quizzes();
        $json     = wp_json_encode( $quizzes[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        $errors   = array();

        if ( isset( $_POST['stq_settings_submit'] ) ) {
            check_admin_referer( 'stq_settings_save', 'stq_settings_nonce' );

            $settings['from_name']  = sanitize_text_field( wp_unslash( $_POST['stq_from_name'] ?? '' ) );
            $settings['from_email'] = sanitize_email( wp_unslash( $_POST['stq_from_email'] ?? '' ) );
            $settings['copy_admin'] = isset( $_POST['stq_copy_admin'] ) ? '1' : '0';

            $json_input = wp_unslash( $_POST['stq_quiz_json'] ?? '' );
            $decoded    = json_decode( $json_input, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $errors[] = __( 'JSON non valido. Controlla la sintassi.', 'skin-type-quiz' );
            } else {
                $validation = self::validate_quiz_payload( $decoded );
                if ( ! $validation['valid'] ) {
                    $errors = array_merge( $errors, $validation['errors'] );
                } else {
                    $quizzes = array( $decoded );
                }
            }

            if ( empty( $errors ) ) {
                update_option( STQ_OPTION_SETTINGS, $settings );
                update_option( STQ_OPTION_QUIZZES, $quizzes );
                $json = wp_json_encode( $quizzes[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                add_settings_error( 'stq_messages', 'stq_message', __( 'Impostazioni salvate.', 'skin-type-quiz' ), 'updated' );
            } else {
                add_settings_error( 'stq_messages', 'stq_message', implode( ' ', $errors ), 'error' );
            }
        }

        settings_errors( 'stq_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Skin Type Quiz', 'skin-type-quiz' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'stq_settings_save', 'stq_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="stq_from_name"><?php esc_html_e( 'From name', 'skin-type-quiz' ); ?></label></th>
                        <td><input name="stq_from_name" type="text" id="stq_from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stq_from_email"><?php esc_html_e( 'From email', 'skin-type-quiz' ); ?></label></th>
                        <td><input name="stq_from_email" type="email" id="stq_from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Invia copia admin', 'skin-type-quiz' ); ?></th>
                        <td>
                            <label>
                                <input name="stq_copy_admin" type="checkbox" value="1" <?php checked( $settings['copy_admin'], '1' ); ?> />
                                <?php esc_html_e( 'Invia una copia all’email admin del sito', 'skin-type-quiz' ); ?>
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'JSON quiz', 'skin-type-quiz' ); ?></h2>
                <p><?php esc_html_e( 'Definisci domande e risultati. Supporto futuro per più quiz_id.', 'skin-type-quiz' ); ?></p>
                <textarea name="stq_quiz_json" rows="20" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $json ); ?></textarea>

                <p class="submit">
                    <button type="submit" name="stq_settings_submit" class="button button-primary">
                        <?php esc_html_e( 'Salva', 'skin-type-quiz' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function get_settings() {
        $settings = get_option( STQ_OPTION_SETTINGS, self::get_default_settings() );

        return wp_parse_args(
            $settings,
            self::get_default_settings()
        );
    }

    public static function get_quizzes() {
        $quizzes = get_option( STQ_OPTION_QUIZZES, array( self::get_default_quiz() ) );

        if ( empty( $quizzes ) || ! is_array( $quizzes ) ) {
            $quizzes = array( self::get_default_quiz() );
        }

        return $quizzes;
    }

    public static function get_quiz_by_id( $quiz_id ) {
        $quizzes = self::get_quizzes();
        foreach ( $quizzes as $quiz ) {
            if ( isset( $quiz['quiz_id'] ) && $quiz['quiz_id'] === $quiz_id ) {
                return $quiz;
            }
        }

        return null;
    }

    public static function get_default_settings() {
        return array(
            'from_name'  => get_bloginfo( 'name' ),
            'from_email' => get_option( 'admin_email' ),
            'copy_admin' => '1',
        );
    }

    public static function get_default_quiz() {
        return array(
            'quiz_id'  => 'default',
            'title'    => 'Quiz tipo di pelle',
            'questions' => array(
                array(
                    'id'      => 'q1',
                    'text'    => 'Dopo la detersione, la pelle tira?',
                    'answers' => array(
                        array( 'label' => 'Sì, spesso', 'letter' => 'A' ),
                        array( 'label' => 'A volte', 'letter' => 'B' ),
                        array( 'label' => 'Raramente', 'letter' => 'C' ),
                        array( 'label' => 'Mai', 'letter' => 'D' ),
                    ),
                ),
                array(
                    'id'      => 'q2',
                    'text'    => 'Come appare la zona T durante la giornata?',
                    'answers' => array(
                        array( 'label' => 'Lucida e oleosa', 'letter' => 'D' ),
                        array( 'label' => 'Leggermente lucida', 'letter' => 'B' ),
                        array( 'label' => 'Uniforme', 'letter' => 'C' ),
                        array( 'label' => 'Secca e opaca', 'letter' => 'A' ),
                    ),
                ),
            ),
            'results' => array(
                'A' => array(
                    'title'       => 'Pelle secca',
                    'description' => 'La tua pelle tende a tirare e necessita di idratazione intensa.',
                ),
                'B' => array(
                    'title'       => 'Pelle mista',
                    'description' => 'La zona T è più lucida mentre le guance restano equilibrate.',
                ),
                'C' => array(
                    'title'       => 'Pelle normale',
                    'description' => 'La tua pelle appare equilibrata e con pori poco visibili.',
                ),
                'D' => array(
                    'title'       => 'Pelle grassa',
                    'description' => 'La pelle produce sebo in eccesso e appare lucida.',
                ),
            ),
        );
    }

    public static function validate_quiz_payload( $payload ) {
        $errors = array();

        if ( ! is_array( $payload ) ) {
            $errors[] = __( 'Il JSON deve essere un oggetto.', 'skin-type-quiz' );
            return array( 'valid' => false, 'errors' => $errors );
        }

        if ( empty( $payload['quiz_id'] ) ) {
            $errors[] = __( 'quiz_id è obbligatorio.', 'skin-type-quiz' );
        }

        if ( empty( $payload['title'] ) ) {
            $errors[] = __( 'title è obbligatorio.', 'skin-type-quiz' );
        }

        if ( empty( $payload['questions'] ) || ! is_array( $payload['questions'] ) ) {
            $errors[] = __( 'questions deve essere un array.', 'skin-type-quiz' );
        } else {
            foreach ( $payload['questions'] as $question ) {
                if ( empty( $question['id'] ) || empty( $question['text'] ) ) {
                    $errors[] = __( 'Ogni domanda deve avere id e text.', 'skin-type-quiz' );
                    break;
                }
                if ( empty( $question['answers'] ) || ! is_array( $question['answers'] ) ) {
                    $errors[] = __( 'Ogni domanda deve avere answers.', 'skin-type-quiz' );
                    break;
                }
            }
        }

        if ( empty( $payload['results'] ) || ! is_array( $payload['results'] ) ) {
            $errors[] = __( 'results deve essere un oggetto con lettere.', 'skin-type-quiz' );
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }
}

new STQ_Admin();
