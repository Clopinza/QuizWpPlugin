<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STQ_Shortcode {
    public function register() {
        add_shortcode( 'skin_type_quiz', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'id' => 'default',
            ),
            $atts,
            'skin_type_quiz'
        );

        $quiz = STQ_Admin::get_quiz_by_id( $atts['id'] );
        if ( ! $quiz ) {
            return '<div class="stq-quiz stq-error">' . esc_html__( 'Quiz non trovato.', 'skin-type-quiz' ) . '</div>';
        }

        wp_enqueue_style( 'stq-frontend' );
        wp_enqueue_script( 'stq-frontend' );

        $action_url = admin_url( 'admin-post.php' );

        ob_start();
        ?>
        <div class="stq-quiz" data-quiz-id="<?php echo esc_attr( $quiz['quiz_id'] ); ?>">
            <h2 class="stq-title"><?php echo esc_html( $quiz['title'] ); ?></h2>
            <form method="post" action="<?php echo esc_url( $action_url ); ?>" class="stq-form">
                <input type="hidden" name="action" value="stq_submit_quiz" />
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz['quiz_id'] ); ?>" />
                <input type="hidden" name="stq_quiz_nonce" value="<?php echo esc_attr( wp_create_nonce( 'stq_quiz_submit' ) ); ?>" />

                <div class="stq-progress" aria-hidden="true">
                    <div class="stq-progress-bar" style="width: 0%;"></div>
                </div>

                <div class="stq-stepper" data-total="<?php echo esc_attr( count( $quiz['questions'] ) ); ?>">
                    <?php foreach ( $quiz['questions'] as $index => $question ) : ?>
                        <fieldset class="stq-step" data-step="<?php echo esc_attr( $index ); ?>">
                            <legend><?php echo esc_html( $question['text'] ); ?></legend>
                            <div class="stq-answers">
                                <?php foreach ( $question['answers'] as $answer_index => $answer ) : ?>
                                    <?php
                                    $input_id = sprintf( 'stq_%s_%s_%s', $quiz['quiz_id'], $question['id'], $answer_index );
                                    ?>
                                    <label for="<?php echo esc_attr( $input_id ); ?>">
                                        <input
                                            type="radio"
                                            id="<?php echo esc_attr( $input_id ); ?>"
                                            name="answers[<?php echo esc_attr( $question['id'] ); ?>]"
                                            value="<?php echo esc_attr( $answer['letter'] ); ?>|<?php echo esc_attr( $answer['label'] ); ?>"
                                            required
                                        />
                                        <?php echo esc_html( $answer['label'] ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <div class="stq-user-fields">
                    <label>
                        <?php esc_html_e( 'Nome', 'skin-type-quiz' ); ?>
                        <input type="text" name="user_name" required />
                    </label>
                    <label>
                        <?php esc_html_e( 'Email', 'skin-type-quiz' ); ?>
                        <input type="email" name="user_email" required />
                    </label>
                </div>

                <div class="stq-navigation">
                    <button type="button" class="stq-prev" disabled><?php esc_html_e( 'Indietro', 'skin-type-quiz' ); ?></button>
                    <button type="button" class="stq-next"><?php esc_html_e( 'Avanti', 'skin-type-quiz' ); ?></button>
                    <button type="submit" class="stq-submit"><?php esc_html_e( 'Invia', 'skin-type-quiz' ); ?></button>
                </div>

                <div class="stq-result" aria-live="polite"></div>
            </form>

            <noscript>
                <style>.stq-stepper{display:block}.stq-step{display:block}.stq-navigation{display:none}.stq-progress{display:none}</style>
                <p><?php esc_html_e( 'JavaScript è disabilitato: tutte le domande sono visibili.', 'skin-type-quiz' ); ?></p>
            </noscript>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_submission( $is_ajax = false ) {
        $request = wp_unslash( $_POST );

        if ( $is_ajax ) {
            check_ajax_referer( 'stq_quiz_submit', 'nonce' );
        } else {
            if ( empty( $request['stq_quiz_nonce'] ) || ! wp_verify_nonce( $request['stq_quiz_nonce'], 'stq_quiz_submit' ) ) {
                wp_die( esc_html__( 'Nonce non valido.', 'skin-type-quiz' ) );
            }
        }

        $quiz_id = sanitize_text_field( $request['quiz_id'] ?? '' );
        $quiz    = STQ_Admin::get_quiz_by_id( $quiz_id );

        if ( ! $quiz ) {
            return $this->send_response( $is_ajax, false, __( 'Quiz non trovato.', 'skin-type-quiz' ) );
        }

        $user_name  = sanitize_text_field( $request['user_name'] ?? '' );
        $user_email = sanitize_email( $request['user_email'] ?? '' );

        if ( empty( $user_name ) || empty( $user_email ) || ! is_email( $user_email ) ) {
            return $this->send_response( $is_ajax, false, __( 'Nome o email non validi.', 'skin-type-quiz' ) );
        }

        if ( $this->is_rate_limited() ) {
            return $this->send_response( $is_ajax, false, __( 'Hai raggiunto il limite di invii. Riprova più tardi.', 'skin-type-quiz' ) );
        }

        $answers_raw = $request['answers'] ?? array();
        if ( empty( $answers_raw ) || ! is_array( $answers_raw ) ) {
            return $this->send_response( $is_ajax, false, __( 'Seleziona tutte le risposte.', 'skin-type-quiz' ) );
        }

        $parsed_answers = $this->parse_answers( $quiz['questions'], $answers_raw );
        if ( $parsed_answers['missing'] ) {
            return $this->send_response( $is_ajax, false, __( 'Seleziona tutte le risposte.', 'skin-type-quiz' ) );
        }

        $result = $this->calculate_result( $parsed_answers['letters'], $parsed_answers['last_letter'] );

        if ( empty( $quiz['results'][ $result ] ) ) {
            return $this->send_response( $is_ajax, false, __( 'Risultato non definito.', 'skin-type-quiz' ) );
        }

        $result_data = $quiz['results'][ $result ];
        $this->save_submission( $quiz_id, $user_name, $user_email, $result, $result_data['title'] );
        $this->send_emails( $quiz, $user_name, $user_email, $result, $result_data, $parsed_answers['summary'] );

        return $this->send_response(
            $is_ajax,
            true,
            '',
            array(
                'result_title'       => $result_data['title'],
                'result_description' => $result_data['description'],
            )
        );
    }

    private function parse_answers( $questions, $answers_raw ) {
        $letters = array();
        $summary = array();
        $missing = false;
        $last_letter = '';

        foreach ( $questions as $question ) {
            $question_id = $question['id'];
            if ( empty( $answers_raw[ $question_id ] ) ) {
                $missing = true;
                continue;
            }

            $value_parts = explode( '|', sanitize_text_field( $answers_raw[ $question_id ] ) );
            $letter      = strtoupper( trim( $value_parts[0] ?? '' ) );
            $label       = trim( $value_parts[1] ?? '' );

            if ( empty( $letter ) ) {
                $missing = true;
                continue;
            }

            $letters[]  = $letter;
            $last_letter = $letter;

            $summary[] = array(
                'question' => $question['text'],
                'answer'   => $label,
                'letter'   => $letter,
            );
        }

        return array(
            'letters'     => $letters,
            'summary'     => $summary,
            'missing'     => $missing,
            'last_letter' => $last_letter,
        );
    }

    private function calculate_result( $letters, $last_letter ) {
        $counts = array();
        foreach ( $letters as $letter ) {
            if ( ! isset( $counts[ $letter ] ) ) {
                $counts[ $letter ] = 0;
            }
            $counts[ $letter ]++;
        }

        arsort( $counts );
        $max = reset( $counts );
        $top_letters = array_keys( array_filter( $counts, function( $count ) use ( $max ) {
            return $count === $max;
        } ) );

        if ( count( $top_letters ) === 1 ) {
            return $top_letters[0];
        }

        if ( $last_letter && in_array( $last_letter, $top_letters, true ) ) {
            return $last_letter;
        }

        sort( $top_letters, SORT_STRING );
        return $top_letters[0];
    }

    private function send_emails( $quiz, $user_name, $user_email, $result_letter, $result_data, $summary ) {
        $settings = STQ_Admin::get_settings();
        $from_name = $settings['from_name'] ?: get_bloginfo( 'name' );
        $from_email = $settings['from_email'] ?: get_option( 'admin_email' );

        $headers = array(
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_name . ' <' . $from_email . '>',
            'Content-Type: text/html; charset=UTF-8',
        );

        $subject = sprintf( __( 'Il tuo risultato: %s', 'skin-type-quiz' ), $result_data['title'] );
        $body = $this->build_html_email( $quiz, $user_name, $result_letter, $result_data, $summary );

        wp_mail( $user_email, $subject, $body, $headers );

        if ( $settings['copy_admin'] === '1' ) {
            $admin_email = get_option( 'admin_email' );
            wp_mail( $admin_email, $subject, $body, $headers );
        }
    }

    private function build_html_email( $quiz, $user_name, $result_letter, $result_data, $summary ) {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url();
        $quiz_title = $quiz['title'] ?? __( 'Quiz tipo di pelle', 'skin-type-quiz' );

        $list_items = '';
        foreach ( $summary as $item ) {
            $list_items .= sprintf(
                '<li><strong>%s</strong><br>%s <em>(%s)</em></li>',
                esc_html( $item['question'] ),
                esc_html( $item['answer'] ),
                esc_html( $item['letter'] )
            );
        }

        $body = sprintf(
            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">' .
            '<div style="background:#f5f7fa;padding:20px;border-radius:8px;">' .
            '<h1 style="margin:0 0 10px;font-size:20px;">%s</h1>' .
            '<p style="margin:0 0 10px;">%s <strong>%s</strong></p>' .
            '<p style="margin:0 0 10px;">%s</p>' .
            '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;" />' .
            '<h2 style="margin:0 0 10px;font-size:18px;">%s</h2>' .
            '<p style="margin:0 0 10px;"><strong>%s</strong> <span style="color:#555;">(%s)</span></p>' .
            '<p style="margin:0 0 10px;">%s</p>' .
            '<h3 style="margin:20px 0 10px;font-size:16px;">%s</h3>' .
            '<ol style="padding-left:20px;margin:0;">%s</ol>' .
            '<p style="margin:20px 0 0;font-size:12px;color:#777;">%s <a href="%s" style="color:#007cba;">%s</a></p>' .
            '</div>' .
            '</div>',
            esc_html( $quiz_title ),
            esc_html__( 'Ciao', 'skin-type-quiz' ),
            esc_html( $user_name ),
            esc_html__( 'Ecco il tuo risultato personalizzato.', 'skin-type-quiz' ),
            esc_html__( 'Risultato', 'skin-type-quiz' ),
            esc_html( $result_data['title'] ),
            esc_html( $result_letter ),
            esc_html( $result_data['description'] ),
            esc_html__( 'Riepilogo risposte', 'skin-type-quiz' ),
            $list_items,
            esc_html__( 'Grazie per aver completato il quiz su', 'skin-type-quiz' ),
            esc_url( $site_url ),
            esc_html( $site_name )
        );

        return $body;
    }

    private function send_response( $is_ajax, $success, $message = '', $data = array() ) {
        if ( $is_ajax ) {
            wp_send_json(
                array(
                    'success' => $success,
                    'message' => $message,
                    'data'    => $data,
                )
            );
        }

        if ( $success ) {
            $result_html = sprintf(
                '<div class="stq-result"><strong>%s</strong> %s<p>%s</p></div>',
                esc_html__( 'Risultato:', 'skin-type-quiz' ),
                esc_html( $data['result_title'] ?? '' ),
                esc_html( $data['result_description'] ?? '' )
            );
        } else {
            $result_html = sprintf( '<div class="stq-result stq-error">%s</div>', esc_html( $message ) );
        }

        wp_die( $result_html );
    }

    private function save_submission( $quiz_id, $user_name, $user_email, $result_letter, $result_title ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stq_quiz_users';

        $wpdb->insert(
            $table_name,
            array(
                'quiz_id'       => $quiz_id,
                'user_name'     => $user_name,
                'user_email'    => $user_email,
                'result_letter' => $result_letter,
                'result_title'  => $result_title,
                'submitted_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private function is_rate_limited() {
        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
        $key = 'stq_rate_' . md5( $ip );
        $current = get_transient( $key );

        if ( false === $current ) {
            set_transient( $key, 1, HOUR_IN_SECONDS );
            return false;
        }

        if ( $current >= 5 ) {
            return true;
        }

        set_transient( $key, $current + 1, HOUR_IN_SECONDS );
        return false;
    }
}
