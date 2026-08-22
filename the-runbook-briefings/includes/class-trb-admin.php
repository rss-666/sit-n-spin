<?php
/**
 * WordPress-native administration screens.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Admin {
    private TRB_Source_Repository $sources;
    private TRB_Item_Repository $items;

    public function __construct() {
        $this->sources = new TRB_Source_Repository();
        $this->items   = new TRB_Item_Repository();
    }

    public function hooks(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_notices', array( $this, 'notice' ) );
    }

    public function menu(): void {
        add_menu_page(
            __( 'Content Briefings', 'the-runbook-briefings' ),
            __( 'Content Briefings', 'the-runbook-briefings' ),
            'review_runbook_briefings',
            'trb-dashboard',
            array( $this, 'dashboard' ),
            'dashicons-media-document',
            26
        );
        add_submenu_page( 'trb-dashboard', __( 'Dashboard', 'the-runbook-briefings' ), __( 'Dashboard', 'the-runbook-briefings' ), 'review_runbook_briefings', 'trb-dashboard', array( $this, 'dashboard' ) );
        add_submenu_page( 'trb-dashboard', __( 'Sources', 'the-runbook-briefings' ), __( 'Sources', 'the-runbook-briefings' ), 'manage_options', 'trb-sources', array( $this, 'sources' ) );
        add_submenu_page( 'trb-dashboard', __( 'Review Queue', 'the-runbook-briefings' ), __( 'Review Queue', 'the-runbook-briefings' ), 'review_runbook_briefings', 'trb-review', array( $this, 'review' ) );
        add_submenu_page( 'trb-dashboard', __( 'Settings', 'the-runbook-briefings' ), __( 'Settings', 'the-runbook-briefings' ), 'manage_options', 'trb-settings', array( $this, 'settings' ) );
        add_submenu_page( 'trb-dashboard', __( 'Logs', 'the-runbook-briefings' ), __( 'Logs', 'the-runbook-briefings' ), 'manage_options', 'trb-logs', array( $this, 'logs' ) );
    }

    public function assets( string $hook ): void {
        if ( ! str_contains( $hook, 'trb-' ) ) {
            return;
        }
        wp_enqueue_style( 'trb-admin', TRB_PLUGIN_URL . 'assets/admin.css', array(), TRB_VERSION );
        wp_enqueue_script( 'trb-admin', TRB_PLUGIN_URL . 'assets/admin.js', array(), TRB_VERSION, true );
    }

    public function notice(): void {
        $key    = 'trb_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        if ( ! is_array( $notice ) ) {
            return;
        }
        delete_transient( $key );
        $class = 'error' === ( $notice['type'] ?? '' ) ? 'notice notice-error' : 'notice notice-success is-dismissible';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( (string) ( $notice['message'] ?? '' ) ) . '</p></div>';
    }

    public function dashboard(): void {
        $this->guard_review();
        $counts  = $this->items->counts();
        $sources = $this->sources->all();
        ?>
        <div class="wrap trb-wrap">
            <h1><?php esc_html_e( 'Content Briefings', 'the-runbook-briefings' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Turn hosting news and security alerts into original, engineer-reviewed Runbook briefings.', 'the-runbook-briefings' ); ?></p>
            <div class="trb-stats">
                <?php foreach ( array( 'new', 'processing', 'drafted', 'published', 'dismissed', 'error' ) as $status ) : ?>
                    <a class="trb-stat" href="<?php echo esc_url( admin_url( 'admin.php?page=trb-review&status=' . $status ) ); ?>">
                        <span class="trb-stat-number"><?php echo esc_html( (string) $counts[ $status ] ); ?></span>
                        <span><?php echo esc_html( $this->status_label( $status ) ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="trb-grid">
                <section class="trb-card">
                    <h2><?php esc_html_e( 'Editorial workflow', 'the-runbook-briefings' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Add and approve RSS sources.', 'the-runbook-briefings' ); ?></li>
                        <li><?php esc_html_e( 'Import and filter short source excerpts.', 'the-runbook-briefings' ); ?></li>
                        <li><?php esc_html_e( 'Review, generate, or manually write a briefing.', 'the-runbook-briefings' ); ?></li>
                        <li><?php esc_html_e( 'Create a source-attributed WordPress draft.', 'the-runbook-briefings' ); ?></li>
                    </ol>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=trb-review' ) ); ?>"><?php esc_html_e( 'Open review queue', 'the-runbook-briefings' ); ?></a>
                </section>
                <section class="trb-card">
                    <h2><?php esc_html_e( 'Source health', 'the-runbook-briefings' ); ?></h2>
                    <p><?php echo esc_html( sprintf( /* translators: %d: source count. */ _n( '%d configured source', '%d configured sources', count( $sources ), 'the-runbook-briefings' ), count( $sources ) ) ); ?></p>
                    <?php if ( TRB_Security::can_manage() ) : ?>
                        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=trb-sources' ) ); ?>"><?php esc_html_e( 'Manage sources', 'the-runbook-briefings' ); ?></a></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="trb_run_import">
                            <?php wp_nonce_field( 'trb_run_import' ); ?>
                            <button class="button" type="submit"><?php esc_html_e( 'Run import now', 'the-runbook-briefings' ); ?></button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function sources(): void {
        $this->guard_manage();
        $edit_id = absint( $_GET['edit'] ?? 0 );
        $editing = $edit_id ? $this->sources->find( $edit_id ) : null;
        $source  = $editing ?: (object) array(
            'id' => 0, 'name' => '', 'feed_url' => '', 'enabled' => 1, 'keywords' => '', 'categories' => '',
            'reliability_notes' => '', 'import_frequency' => 'inherit',
        );
        $sources = $this->sources->all();
        ?>
        <div class="wrap trb-wrap">
            <h1><?php esc_html_e( 'RSS Sources', 'the-runbook-briefings' ); ?></h1>
            <div class="trb-grid trb-grid-sources">
                <section class="trb-card">
                    <h2><?php echo $editing ? esc_html__( 'Edit source', 'the-runbook-briefings' ) : esc_html__( 'Add source', 'the-runbook-briefings' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="trb_save_source">
                        <input type="hidden" name="source[id]" value="<?php echo esc_attr( (string) $source->id ); ?>">
                        <?php wp_nonce_field( 'trb_save_source' ); ?>
                        <p><label for="trb-source-name"><strong><?php esc_html_e( 'Feed name', 'the-runbook-briefings' ); ?></strong></label><br>
                            <input class="regular-text" id="trb-source-name" name="source[name]" type="text" required value="<?php echo esc_attr( (string) $source->name ); ?>"></p>
                        <p><label for="trb-feed-url"><strong><?php esc_html_e( 'Feed URL', 'the-runbook-briefings' ); ?></strong></label><br>
                            <input class="large-text" id="trb-feed-url" name="source[feed_url]" type="url" required placeholder="https://example.com/feed/" value="<?php echo esc_attr( (string) $source->feed_url ); ?>"></p>
                        <p><label><input name="source[enabled]" type="checkbox" value="1" <?php checked( (int) $source->enabled, 1 ); ?>> <?php esc_html_e( 'Enabled', 'the-runbook-briefings' ); ?></label></p>
                        <p><label for="trb-keywords"><strong><?php esc_html_e( 'Keywords', 'the-runbook-briefings' ); ?></strong></label><br>
                            <textarea class="large-text" id="trb-keywords" name="source[keywords]" rows="3" placeholder="WordPress, hosting, vulnerability"><?php echo esc_textarea( (string) $source->keywords ); ?></textarea><br>
                            <span class="description"><?php esc_html_e( 'Comma or newline separated. Any match is accepted; leave blank to accept all.', 'the-runbook-briefings' ); ?></span></p>
                        <p><label for="trb-categories"><strong><?php esc_html_e( 'Feed categories', 'the-runbook-briefings' ); ?></strong></label><br>
                            <textarea class="large-text" id="trb-categories" name="source[categories]" rows="2"><?php echo esc_textarea( (string) $source->categories ); ?></textarea></p>
                        <p><label for="trb-frequency"><strong><?php esc_html_e( 'Import frequency', 'the-runbook-briefings' ); ?></strong></label><br>
                            <select id="trb-frequency" name="source[import_frequency]">
                                <?php foreach ( array( 'inherit' => __( 'Use global schedule', 'the-runbook-briefings' ), 'hourly' => __( 'Hourly', 'the-runbook-briefings' ), 'twicedaily' => __( 'Twice daily', 'the-runbook-briefings' ), 'daily' => __( 'Daily', 'the-runbook-briefings' ) ) as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $source->import_frequency, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                        <p><label for="trb-reliability"><strong><?php esc_html_e( 'Reliability notes', 'the-runbook-briefings' ); ?></strong></label><br>
                            <textarea class="large-text" id="trb-reliability" name="source[reliability_notes]" rows="3"><?php echo esc_textarea( (string) $source->reliability_notes ); ?></textarea></p>
                        <?php submit_button( $editing ? __( 'Update source', 'the-runbook-briefings' ) : __( 'Add source', 'the-runbook-briefings' ), 'primary', 'submit', false ); ?>
                        <?php if ( $editing ) : ?> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=trb-sources' ) ); ?>"><?php esc_html_e( 'Cancel', 'the-runbook-briefings' ); ?></a><?php endif; ?>
                    </form>
                </section>
                <section class="trb-card trb-card-wide">
                    <h2><?php esc_html_e( 'Configured sources', 'the-runbook-briefings' ); ?></h2>
                    <?php if ( empty( $sources ) ) : ?>
                        <div class="trb-empty"><p><?php esc_html_e( 'No feeds yet. Add an approved public RSS URL to begin.', 'the-runbook-briefings' ); ?></p></div>
                    <?php else : ?>
                        <div class="trb-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Source', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Health', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Last success', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Actions', 'the-runbook-briefings' ); ?></th></tr></thead><tbody>
                            <?php foreach ( $sources as $row ) : ?>
                                <tr><td><strong><?php echo esc_html( $row->name ); ?></strong><br><a href="<?php echo esc_url( $row->feed_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( $row->feed_url ); ?></a></td>
                                    <td><?php echo $this->source_health_html( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td><?php echo $row->last_success_at ? esc_html( get_date_from_gmt( $row->last_success_at, 'Y-m-d H:i' ) ) : '—'; ?></td>
                                    <td class="trb-actions"><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=trb-sources&edit=' . (int) $row->id ) ); ?>"><?php esc_html_e( 'Edit', 'the-runbook-briefings' ); ?></a>
                                        <?php $this->source_action_forms( $row ); ?>
                                    </td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function review(): void {
        $this->guard_review();
        $item_id = absint( $_GET['item'] ?? 0 );
        if ( 'edit' === ( $_GET['action'] ?? '' ) && $item_id ) {
            $this->review_item( $item_id );
            return;
        }

        $status    = sanitize_key( (string) ( $_GET['status'] ?? '' ) );
        $source_id = absint( $_GET['source_id'] ?? 0 );
        $search    = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
        $items     = $this->items->query( array( 'status' => $status, 'source_id' => $source_id, 'search' => $search, 'limit' => 50 ) );
        $sources   = $this->sources->all();
        ?>
        <div class="wrap trb-wrap">
            <h1><?php esc_html_e( 'Review Queue', 'the-runbook-briefings' ); ?></h1>
            <form method="get" class="trb-filters">
                <input type="hidden" name="page" value="trb-review">
                <label class="screen-reader-text" for="trb-status-filter"><?php esc_html_e( 'Filter by status', 'the-runbook-briefings' ); ?></label>
                <select id="trb-status-filter" name="status"><option value=""><?php esc_html_e( 'All statuses', 'the-runbook-briefings' ); ?></option>
                    <?php foreach ( TRB_Item_Repository::STATUSES as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( $this->status_label( $option ) ); ?></option><?php endforeach; ?>
                </select>
                <label class="screen-reader-text" for="trb-source-filter"><?php esc_html_e( 'Filter by source', 'the-runbook-briefings' ); ?></label>
                <select id="trb-source-filter" name="source_id"><option value="0"><?php esc_html_e( 'All sources', 'the-runbook-briefings' ); ?></option>
                    <?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( (string) $source->id ); ?>" <?php selected( $source_id, (int) $source->id ); ?>><?php echo esc_html( $source->name ); ?></option><?php endforeach; ?>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search title or source', 'the-runbook-briefings' ); ?>">
                <button class="button" type="submit"><?php esc_html_e( 'Filter', 'the-runbook-briefings' ); ?></button>
            </form>
            <?php if ( empty( $items ) ) : ?>
                <div class="trb-card trb-empty"><h2><?php esc_html_e( 'Nothing to review', 'the-runbook-briefings' ); ?></h2><p><?php esc_html_e( 'Adjust the filters or ask an administrator to add and import an RSS source.', 'the-runbook-briefings' ); ?></p></div>
            <?php else : ?>
                <div class="trb-queue">
                    <?php foreach ( $items as $item ) : ?>
                        <article class="trb-card trb-queue-item">
                            <div class="trb-queue-meta"><span class="trb-status trb-status-<?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( $this->status_label( $item->status ) ); ?></span><span><?php echo esc_html( $item->source_name ); ?></span><?php if ( $item->published_at ) : ?><time><?php echo esc_html( get_date_from_gmt( $item->published_at, 'M j, Y' ) ); ?></time><?php endif; ?></div>
                            <h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=trb-review&action=edit&item=' . (int) $item->id ) ); ?>"><?php echo esc_html( $item->title ); ?></a></h2>
                            <p><?php echo esc_html( wp_trim_words( $item->excerpt, 38 ) ); ?></p>
                            <?php if ( $item->duplicate_of ) : ?><p class="trb-duplicate"><?php echo esc_html( sprintf( /* translators: 1: item ID, 2: percentage. */ __( 'Probable duplicate of item #%1$d (%2$d%% confidence)', 'the-runbook-briefings' ), $item->duplicate_of, round( (float) $item->duplicate_confidence * 100 ) ) ); ?></p><?php endif; ?>
                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=trb-review&action=edit&item=' . (int) $item->id ) ); ?>"><?php esc_html_e( 'Review', 'the-runbook-briefings' ); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function settings(): void {
        $this->guard_manage();
        $settings = TRB_Settings::get();
        $has_key  = '' !== (string) $settings['api_key_encrypted'];
        ?>
        <div class="wrap trb-wrap"><h1><?php esc_html_e( 'Runbook Briefings Settings', 'the-runbook-briefings' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trb-card trb-settings-form">
                <input type="hidden" name="action" value="trb_save_settings"><?php wp_nonce_field( 'trb_save_settings' ); ?>
                <h2><?php esc_html_e( 'AI provider', 'the-runbook-briefings' ); ?></h2>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th><label for="trb-provider"><?php esc_html_e( 'Provider', 'the-runbook-briefings' ); ?></label></th><td><select id="trb-provider" name="settings[ai_provider]"><option value="openai" <?php selected( $settings['ai_provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI-compatible', 'the-runbook-briefings' ); ?></option><option value="none" <?php selected( $settings['ai_provider'], 'none' ); ?>><?php esc_html_e( 'Disabled / manual only', 'the-runbook-briefings' ); ?></option></select></td></tr>
                    <tr><th><label for="trb-api-key"><?php esc_html_e( 'API key', 'the-runbook-briefings' ); ?></label></th><td><input id="trb-api-key" name="settings[api_key]" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( 'Encrypted key saved; enter to replace', 'the-runbook-briefings' ) : ''; ?>"><p class="description"><?php esc_html_e( 'Stored with authenticated encryption derived from WordPress salts. It is never displayed after saving.', 'the-runbook-briefings' ); ?></p><?php if ( $has_key ) : ?><label><input type="checkbox" name="settings[remove_api_key]" value="1"> <?php esc_html_e( 'Remove saved key', 'the-runbook-briefings' ); ?></label><?php endif; ?></td></tr>
                    <tr><th><label for="trb-model"><?php esc_html_e( 'Model', 'the-runbook-briefings' ); ?></label></th><td><input id="trb-model" name="settings[model]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['model'] ); ?>"></td></tr>
                    <tr><th><label for="trb-tone"><?php esc_html_e( 'Default tone', 'the-runbook-briefings' ); ?></label></th><td><select id="trb-tone" name="settings[default_tone]"><?php foreach ( array( 'technical', 'beginner-friendly', 'concise', 'editorial' ) as $tone ) : ?><option value="<?php echo esc_attr( $tone ); ?>" <?php selected( $settings['default_tone'], $tone ); ?>><?php echo esc_html( ucfirst( $tone ) ); ?></option><?php endforeach; ?></select></td></tr>
                </tbody></table>
                <h2><?php esc_html_e( 'Import and publishing safeguards', 'the-runbook-briefings' ); ?></h2>
                <table class="form-table" role="presentation"><tbody>
                    <?php $this->number_setting( 'max_excerpt_length', __( 'Maximum source excerpt characters', 'the-runbook-briefings' ), $settings, 100, 5000 ); ?>
                    <tr><th><label for="trb-cron"><?php esc_html_e( 'Cron frequency', 'the-runbook-briefings' ); ?></label></th><td><select id="trb-cron" name="settings[cron_frequency]"><?php foreach ( array( 'trb_fifteen_minutes' => __( 'Every 15 minutes', 'the-runbook-briefings' ), 'hourly' => __( 'Hourly', 'the-runbook-briefings' ), 'twicedaily' => __( 'Twice daily', 'the-runbook-briefings' ), 'daily' => __( 'Daily', 'the-runbook-briefings' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['cron_frequency'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th><label for="trb-post-status"><?php esc_html_e( 'Default post status', 'the-runbook-briefings' ); ?></label></th><td><select id="trb-post-status" name="settings[default_post_status]"><option value="draft" <?php selected( $settings['default_post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'the-runbook-briefings' ); ?></option><option value="pending" <?php selected( $settings['default_post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending review', 'the-runbook-briefings' ); ?></option></select><p class="description"><?php esc_html_e( 'Publishing is intentionally unavailable here.', 'the-runbook-briefings' ); ?></p></td></tr>
                    <?php $this->number_setting( 'max_items_per_run', __( 'Maximum new items per run', 'the-runbook-briefings' ), $settings, 1, 100 ); ?>
                    <?php $this->number_setting( 'max_item_age_days', __( 'Maximum item age in days', 'the-runbook-briefings' ), $settings, 1, 365 ); ?>
                    <?php $this->number_setting( 'request_timeout', __( 'External request timeout (seconds)', 'the-runbook-briefings' ), $settings, 5, 60 ); ?>
                    <?php $this->number_setting( 'retry_limit', __( 'AI retry limit', 'the-runbook-briefings' ), $settings, 0, 5 ); ?>
                </tbody></table>
                <h2><?php esc_html_e( 'Data retention', 'the-runbook-briefings' ); ?></h2><p><label><input type="checkbox" name="settings[delete_data_on_uninstall]" value="1" <?php checked( (int) $settings['delete_data_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Delete plugin tables and settings when the plugin is uninstalled', 'the-runbook-briefings' ); ?></label></p>
                <?php submit_button( __( 'Save settings', 'the-runbook-briefings' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function logs(): void {
        $this->guard_manage();
        global $wpdb;
        $table = TRB_Database::table( 'logs' );
        $logs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT 200" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        ?>
        <div class="wrap trb-wrap"><h1><?php esc_html_e( 'Processing Logs', 'the-runbook-briefings' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Operational events are retained for 90 days. Credentials and authorization headers are redacted.', 'the-runbook-briefings' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trb-clear-form"><input type="hidden" name="action" value="trb_clear_logs"><?php wp_nonce_field( 'trb_clear_logs' ); ?><button class="button" type="submit" data-trb-confirm="<?php esc_attr_e( 'Clear all logs?', 'the-runbook-briefings' ); ?>"><?php esc_html_e( 'Clear logs', 'the-runbook-briefings' ); ?></button></form>
            <?php if ( empty( $logs ) ) : ?><div class="trb-card trb-empty"><p><?php esc_html_e( 'No processing events have been recorded.', 'the-runbook-briefings' ); ?></p></div><?php else : ?>
                <div class="trb-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Level', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Event', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'Message', 'the-runbook-briefings' ); ?></th><th><?php esc_html_e( 'References', 'the-runbook-briefings' ); ?></th></tr></thead><tbody><?php foreach ( $logs as $log ) : ?><tr><td><?php echo esc_html( get_date_from_gmt( $log->created_at, 'Y-m-d H:i:s' ) ); ?></td><td><span class="trb-log-<?php echo esc_attr( $log->level ); ?>"><?php echo esc_html( strtoupper( $log->level ) ); ?></span></td><td><code><?php echo esc_html( $log->event ); ?></code></td><td><?php echo esc_html( $log->message ); ?></td><td><?php echo $log->source_id ? esc_html( 'Source #' . $log->source_id ) : ''; ?> <?php echo $log->item_id ? esc_html( 'Item #' . $log->item_id ) : ''; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function review_item( int $id ): void {
        $item = $this->items->find( $id );
        if ( ! $item ) {
            wp_die( esc_html__( 'The requested briefing item does not exist.', 'the-runbook-briefings' ), 404 );
        }
        $history = $this->items->history_for( $id );
        ?>
        <div class="wrap trb-wrap"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=trb-review' ) ); ?>">&larr; <?php esc_html_e( 'Back to queue', 'the-runbook-briefings' ); ?></a></p>
            <div class="trb-review-heading"><div><h1><?php echo esc_html( $item->title ); ?></h1><p><span class="trb-status trb-status-<?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( $this->status_label( $item->status ) ); ?></span> <?php echo esc_html( $item->source_name ); ?></p></div><?php if ( $item->wp_post_id ) : ?><a class="button" href="<?php echo esc_url( get_edit_post_link( (int) $item->wp_post_id ) ); ?>"><?php esc_html_e( 'Edit linked post', 'the-runbook-briefings' ); ?></a><?php endif; ?></div>
            <?php if ( $item->error_message ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $item->error_message ); ?></p></div><?php endif; ?>
            <?php if ( $item->duplicate_of ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( /* translators: 1: item ID, 2: confidence. */ __( 'This item resembles item #%1$d with %2$d%% confidence. Reopen it only after checking the earlier story.', 'the-runbook-briefings' ), $item->duplicate_of, round( (float) $item->duplicate_confidence * 100 ) ) ); ?></p></div><?php endif; ?>
            <div class="trb-review-layout"><main>
                <section class="trb-card"><h2><?php esc_html_e( 'Source material', 'the-runbook-briefings' ); ?></h2><p><strong><?php esc_html_e( 'Original article:', 'the-runbook-briefings' ); ?></strong> <a href="<?php echo esc_url( $item->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item->title ); ?></a></p><?php if ( $item->published_at ) : ?><p><strong><?php esc_html_e( 'Published:', 'the-runbook-briefings' ); ?></strong> <?php echo esc_html( get_date_from_gmt( $item->published_at, 'F j, Y H:i' ) ); ?></p><?php endif; ?><div class="trb-excerpt"><strong><?php esc_html_e( 'Imported short excerpt', 'the-runbook-briefings' ); ?></strong><p><?php echo nl2br( esc_html( $item->excerpt ) ); ?></p></div></section>
                <form id="trb-briefing-form" class="trb-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input id="trb-form-action" type="hidden" name="action" value="trb_save_briefing"><input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $id ); ?>"><input id="trb-form-nonce" type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'trb_save_briefing_' . $id ) ); ?>">
                    <h2><?php esc_html_e( 'Editable briefing', 'the-runbook-briefings' ); ?></h2>
                    <p><label for="trb-headline"><strong><?php esc_html_e( 'Suggested headline', 'the-runbook-briefings' ); ?></strong></label><br><input class="large-text" id="trb-headline" name="briefing[suggested_headline]" type="text" value="<?php echo esc_attr( $item->suggested_headline ); ?>"></p>
                    <?php $this->editor_textarea( 'factual_summary', __( 'Factual summary', 'the-runbook-briefings' ), $item->factual_summary, __( 'Only facts supported by the source excerpt.', 'the-runbook-briefings' ) ); ?>
                    <?php $this->editor_textarea( 'why_matters', __( 'Why this matters', 'the-runbook-briefings' ), $item->why_matters, __( 'Original Runbook analysis, clearly distinct from source facts.', 'the-runbook-briefings' ) ); ?>
                    <?php $this->editor_textarea( 'practical_implications', __( 'Practical implications for site owners', 'the-runbook-briefings' ), $item->practical_implications, '' ); ?>
                    <?php $this->editor_textarea( 'internal_links', __( 'Internal-link suggestions', 'the-runbook-briefings' ), TRB_Briefing::internal_link_lines( (string) $item->internal_links ), __( 'One local link per line: Title | URL | Reason. External URLs are discarded.', 'the-runbook-briefings' ), 4 ); ?>
                    <?php $this->editor_textarea( 'editorial_notes', __( 'Private editorial notes', 'the-runbook-briefings' ), $item->editorial_notes, __( 'Notes are not included in the WordPress draft.', 'the-runbook-briefings' ), 3 ); ?>
                    <div class="trb-editor-actions">
                        <button class="button" type="submit" data-trb-action="trb_save_briefing" data-trb-nonce="<?php echo esc_attr( wp_create_nonce( 'trb_save_briefing_' . $id ) ); ?>"><?php esc_html_e( 'Save changes', 'the-runbook-briefings' ); ?></button>
                        <button class="button" type="submit" data-trb-action="trb_generate" data-trb-nonce="<?php echo esc_attr( wp_create_nonce( 'trb_generate_' . $id ) ); ?>"><?php esc_html_e( 'Generate suggestions', 'the-runbook-briefings' ); ?></button>
                        <button class="button button-primary" type="submit" data-trb-action="trb_create_draft" data-trb-nonce="<?php echo esc_attr( wp_create_nonce( 'trb_create_draft_' . $id ) ); ?>"><?php esc_html_e( 'Save as WordPress draft', 'the-runbook-briefings' ); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e( 'Nothing is published automatically. Review factual accuracy and attribution before saving.', 'the-runbook-briefings' ); ?></p>
                </form>
            </main><aside>
                <section class="trb-card"><h2><?php esc_html_e( 'Attribution preview', 'the-runbook-briefings' ); ?></h2><?php echo TRB_Briefing::attribution_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
                <section class="trb-card"><h2><?php esc_html_e( 'Queue actions', 'the-runbook-briefings' ); ?></h2><?php if ( in_array( $item->status, array( 'error', 'dismissed' ), true ) ) : ?><?php $this->item_action_form( 'trb_retry_item', $id, __( 'Return to queue', 'the-runbook-briefings' ) ); ?><?php endif; ?><?php if ( 'dismissed' !== $item->status ) : ?><?php $this->item_action_form( 'trb_dismiss_item', $id, __( 'Dismiss item', 'the-runbook-briefings' ), true ); ?><?php endif; ?></section>
                <section class="trb-card"><h2><?php esc_html_e( 'Processing history', 'the-runbook-briefings' ); ?></h2><?php if ( empty( $history ) ) : ?><p><?php esc_html_e( 'No history yet.', 'the-runbook-briefings' ); ?></p><?php else : ?><ol class="trb-history"><?php foreach ( $history as $event ) : ?><li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $event->action ) ) ); ?></strong><br><span><?php echo esc_html( get_date_from_gmt( $event->created_at, 'M j, Y H:i' ) ); ?></span><?php if ( $event->message ) : ?><p><?php echo esc_html( $event->message ); ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section>
            </aside></div>
        </div>
        <?php
    }

    private function source_action_forms( object $source ): void {
        ?><form class="trb-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_fetch_source"><input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>"><?php wp_nonce_field( 'trb_fetch_source_' . (int) $source->id ); ?><button class="button button-small" type="submit"><?php esc_html_e( 'Fetch Now', 'the-runbook-briefings' ); ?></button></form>
        <form class="trb-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_toggle_source"><input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>"><input type="hidden" name="enabled" value="<?php echo $source->enabled ? '0' : '1'; ?>"><?php wp_nonce_field( 'trb_toggle_source_' . (int) $source->id ); ?><button class="button button-small" type="submit"><?php echo $source->enabled ? esc_html__( 'Disable', 'the-runbook-briefings' ) : esc_html__( 'Enable', 'the-runbook-briefings' ); ?></button></form>
        <form class="trb-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_delete_source"><input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>"><?php wp_nonce_field( 'trb_delete_source_' . (int) $source->id ); ?><button class="button button-small button-link-delete" type="submit" data-trb-confirm="<?php esc_attr_e( 'Delete this source? Imported item attribution will remain.', 'the-runbook-briefings' ); ?>"><?php esc_html_e( 'Delete', 'the-runbook-briefings' ); ?></button></form><?php
    }

    private function source_health_html( object $source ): string {
        if ( ! $source->enabled ) {
            return '<span class="trb-health trb-health-muted">' . esc_html__( 'Disabled', 'the-runbook-briefings' ) . '</span>';
        }
        if ( $source->last_error ) {
            return '<span class="trb-health trb-health-error">' . esc_html__( 'Error', 'the-runbook-briefings' ) . '</span><br><span class="description">' . esc_html( $source->last_error ) . '</span>';
        }
        if ( $source->last_success_at ) {
            return '<span class="trb-health trb-health-ok">' . esc_html__( 'Healthy', 'the-runbook-briefings' ) . '</span>';
        }
        return '<span class="trb-health trb-health-muted">' . esc_html__( 'Not fetched', 'the-runbook-briefings' ) . '</span>';
    }

    private function item_action_form( string $action, int $id, string $label, bool $destructive = false ): void {
        ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"><input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $id ); ?>"><?php wp_nonce_field( $action . '_' . $id ); ?><button class="button <?php echo $destructive ? 'button-link-delete' : ''; ?>" type="submit"<?php if ( $destructive ) : ?> data-trb-confirm="<?php esc_attr_e( 'Dismiss this item?', 'the-runbook-briefings' ); ?>"<?php endif; ?>><?php echo esc_html( $label ); ?></button></form><?php
    }

    private function editor_textarea( string $name, string $label, string $value, string $description, int $rows = 7 ): void {
        ?><p><label for="trb-<?php echo esc_attr( $name ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br><textarea class="large-text" id="trb-<?php echo esc_attr( $name ); ?>" name="briefing[<?php echo esc_attr( $name ); ?>]" rows="<?php echo esc_attr( (string) $rows ); ?>"><?php echo esc_textarea( $value ); ?></textarea><?php if ( $description ) : ?><br><span class="description"><?php echo esc_html( $description ); ?></span><?php endif; ?></p><?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     */
    private function number_setting( string $key, string $label, array $settings, int $min, int $max ): void {
        ?><tr><th><label for="trb-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="trb-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>"></td></tr><?php
    }

    private function status_label( string $status ): string {
        $labels = array( 'new' => __( 'New', 'the-runbook-briefings' ), 'processing' => __( 'Processing', 'the-runbook-briefings' ), 'drafted' => __( 'Drafted', 'the-runbook-briefings' ), 'published' => __( 'Published', 'the-runbook-briefings' ), 'dismissed' => __( 'Dismissed', 'the-runbook-briefings' ), 'error' => __( 'Error', 'the-runbook-briefings' ) );
        return $labels[ $status ] ?? ucfirst( $status );
    }

    private function guard_manage(): void {
        if ( ! TRB_Security::can_manage() ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'the-runbook-briefings' ), 403 );
        }
    }

    private function guard_review(): void {
        if ( ! TRB_Security::can_review() ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'the-runbook-briefings' ), 403 );
        }
    }
}
