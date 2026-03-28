<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class VV_Admin {

    public static function add_menu() {
        add_menu_page(
            'Vem vare?',
            'Vem vare?',
            'manage_options',
            'vem-vare',
            array( __CLASS__, 'render_page' ),
            'dashicons-visibility',
            30
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_vem-vare' ) {
            return;
        }

        wp_enqueue_style(
            'vv-admin-style',
            VV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VV_VERSION
        );

        wp_enqueue_script(
            'vv-admin-script',
            VV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            VV_VERSION,
            true
        );

        wp_localize_script( 'vv-admin-script', 'vvData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'vv_nonce' ),
            'version' => VV_VERSION,
            'i18n'    => array(
                'loading'           => __( 'Laddar besökare...', 'vem-vare' ),
                'noVisitors'        => __( 'Inga besökare hittades.', 'vem-vare' ),
                'loadError'         => __( 'Kunde inte ladda data.', 'vem-vare' ),
                'dataRefreshed'     => __( 'Data uppdaterad', 'vem-vare' ),
                'commentSaved'      => __( 'Kommentar sparad!', 'vem-vare' ),
                'commentSaveError'  => __( 'Kunde inte spara: ', 'vem-vare' ),
                'visitorDeleted'    => __( 'Besökare borttagen', 'vem-vare' ),
                'deleteError'       => __( 'Kunde inte ta bort', 'vem-vare' ),
                'confirmDelete'     => __( 'Är du säker på att du vill ta bort denna besökare?', 'vem-vare' ),
                'csvExported'       => __( 'CSV exporterad!', 'vem-vare' ),
                'addComment'        => __( '+ Lägg till', 'vem-vare' ),
                'visitors'          => __( 'besökare', 'vem-vare' ),
                'justNow'           => __( 'Just nu', 'vem-vare' ),
                'minAgo'            => __( 'min sedan', 'vem-vare' ),
                'hoursAgo'          => __( 'tim sedan', 'vem-vare' ),
                'location'          => __( 'Plats', 'vem-vare' ),
                'visitsCount'       => __( 'Besök', 'vem-vare' ),
                'allCountries'      => __( 'Alla länder', 'vem-vare' ),
                'topCountries'      => __( 'Topp länder', 'vem-vare' ),
                'showAll'           => __( 'Visa alla', 'vem-vare' ),
            ),
        ) );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Åtkomst nekad.', 'vem-vare' ) );
        }
        ?>
        <div id="vv-app" class="vv-wrap">
            <!-- Header -->
            <header class="vv-header">
                <div class="vv-header-left">
                    <div class="vv-logo">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="8" fill="#820500"/>
                            <path d="M8 10L16 22L24 10" stroke="#FF7DFA" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="16" cy="12" r="3" fill="#FF2D2D"/>
                        </svg>
                        <h1>Vem vare<span>?</span></h1>
                    </div>
                    <span class="vv-version">v<?php echo esc_html( VV_VERSION ); ?></span>
                </div>
                <div class="vv-header-right">
                    <div class="vv-search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="vv-search" placeholder="<?php echo esc_attr__( 'Sök IP, DNS, stad, land...', 'vem-vare' ); ?>">
                    </div>
                    <button class="vv-btn vv-btn-secondary" id="vv-export-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <?php esc_html_e( 'Exportera CSV', 'vem-vare' ); ?>
                    </button>
                    <button class="vv-btn vv-btn-primary" id="vv-refresh-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        <?php esc_html_e( 'Uppdatera', 'vem-vare' ); ?>
                    </button>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="vv-stats" id="vv-stats">
                <div class="vv-stat-card">
                    <div class="vv-stat-icon vv-stat-icon--visitors">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="vv-stat-content">
                        <span class="vv-stat-number" id="vv-stat-total">&mdash;</span>
                        <span class="vv-stat-label"><?php esc_html_e( 'Totalt besökare', 'vem-vare' ); ?></span>
                    </div>
                </div>
                <div class="vv-stat-card">
                    <div class="vv-stat-icon vv-stat-icon--today">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="vv-stat-content">
                        <span class="vv-stat-number" id="vv-stat-today">&mdash;</span>
                        <span class="vv-stat-label"><?php esc_html_e( 'Idag', 'vem-vare' ); ?></span>
                    </div>
                </div>
                <div class="vv-stat-card">
                    <div class="vv-stat-icon vv-stat-icon--countries">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <div class="vv-stat-content">
                        <span class="vv-stat-number" id="vv-stat-countries">&mdash;</span>
                        <span class="vv-stat-label"><?php esc_html_e( 'Länder', 'vem-vare' ); ?></span>
                    </div>
                </div>
                <div class="vv-stat-card">
                    <div class="vv-stat-icon vv-stat-icon--comments">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="vv-stat-content">
                        <span class="vv-stat-number" id="vv-stat-comments">&mdash;</span>
                        <span class="vv-stat-label"><?php esc_html_e( 'Kommentarer', 'vem-vare' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Country Top Bar -->
            <div class="vv-country-bar" id="vv-country-bar">
                <div class="vv-country-bar-header">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <?php esc_html_e( 'Besökare per land', 'vem-vare' ); ?>
                    </h3>
                    <button class="vv-country-toggle" id="vv-country-toggle">
                        <?php esc_html_e( 'Visa alla', 'vem-vare' ); ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="vv-country-chips" id="vv-country-chips"></div>
            </div>

            <!-- Filters -->
            <div class="vv-filters">
                <div class="vv-filter-group">
                    <label><?php esc_html_e( 'Land:', 'vem-vare' ); ?></label>
                    <select id="vv-filter-country">
                        <option value=""><?php esc_html_e( 'Alla länder', 'vem-vare' ); ?></option>
                    </select>
                </div>
                <div class="vv-filter-group">
                    <label><?php esc_html_e( 'Period:', 'vem-vare' ); ?></label>
                    <select id="vv-filter-period">
                        <option value=""><?php esc_html_e( 'Alla', 'vem-vare' ); ?></option>
                        <option value="today"><?php esc_html_e( 'Idag', 'vem-vare' ); ?></option>
                        <option value="week"><?php esc_html_e( 'Senaste 7 dagarna', 'vem-vare' ); ?></option>
                        <option value="month"><?php esc_html_e( 'Senaste 30 dagarna', 'vem-vare' ); ?></option>
                    </select>
                </div>
                <div class="vv-filter-group">
                    <label><?php esc_html_e( 'Kommentar:', 'vem-vare' ); ?></label>
                    <select id="vv-filter-comment">
                        <option value=""><?php esc_html_e( 'Alla', 'vem-vare' ); ?></option>
                        <option value="with"><?php esc_html_e( 'Med kommentar', 'vem-vare' ); ?></option>
                        <option value="without"><?php esc_html_e( 'Utan kommentar', 'vem-vare' ); ?></option>
                    </select>
                </div>
                <div class="vv-filter-group vv-filter-sort">
                    <label><?php esc_html_e( 'Sortera:', 'vem-vare' ); ?></label>
                    <select id="vv-filter-sort">
                        <option value="last_visit"><?php esc_html_e( 'Senaste besök', 'vem-vare' ); ?></option>
                        <option value="first_visit"><?php esc_html_e( 'Första besök', 'vem-vare' ); ?></option>
                        <option value="visit_count"><?php esc_html_e( 'Flest besök', 'vem-vare' ); ?></option>
                        <option value="ip_address"><?php esc_html_e( 'IP-adress', 'vem-vare' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="vv-table-container">
                <table class="vv-table" id="vv-table">
                    <thead>
                        <tr>
                            <th class="vv-th-flag"></th>
                            <th><?php esc_html_e( 'IP-adress', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Reverse DNS', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Organisation', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Stad', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Land', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Besök', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Senaste besök', 'vem-vare' ); ?></th>
                            <th><?php esc_html_e( 'Kommentar', 'vem-vare' ); ?></th>
                            <th class="vv-th-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="vv-tbody">
                        <tr>
                            <td colspan="10" class="vv-loading">
                                <div class="vv-spinner"></div>
                                <span><?php esc_html_e( 'Laddar besökare...', 'vem-vare' ); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="vv-pagination" id="vv-pagination"></div>

            <!-- Comment Modal -->
            <div class="vv-modal-overlay" id="vv-modal" style="display:none;">
                <div class="vv-modal">
                    <div class="vv-modal-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?php esc_html_e( 'Kommentar', 'vem-vare' ); ?>
                        </h3>
                        <button class="vv-modal-close" id="vv-modal-close">&times;</button>
                    </div>
                    <div class="vv-modal-body">
                        <div class="vv-modal-info" id="vv-modal-info"></div>
                        <textarea id="vv-comment-text" rows="4" placeholder="<?php echo esc_attr__( 'Skriv en kommentar om denna besökare...', 'vem-vare' ); ?>"></textarea>
                        <input type="hidden" id="vv-comment-id">
                    </div>
                    <div class="vv-modal-footer">
                        <button class="vv-btn vv-btn-secondary" id="vv-modal-cancel"><?php esc_html_e( 'Avbryt', 'vem-vare' ); ?></button>
                        <button class="vv-btn vv-btn-primary" id="vv-modal-save"><?php esc_html_e( 'Spara kommentar', 'vem-vare' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="vv-footer">
                <span>Vem vare? v<?php echo esc_html( VV_VERSION ); ?> &mdash; <?php esc_html_e( 'Besökarspårning för WordPress', 'vem-vare' ); ?></span>
            </footer>
        </div>
        <?php
    }
}
