<?php
/**
 * WP-Cron RSS importer using WordPress SimplePie integration.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Importer {
    private TRB_Source_Repository $sources;
    private TRB_Item_Repository $items;

    public function __construct( ?TRB_Source_Repository $sources = null, ?TRB_Item_Repository $items = null ) {
        $this->sources = $sources ?? new TRB_Source_Repository();
        $this->items   = $items ?? new TRB_Item_Repository();
    }

    /**
     * Import due sources with a short-lived overlap lock.
     *
     * @return array<string,int>
     */
    public function run( bool $force = false ): array {
        $stats = array( 'sources' => 0, 'imported' => 0, 'duplicates' => 0, 'filtered' => 0, 'errors' => 0 );
        if ( get_transient( 'trb_import_lock' ) ) {
            TRB_Logger::log( 'warning', 'import_locked', __( 'An import run was skipped because another run is active.', 'the-runbook-briefings' ) );
            return $stats;
        }
        set_transient( 'trb_import_lock', 1, 10 * MINUTE_IN_SECONDS );

        try {
            $limit = (int) TRB_Settings::get( 'max_items_per_run' );
            foreach ( $this->sources->all( true ) as $source ) {
                if ( $stats['imported'] >= $limit ) {
                    break;
                }
                if ( ! $force && ! $this->is_due( $source ) ) {
                    continue;
                }
                ++$stats['sources'];
                $result = $this->import_source( $source, $limit - $stats['imported'] );
                foreach ( array( 'imported', 'duplicates', 'filtered', 'errors' ) as $key ) {
                    $stats[ $key ] += $result[ $key ];
                }
            }
            TRB_Logger::prune();
        } finally {
            delete_transient( 'trb_import_lock' );
        }

        TRB_Logger::log( 'info', 'import_complete', __( 'Feed import run completed.', 'the-runbook-briefings' ), $stats );
        return $stats;
    }

    /**
     * @return array<string,int>
     */
    public function import_source( object $source, int $remaining ): array {
        $stats = array( 'imported' => 0, 'duplicates' => 0, 'filtered' => 0, 'errors' => 0 );
        $valid = TRB_URL::validate_feed_url( (string) $source->feed_url );
        if ( is_wp_error( $valid ) ) {
            return $this->source_error( $source, $valid->get_error_message(), $stats );
        }

        if ( ! function_exists( 'fetch_feed' ) ) {
            require_once ABSPATH . WPINC . '/feed.php';
        }

        add_filter( 'http_request_args', array( $this, 'safe_request_args' ), 10, 2 );
        add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'feed_cache_lifetime' ), 10, 2 );
        try {
            $feed = fetch_feed( (string) $source->feed_url );
        } catch ( Throwable $exception ) {
            $feed = new WP_Error( 'trb_feed_exception', __( 'The feed parser encountered an unexpected error.', 'the-runbook-briefings' ) );
        } finally {
            remove_filter( 'http_request_args', array( $this, 'safe_request_args' ), 10 );
            remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'feed_cache_lifetime' ), 10 );
        }

        if ( is_wp_error( $feed ) ) {
            return $this->source_error( $source, $feed->get_error_message(), $stats );
        }

        $maximum = min( max( 0, $remaining ), 50 );
        $entries = $feed->get_items( 0, $maximum > 0 ? $maximum * 3 : 0 );
        foreach ( $entries as $entry ) {
            if ( $stats['imported'] >= $maximum ) {
                break;
            }
            $result = $this->import_entry( $source, $entry );
            ++$stats[ $result ];
        }

        $this->sources->record_success( (int) $source->id );
        TRB_Logger::log( 'info', 'source_imported', __( 'RSS source fetched successfully.', 'the-runbook-briefings' ), $stats, (int) $source->id );
        return $stats;
    }

    /**
     * Force safe transport behavior while SimplePie requests a configured feed.
     *
     * @param array<string,mixed> $args HTTP arguments.
     * @return array<string,mixed>
     */
    public function safe_request_args( array $args, string $url ): array {
        $args['timeout']            = (int) TRB_Settings::get( 'request_timeout' );
        $args['redirection']        = 3;
        $args['reject_unsafe_urls'] = true;
        $args['user-agent']         = 'The Runbook Briefings/' . TRB_VERSION . '; ' . home_url( '/' );
        return $args;
    }

    public function feed_cache_lifetime( int $lifetime, string $url = '' ): int {
        return 15 * MINUTE_IN_SECONDS;
    }

    private function import_entry( object $source, object $entry ): string {
        $title = sanitize_text_field( (string) $entry->get_title() );
        $url   = esc_url_raw( (string) $entry->get_permalink(), array( 'http', 'https' ) );
        if ( '' === $title || '' === $url || ! wp_http_validate_url( $url ) ) {
            return 'filtered';
        }

        $normalized = TRB_URL::normalize( $url );
        $url_hash   = TRB_URL::hash( $url );
        if ( '' === $normalized || '' === $url_hash ) {
            return 'filtered';
        }
        if ( $this->items->find_by_url_hash( $url_hash ) ) {
            return 'duplicates';
        }

        $timestamp = (int) $entry->get_date( 'U' );
        if ( ! TRB_Filter::is_within_date_range( $timestamp, (int) TRB_Settings::get( 'max_item_age_days' ) ) ) {
            return 'filtered';
        }

        $excerpt = TRB_Filter::excerpt( (string) $entry->get_description(), (int) TRB_Settings::get( 'max_excerpt_length' ) );
        if ( ! TRB_Filter::matches_keywords( $title, $excerpt, TRB_Filter::terms( (string) $source->keywords ) ) ) {
            return 'filtered';
        }

        $categories = array();
        foreach ( (array) $entry->get_categories() as $category ) {
            if ( is_object( $category ) && method_exists( $category, 'get_label' ) ) {
                $label = sanitize_text_field( (string) $category->get_label() );
                if ( '' !== $label ) {
                    $categories[] = $label;
                }
            }
        }
        if ( ! TRB_Filter::matches_categories( $categories, TRB_Filter::terms( (string) $source->categories ) ) ) {
            return 'filtered';
        }

        $author      = $entry->get_author();
        $author_name = is_object( $author ) && method_exists( $author, 'get_name' ) ? (string) $author->get_name() : '';
        $duplicate   = $this->items->closest_title( $title );
        $threshold   = (float) apply_filters( 'trb_duplicate_title_threshold', 0.86 );
        $is_duplicate = $duplicate['item'] && $duplicate['confidence'] >= $threshold;

        $inserted = $this->items->insert(
            array(
                'source_id'            => (int) $source->id,
                'source_name'          => (string) $source->name,
                'guid'                 => (string) $entry->get_id( true ),
                'source_url'           => $url,
                'canonical_url'        => $url,
                'normalized_url'       => $normalized,
                'url_hash'             => $url_hash,
                'title'                => $title,
                'author'               => $author_name,
                'published_at'         => $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null,
                'categories'           => $categories,
                'excerpt'              => $excerpt,
                'status'               => $is_duplicate ? 'dismissed' : 'new',
                'duplicate_of'         => $is_duplicate ? (int) $duplicate['item']->id : null,
                'duplicate_confidence' => $is_duplicate ? (float) $duplicate['confidence'] : 0,
            )
        );

        if ( is_wp_error( $inserted ) ) {
            TRB_Logger::log( 'error', 'item_import_failed', $inserted->get_error_message(), array(), (int) $source->id );
            return 'errors';
        }
        return $is_duplicate ? 'duplicates' : 'imported';
    }

    /**
     * @param array<string,int> $stats Stats.
     * @return array<string,int>
     */
    private function source_error( object $source, string $message, array $stats ): array {
        ++$stats['errors'];
        $safe_message = sanitize_text_field( $message );
        $this->sources->record_error( (int) $source->id, $safe_message );
        TRB_Logger::log( 'error', 'source_fetch_failed', $safe_message, array(), (int) $source->id );
        return $stats;
    }

    private function is_due( object $source ): bool {
        if ( empty( $source->last_fetched_at ) ) {
            return true;
        }
        $frequency = 'inherit' === $source->import_frequency
            ? (string) TRB_Settings::get( 'cron_frequency' )
            : (string) $source->import_frequency;
        $seconds = array(
            'trb_fifteen_minutes' => 15 * MINUTE_IN_SECONDS,
            'hourly'              => HOUR_IN_SECONDS,
            'twicedaily'          => 12 * HOUR_IN_SECONDS,
            'daily'               => DAY_IN_SECONDS,
        );
        $last = strtotime( (string) $source->last_fetched_at . ' UTC' ) ?: 0;
        return $last <= time() - ( $seconds[ $frequency ] ?? HOUR_IN_SECONDS );
    }
}
