<?php
/**
 * Secured admin form actions.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Actions {
    private TRB_Source_Repository $sources;
    private TRB_Item_Repository $items;

    public function __construct() {
        $this->sources = new TRB_Source_Repository();
        $this->items   = new TRB_Item_Repository();
    }

    public function hooks(): void {
        $actions = array(
            'trb_save_source'     => 'save_source',
            'trb_delete_source'   => 'delete_source',
            'trb_toggle_source'   => 'toggle_source',
            'trb_run_import'      => 'run_import',
            'trb_save_settings'   => 'save_settings',
            'trb_save_briefing'   => 'save_briefing',
            'trb_generate'        => 'generate',
            'trb_create_draft'    => 'create_draft',
            'trb_dismiss_item'    => 'dismiss_item',
            'trb_retry_item'      => 'retry_item',
            'trb_clear_logs'      => 'clear_logs',
        );
        foreach ( $actions as $action => $method ) {
            add_action( 'admin_post_' . $action, array( $this, $method ) );
        }
    }

    public function save_source(): void {
        TRB_Security::require_manage( 'trb_save_source' );
        $input  = isset( $_POST['source'] ) && is_array( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : array();
        $result = $this->sources->save( $input );
        if ( is_wp_error( $result ) ) {
            $this->notice( 'error', $result->get_error_message() );
            $this->redirect( 'admin.php?page=trb-sources' );
        }
        $this->notice( 'success', __( 'Source saved.', 'the-runbook-briefings' ) );
        $this->redirect( 'admin.php?page=trb-sources' );
    }

    public function delete_source(): void {
        $id = absint( $_POST['source_id'] ?? 0 );
        TRB_Security::require_manage( 'trb_delete_source_' . $id );
        $source = $this->sources->find( $id );
        if ( ! $source ) {
            $this->notice( 'error', __( 'The source no longer exists.', 'the-runbook-briefings' ) );
        } else {
            $this->sources->delete( $id );
            TRB_Logger::log( 'info', 'source_deleted', __( 'An RSS source was deleted.', 'the-runbook-briefings' ), array( 'source_name' => $source->name ), $id );
            $this->notice( 'success', __( 'Source deleted. Previously imported item attribution was retained.', 'the-runbook-briefings' ) );
        }
        $this->redirect( 'admin.php?page=trb-sources' );
    }

    public function toggle_source(): void {
        $id = absint( $_POST['source_id'] ?? 0 );
        TRB_Security::require_manage( 'trb_toggle_source_' . $id );
        $enabled = ! empty( $_POST['enabled'] );
        $this->sources->set_enabled( $id, $enabled );
        $this->notice( 'success', $enabled ? __( 'Source enabled.', 'the-runbook-briefings' ) : __( 'Source disabled.', 'the-runbook-briefings' ) );
        $this->redirect( 'admin.php?page=trb-sources' );
    }

    public function run_import(): void {
        TRB_Security::require_manage( 'trb_run_import' );
        $stats = ( new TRB_Importer() )->run( true );
        $this->notice(
            'success',
            sprintf(
                /* translators: 1: imported count, 2: duplicate count, 3: error count. */
                __( 'Import finished: %1$d new, %2$d duplicates, %3$d errors.', 'the-runbook-briefings' ),
                $stats['imported'],
                $stats['duplicates'],
                $stats['errors']
            )
        );
        $this->redirect( 'admin.php?page=trb-review' );
    }

    public function save_settings(): void {
        TRB_Security::require_manage( 'trb_save_settings' );
        $input    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        $settings = TRB_Settings::sanitize( $input );

        if ( ! empty( $input['remove_api_key'] ) ) {
            $settings['api_key_encrypted'] = '';
        } elseif ( ! empty( $input['api_key'] ) ) {
            $encrypted = TRB_Credentials::encrypt( sanitize_text_field( (string) $input['api_key'] ) );
            if ( is_wp_error( $encrypted ) ) {
                $this->notice( 'error', $encrypted->get_error_message() );
                $this->redirect( 'admin.php?page=trb-settings' );
            }
            $settings['api_key_encrypted'] = $encrypted;
        }

        update_option( TRB_Settings::OPTION, $settings, false );
        TRB_Activator::schedule( true );
        TRB_Logger::log( 'info', 'settings_updated', __( 'Plugin settings were updated.', 'the-runbook-briefings' ) );
        $this->notice( 'success', __( 'Settings saved.', 'the-runbook-briefings' ) );
        $this->redirect( 'admin.php?page=trb-settings' );
    }

    public function save_briefing(): void {
        $id = absint( $_POST['item_id'] ?? 0 );
        TRB_Security::require_review( 'trb_save_briefing_' . $id );
        $item = $this->items->find( $id );
        if ( ! $item ) {
            $this->notice( 'error', __( 'The item no longer exists.', 'the-runbook-briefings' ) );
            $this->redirect( 'admin.php?page=trb-review' );
        }
        $this->items->save_briefing( $id, $this->briefing_input() );
        $this->items->transition( $id, (string) $item->status, 'briefing_saved', __( 'Editorial fields saved.', 'the-runbook-briefings' ), get_current_user_id() );
        $this->notice( 'success', __( 'Briefing changes saved.', 'the-runbook-briefings' ) );
        $this->redirect_item( $id );
    }

    public function generate(): void {
        $id = absint( $_POST['item_id'] ?? 0 );
        TRB_Security::require_review( 'trb_generate_' . $id );
        $item = $this->items->find( $id );
        if ( ! $item ) {
            $this->notice( 'error', __( 'The item no longer exists.', 'the-runbook-briefings' ) );
            $this->redirect( 'admin.php?page=trb-review' );
        }

        $this->items->save_briefing( $id, $this->briefing_input() );
        $this->items->transition( $id, 'processing', 'generation_started', __( 'AI generation started.', 'the-runbook-briefings' ), get_current_user_id() );
        $candidates = $this->internal_link_candidates( absint( $item->wp_post_id ) );
        $provider   = $this->provider();
        $result     = $provider->generate(
            array(
                'source_name'             => $item->source_name,
                'title'                   => $item->title,
                'published_at'            => $item->published_at,
                'source_url'              => $item->source_url,
                'excerpt'                 => $item->excerpt,
                'tone'                    => TRB_Settings::get( 'default_tone' ),
                'internal_link_candidates'=> $candidates,
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->items->transition(
                $id,
                'error',
                'generation_failed',
                $result->get_error_message(),
                get_current_user_id(),
                array( 'error_message' => sanitize_text_field( $result->get_error_message() ), 'retry_count' => (int) $item->retry_count + 1 )
            );
            TRB_Logger::log( 'error', 'generation_failed', $result->get_error_message(), array(), (int) $item->source_id, $id );
            $this->notice( 'error', $result->get_error_message() );
            $this->redirect_item( $id );
        }

        $fields = TRB_Briefing::sanitize_generated( $result, $candidates );
        $fields['editorial_notes'] = $this->briefing_input()['editorial_notes'];
        $this->items->save_briefing( $id, $fields );
        $this->items->transition( $id, 'new', 'generation_completed', __( 'AI suggestions generated for editorial review.', 'the-runbook-briefings' ), get_current_user_id(), array( 'error_message' => null ) );
        $this->notice( 'success', __( 'Suggestions generated. Review every field before creating a draft.', 'the-runbook-briefings' ) );
        $this->redirect_item( $id );
    }

    public function create_draft(): void {
        $id = absint( $_POST['item_id'] ?? 0 );
        TRB_Security::require_review( 'trb_create_draft_' . $id );
        if ( ! $this->items->find( $id ) ) {
            $this->notice( 'error', __( 'The item no longer exists.', 'the-runbook-briefings' ) );
            $this->redirect( 'admin.php?page=trb-review' );
        }
        $this->items->save_briefing( $id, $this->briefing_input() );
        $item   = $this->items->find( $id );
        $result = TRB_Draft::save( $item );
        if ( is_wp_error( $result ) ) {
            $this->notice( 'error', $result->get_error_message() );
            $this->redirect_item( $id );
        }

        $this->items->transition(
            $id,
            'drafted',
            'draft_created',
            __( 'WordPress draft created or updated.', 'the-runbook-briefings' ),
            get_current_user_id(),
            array( 'wp_post_id' => (int) $result, 'error_message' => null )
        );
        $this->notice( 'success', __( 'WordPress draft saved with source attribution.', 'the-runbook-briefings' ) );
        $this->redirect_item( $id );
    }

    public function dismiss_item(): void {
        $id = absint( $_POST['item_id'] ?? 0 );
        TRB_Security::require_review( 'trb_dismiss_item_' . $id );
        $this->items->transition( $id, 'dismissed', 'dismissed', __( 'Item dismissed by an editor.', 'the-runbook-briefings' ), get_current_user_id() );
        $this->notice( 'success', __( 'Item dismissed.', 'the-runbook-briefings' ) );
        $this->redirect( 'admin.php?page=trb-review' );
    }

    public function retry_item(): void {
        $id = absint( $_POST['item_id'] ?? 0 );
        TRB_Security::require_review( 'trb_retry_item_' . $id );
        $this->items->transition( $id, 'new', 'retried', __( 'Item returned to the review queue.', 'the-runbook-briefings' ), get_current_user_id(), array( 'error_message' => null ) );
        $this->notice( 'success', __( 'Item returned to the queue.', 'the-runbook-briefings' ) );
        $this->redirect_item( $id );
    }

    public function clear_logs(): void {
        TRB_Security::require_manage( 'trb_clear_logs' );
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . TRB_Database::table( 'logs' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $this->notice( 'success', __( 'Logs cleared.', 'the-runbook-briefings' ) );
        $this->redirect( 'admin.php?page=trb-logs' );
    }

    /**
     * @return array<string,mixed>
     */
    private function briefing_input(): array {
        $input = isset( $_POST['briefing'] ) && is_array( $_POST['briefing'] ) ? wp_unslash( $_POST['briefing'] ) : array();
        return array(
            'suggested_headline'     => (string) ( $input['suggested_headline'] ?? '' ),
            'factual_summary'        => (string) ( $input['factual_summary'] ?? '' ),
            'why_matters'            => (string) ( $input['why_matters'] ?? '' ),
            'practical_implications' => (string) ( $input['practical_implications'] ?? '' ),
            'internal_links'         => (string) ( $input['internal_links'] ?? '' ),
            'editorial_notes'        => (string) ( $input['editorial_notes'] ?? '' ),
        );
    }

    private function provider(): TRB_AI_Provider {
        if ( 'openai' === TRB_Settings::get( 'ai_provider' ) ) {
            return new TRB_OpenAI_Provider();
        }
        return new class() implements TRB_AI_Provider {
            public function is_configured(): bool { return false; }
            public function generate( array $input ) {
                return new WP_Error( 'trb_ai_disabled', __( 'AI generation is disabled. Use the manual briefing fields instead.', 'the-runbook-briefings' ) );
            }
        };
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function internal_link_candidates( int $exclude_id ): array {
        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => 'publish',
                'posts_per_page'         => 20,
                'post__not_in'           => $exclude_id ? array( $exclude_id ) : array(),
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );
        $candidates = array();
        foreach ( $query->posts as $post ) {
            $candidates[] = array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) );
        }
        return $candidates;
    }

    private function notice( string $type, string $message ): void {
        set_transient(
            'trb_notice_' . get_current_user_id(),
            array( 'type' => 'error' === $type ? 'error' : 'success', 'message' => sanitize_text_field( $message ) ),
            MINUTE_IN_SECONDS
        );
    }

    private function redirect_item( int $id ): void {
        $this->redirect( 'admin.php?page=trb-review&action=edit&item=' . $id );
    }

    private function redirect( string $path ): void {
        wp_safe_redirect( admin_url( $path ) );
        exit;
    }
}
