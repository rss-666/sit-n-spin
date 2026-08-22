<?php
/**
 * Explicit editor-driven WordPress draft creation.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Draft {
    /**
     * Create or update the linked draft.
     *
     * @return int|WP_Error Post ID or error.
     */
    public static function save( object $item ) {
        $title = trim( (string) $item->suggested_headline );
        if ( '' === $title ) {
            $title = (string) $item->title;
        }
        if ( '' === trim( (string) $item->factual_summary ) ) {
            return new WP_Error( 'trb_draft_summary', __( 'Add a factual summary before creating the WordPress draft.', 'the-runbook-briefings' ) );
        }

        $status = (string) TRB_Settings::get( 'default_post_status' );
        if ( ! in_array( $status, array( 'draft', 'pending' ), true ) ) {
            $status = 'draft';
        }
        $post = array(
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => TRB_Briefing::content_html( $item ),
            'post_status'  => $status,
            'post_type'    => 'post',
        );

        $post_id = absint( $item->wp_post_id ?? 0 );
        if ( $post_id && get_post( $post_id ) ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return new WP_Error( 'trb_draft_permission', __( 'You cannot update the linked WordPress draft.', 'the-runbook-briefings' ) );
            }
            $post['ID'] = $post_id;
            $result     = wp_update_post( wp_slash( $post ), true );
        } else {
            $result = wp_insert_post( wp_slash( $post ), true );
        }

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'trb_draft_failed', __( 'WordPress could not save the briefing draft.', 'the-runbook-briefings' ) );
        }

        $post_id = (int) $result;
        update_post_meta( $post_id, '_trb_item_id', (int) $item->id );
        update_post_meta( $post_id, '_trb_source_name', sanitize_text_field( (string) $item->source_name ) );
        update_post_meta( $post_id, '_trb_source_title', sanitize_text_field( (string) $item->title ) );
        update_post_meta( $post_id, '_trb_source_url', esc_url_raw( (string) $item->source_url ) );
        update_post_meta( $post_id, '_trb_source_published_at', sanitize_text_field( (string) $item->published_at ) );
        if ( ! empty( $item->canonical_url ) ) {
            update_post_meta( $post_id, '_trb_source_canonical_url', esc_url_raw( (string) $item->canonical_url ) );
        }
        return $post_id;
    }
}
