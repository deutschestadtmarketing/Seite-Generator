<?php
/**
 * Admin Handler Klasse
 * 
 * Diese Klasse verwaltet das komplette Backend-Interface des Plugins:
 * - Registriert alle Plugin-Einstellungen in WordPress
 * - Rendert die verschiedenen Admin-Tabs (Generator, Übersicht, KI, Einstellungen, Hilfe)
 * - Sanitisiert alle Benutzereingaben für Sicherheit
 * - Verwaltet die Platzhalter-Konfiguration
 * - Stellt die Verbindung zwischen Frontend und Backend her
 * 
 * Hinweis: Reiner Lesbarkeits-Refactor – Verhalten unverändert.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Admin_Handler {

    /**
     * Anleitung Handler Instanz
     * 
     * @var CPG_Anleitung_Handler
     */
    private $anleitung_handler;

    /**
     * Konstruktor - registriert WordPress Hooks für Settings
     */
    public function __construct() {
        // WordPress Settings API verwenden für sichere Einstellungsverwaltung
        add_action('admin_init', [$this, 'registerSettings']);
        
        // Anleitung Handler initialisieren
        $this->anleitung_handler = new CPG_Anleitung_Handler();
    }

    /**
     * Registriert Plugin-Einstellungen - verbindet alle Plugin-Optionen mit WordPress
     * 
     * Diese Methode registriert alle Plugin-Einstellungen in WordPress:
     * - Page Builder Auswahl (Elementor/Bricks)
     * - API-Konfiguration (GeoNames, Gemini)
     * - Cache-Einstellungen
     * - Platzhalter-Konfiguration
     * - KI-Einstellungen
     */
    public function registerSettings() {
        // Option Group registrieren
        add_settings_section(
            'cpg_settings_section',
            __('City Pages Generator Einstellungen', 'city-pages-generator'),
            '__return_false',
            'cpg_settings'
        );

        // Einzelne Settings registrieren
        register_setting(
            'cpg_settings',
            'cpg_selected_builder',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        register_setting(
            'cpg_settings',
            'cpg_geonames_username',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        register_setting(
            'cpg_settings',
            'cpg_api_cache_time',
            [
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 3600
            ]
        );


        register_setting(
            'cpg_settings',
            'cpg_custom_placeholders',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeCustomPlaceholders'],
                'default' => []
            ]
        );

        register_setting(
            'cpg_settings',
            'cpg_custom_master_prompt',
            [
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => ''
            ]
        );

        // Gemini API Einstellungen
        register_setting(
            'cpg_settings',
            'cpg_gemini_api_key',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        register_setting(
            'cpg_settings',
            'cpg_gemini_cache_time',
            [
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 3600
            ]
        );

        register_setting(
            'cpg_settings',
            'cpg_gemini_enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            ]
        );

        // Einfache Platzhalter für KI-Textgenerierung (eigene Settings-Gruppe)
        register_setting(
            'cpg_simple_ph_settings',
            'cpg_simple_placeholders',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSimplePlaceholders'],
                'default' => []
            ]
        );

        // Einfache Platzhalter ohne KI (eigene Settings-Gruppe)
        add_settings_section(
            'cpg_simple_ph_no_ki_section',
            __('Einfache Platzhalter ohne KI', 'city-pages-generator'),
            '__return_false',
            'cpg_simple_ph_no_ki_settings'
        );

        register_setting(
            'cpg_simple_ph_no_ki_settings',
            'cpg_simple_placeholders_no_ki',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSimplePlaceholdersNoKi'],
                'default' => []
            ]
        );
    }

    /**
     * Rendert die Admin-Seite
     */
    public function render() {
        $active_tab = $_GET['tab'] ?? 'generator';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Stadtseiten Generator', 'city-pages-generator'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=city-pages-generator&tab=generator" 
                   class="nav-tab <?php echo $active_tab === 'generator' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Generator', 'city-pages-generator'); ?>
                </a>
                <a href="?page=city-pages-generator&tab=overview" 
                   class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Übersicht', 'city-pages-generator'); ?>
                </a>
                <a href="?page=city-pages-generator&tab=ki" 
                   class="nav-tab <?php echo $active_tab === 'ki' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('KI', 'city-pages-generator'); ?>
                </a>
                <a href="?page=city-pages-generator&tab=settings" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Einstellungen', 'city-pages-generator'); ?>
                </a>
                <a href="?page=city-pages-generator&tab=help" 
                   class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Hilfe & Platzhalter', 'city-pages-generator'); ?>
                </a>
                <a href="?page=city-pages-generator&tab=anleitung" 
                   class="nav-tab <?php echo $active_tab === 'anleitung' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('📖 Anleitung', 'city-pages-generator'); ?>
                </a>
            </nav>

            <div class="cpg-tab-content">
                <?php
                switch ($active_tab) {
                    case 'generator':
                        $this->renderGeneratorTab();
                        break;
                    case 'overview':
                        $this->renderOverviewTab();
                        break;
                    case 'ki':
                        $this->renderKITab();
                        break;
                    case 'settings':
                        $this->renderSettingsTab();
                        break;
                    case 'help':
                        $this->renderHelpTab();
                        break;
                    case 'anleitung':
                        $this->renderAnleitungTab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Generator-Tab
     */
    private function renderGeneratorTab() {
        ?>
        <div class="cpg-generator-container">
            <form id="cpg-generator-form" class="cpg-form">
                <?php wp_nonce_field('cpg_nonce', 'cpg_nonce'); ?>
                
                <!-- Quellseite auswählen -->
                <div class="cpg-form-section">
                    <h3><?php _e('Quellseite auswählen', 'city-pages-generator'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="source_page"><?php _e('Quellseite', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <select id="source_page" name="source_page" class="regular-text" required>
                                    <option value=""><?php _e('Seite auswählen...', 'city-pages-generator'); ?></option>
                                    <?php $this->renderBuilderPagesOptions(); ?>
                                </select>
                                <p class="description"><?php _e('Wählen Sie die Seite, die als Vorlage dienen soll.', 'city-pages-generator'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Standort & Radius -->
                <div class="cpg-form-section">
                    <h3><?php _e('Standort & Radius', 'city-pages-generator'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="base_location"><?php _e('Ausgangsort', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="base_location" name="base_location" class="regular-text" 
                                       placeholder="Krefeld" required>
                                <p class="description"><?php _e('Geben Sie den Ausgangsort ein (z.B. Krefeld)', 'city-pages-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="radius"><?php _e('Radius (km)', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="radius" name="radius" class="small-text" 
                                       value="50" min="1" max="500" required>
                                <p class="description"><?php _e('Radius in Kilometern', 'city-pages-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="min_population"><?php _e('Mindesteinwohner', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="min_population" name="min_population" class="regular-text" 
                                       value="50000" min="0" step="1" required>
                                <p class="description"><?php _e('Mindestanzahl Einwohner pro Stadt', 'city-pages-generator'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="cpg-load-cities" class="button button-secondary">
                            <?php _e('Städte laden', 'city-pages-generator'); ?>
                        </button>
                    </p>
                </div>

                <!-- Städte-Auswahl -->
                <div class="cpg-form-section" id="cpg-cities-section" style="display: none;">
                    <h3><?php _e('Gefundene Städte', 'city-pages-generator'); ?></h3>
                    <div id="cpg-cities-list"></div>
                    
                    <!-- Batch-Einstellungen (nur anzeigen wenn Städte ausgewählt) -->
                    <div id="cpg-batch-settings" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <h4 style="margin-top: 0; color: #495057;">⚙️ Batch-Verarbeitung Einstellungen</h4>
                        <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">Passen Sie die Batch-Verarbeitung an Ihre Server-Kapazität an:</p>
                        
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row" style="width: 200px; padding: 8px 0;">
                                    <label for="batch_size"><?php _e('Seiten pro Batch', 'city-pages-generator'); ?></label>
                                </th>
                                <td style="padding: 8px 0;">
                                    <input type="number" id="batch_size" name="batch_size" class="small-text" 
                                           value="15" min="1" max="50" style="width: 80px;">
                                    <p class="description" style="margin: 5px 0 0 0; font-size: 12px;">
                                        <?php _e('Anzahl Seiten pro Batch (1-50). Niedrigere Werte = weniger Server-Last', 'city-pages-generator'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="width: 200px; padding: 8px 0;">
                                    <label for="batch_delay"><?php _e('Pause zwischen Batches', 'city-pages-generator'); ?></label>
                                </th>
                                <td style="padding: 8px 0;">
                                    <input type="number" id="batch_delay" name="batch_delay" class="small-text" 
                                           value="2" min="0" max="30" style="width: 80px;">
                                    <span style="margin-left: 5px; color: #6c757d;">Sekunden</span>
                                    <p class="description" style="margin: 5px 0 0 0; font-size: 12px;">
                                        <?php _e('Wartezeit zwischen Batches (0-30 Sekunden). Längere Pausen = schonender für Server', 'city-pages-generator'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 4px; border-left: 4px solid #2196f3;">
                            <strong style="color: #1976d2;">💡 Empfehlung:</strong>
                            <span style="color: #424242; font-size: 13px;">
                                Für 200+ Seiten: 10-15 Seiten pro Batch, 2-3 Sekunden Pause
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Textanpassung -->
                <div class="cpg-form-section">
                    <h3><?php _e('Textanpassung', 'city-pages-generator'); ?></h3>
                    <table class="form-table">
                        
                        <tr>
                            <th scope="row">
                                <label for="page_title_pattern"><?php _e('Seitentitel-Schema', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="page_title_pattern" name="page_title_pattern" class="regular-text" 
                                       value="{{thema}} in {{stadt}}" required
                                       placeholder="z.B. {{thema}} in {{stadt}} - {{bundesland}}">
                                <p class="description">
                                    <?php _e('Schema für die Seitentitel. Verwenden Sie Platzhalter wie {{thema}}, {{stadt}}, {{bundesland}}', 'city-pages-generator'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="slug_pattern"><?php _e('URL-Slug Schema', 'city-pages-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="slug_pattern" name="slug_pattern" class="regular-text" 
                                       value="{{thema}}-{{stadt}}" required
                                       placeholder="z.B. {{thema}}-{{stadt}}">
                                <p class="description">
                                    <?php _e('Schema für die URL-Slugs. Verwenden Sie Platzhalter wie {{thema}}, {{stadt}}, {{bundesland}}', 'city-pages-generator'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                </div>

                <!-- Server-Überlastungsschutz -->
                <div class="cpg-form-section" id="cpg-server-protection" style="display: none;">
                    <div class="cpg-server-protection">
                        <h4><?php _e('⚠️ Server-Überlastungsschutz aktiviert', 'city-pages-generator'); ?></h4>
                        <p><?php _e('Bei vielen Städten wird automatisch eine Batch-Verarbeitung gestartet, um Server-Überlastung zu vermeiden:', 'city-pages-generator'); ?></p>
                        <ul>
                            <li><?php _e('Seiten werden in kleinen Gruppen (15 pro Batch) erstellt', 'city-pages-generator'); ?></li>
                            <li><?php _e('Pausen zwischen den Batches entlasten den Server', 'city-pages-generator'); ?></li>
                            <li><?php _e('Fortschritt wird kontinuierlich angezeigt', 'city-pages-generator'); ?></li>
                            <li><?php _e('Bei Fehlern wird automatisch wiederholt', 'city-pages-generator'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Generierung starten -->
                <div class="cpg-form-section">
                    <p class="submit">
                        <button type="submit" id="cpg-generate" class="button button-primary" disabled>
                            <?php _e('Seiten generieren', 'city-pages-generator'); ?>
                        </button>
                    </p>
                </div>
            </form>

            <!-- Fortschrittsanzeige -->
            <div id="cpg-progress" class="cpg-progress" style="display: none;">
                <div class="cpg-progress-bar">
                    <div class="cpg-progress-fill"></div>
                </div>
                <div class="cpg-progress-text"></div>
            </div>

            <!-- Ergebnisse -->
            <div id="cpg-results" class="cpg-results" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Rendert den Übersicht-Tab
     */
    private function renderOverviewTab() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpg_generated_pages';
        
        // Prüfen ob alle Seiten angezeigt werden sollen
        $show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
        $time_filter = $show_all ? '' : 'AND p.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)';
        
        // Erst aus Tracking-Tabelle versuchen
        $pages = $wpdb->get_results("
            SELECT p.*, post.post_title, post.post_status, post.post_name
            FROM $table_name p
            LEFT JOIN {$wpdb->posts} post ON p.page_id = post.ID
            WHERE post.ID IS NOT NULL
            $time_filter
            ORDER BY p.created_at DESC
            LIMIT 500
        ");
        
        // Falls Tracking-Tabelle leer ist, recent generated pages suchen
        if (empty($pages)) {
            $time_filter_fallback = $show_all ? '' : 'AND post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)';
            $recent_pages = $wpdb->get_results("
                SELECT ID as page_id, post_title, post_status, post_name as slug, post_date as created_at,
                       'N/A' as city_name, 'N/A' as state, post_title as source_page_id
                FROM {$wpdb->posts}
                WHERE post_type = 'page' 
                $time_filter_fallback
                AND post_status IN ('draft', 'publish')
                ORDER BY post_date DESC
                LIMIT 200
            ");
            
            if (!empty($recent_pages)) {
                $time_info = $show_all ? 'alle' : 'kürzlich erstellte';
                echo '<div class="notice notice-info"><p><strong>Hinweis:</strong> ' . $time_info . ' Seiten gefunden (möglicherweise durch das Plugin generiert):</p></div>';
                $pages = $recent_pages;
            }
        }
        ?>
        <div class="cpg-overview-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">
                    <?php
                        $count_pages = is_array($pages) ? count($pages) : (is_countable($pages) ? count($pages) : 0);
                        printf(
                            /* translators: %d = count of pages */
                            esc_html__('Generierte Seiten (%d)', 'city-pages-generator'),
                            intval($count_pages)
                        );
                    ?>
                </h3>
            </div>
            
            <?php if (empty($pages)): ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Noch keine Seiten generiert.', 'city-pages-generator'); ?></strong></p>
                    <p><?php _e('Bitte generieren Sie Seiten im Tab „Generator“.', 'city-pages-generator'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong><?php echo count($pages); ?> Seiten gefunden</strong> 
                    <?php if ($show_all): ?>
                        (alle Seiten)
                    <?php else: ?>
                        (letzte 30 Tage)
                    <?php endif; ?>
                    - Letzte Aktualisierung: <?php echo date_i18n('d.m.Y H:i'); ?></p>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Seite', 'city-pages-generator'); ?></th>
                            <th><?php _e('URL', 'city-pages-generator'); ?></th>
                            <th><?php _e('Status', 'city-pages-generator'); ?></th>
                            <th><?php _e('Erstellt', 'city-pages-generator'); ?></th>
                            <th><?php _e('Aktionen', 'city-pages-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo get_edit_post_link($page->page_id); ?>">
                                            <?php echo esc_html($page->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><code><?php echo esc_url(get_permalink($page->page_id)); ?></code></td>
                                <td>
                                    <span class="cpg-status cpg-status-<?php echo esc_attr($page->post_status); ?>">
                                        <?php echo $page->post_status === 'publish' ? __('Veröffentlicht', 'city-pages-generator') : __('Entwurf', 'city-pages-generator'); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($page->created_at)); ?></td>
                                <td>
                                    <div class="cpg-actions">
                                        <a href="<?php echo get_permalink($page->page_id); ?>" 
                                           class="button button-small" target="_blank">
                                            <?php _e('Vorschau', 'city-pages-generator'); ?>
                                        </a>
                                        
                                        <?php if ($page->post_status === 'draft'): ?>
                                            <button type="button" 
                                                    class="button button-small button-primary cpg-publish-page" 
                                                    data-page-id="<?php echo $page->page_id; ?>">
                                                <?php _e('Veröffentlichen', 'city-pages-generator'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="button button-small cpg-unpublish-page" 
                                                    data-page-id="<?php echo $page->page_id; ?>">
                                                <?php _e('Entwurf', 'city-pages-generator'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                class="button button-small button-link-delete cpg-delete-page" 
                                                data-page-id="<?php echo $page->page_id; ?>">
                                            <?php _e('Löschen', 'city-pages-generator'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Debug-Informationen -->
                <?php if (WP_DEBUG): ?>
                <div class="notice notice-warning" style="margin-top: 20px;">
                    <p><strong>Debug-Info:</strong></p>
                    <ul>
                        <li>Datenbank-Tabelle: <?php echo $table_name; ?></li>
                        <li>Gefundene Einträge: <?php echo count($pages); ?></li>
                        <li>Letzte Aktualisierung: <?php echo date_i18n('d.m.Y H:i:s'); ?></li>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rendert den Einstellungen-Tab
     */
    private function renderSettingsTab() {
        ?>
        <div class="cpg-settings-container">
            <form method="post" action="options.php">
                <?php
                settings_fields('cpg_settings');
                do_settings_sections('cpg_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cpg_selected_builder"><?php _e('Page Builder', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <select id="cpg_selected_builder" name="cpg_selected_builder" class="regular-text" required>
                                <option value=""><?php _e('Builder auswählen...', 'city-pages-generator'); ?></option>
                                <option value="elementor" <?php selected(get_option('cpg_selected_builder'), 'elementor'); ?>>
                                    Elementor <?php echo is_plugin_active('elementor/elementor.php') ? '✓' : '✗'; ?>
                                </option>
                                <option value="bricks" <?php selected(get_option('cpg_selected_builder'), 'bricks'); ?>>
                                    Bricks Builder <?php echo defined('BRICKS_VERSION') ? '✓' : '✗'; ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Wählen Sie den Page Builder, den Sie verwenden möchten. ✓ = installiert, ✗ = nicht installiert', 'city-pages-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpg_geonames_username"><?php _e('GeoNames Username', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cpg_geonames_username" name="cpg_geonames_username" 
                                   class="regular-text" value="<?php echo esc_attr(get_option('cpg_geonames_username', '')); ?>">
                            <p class="description">
                                <?php _e('Ihr GeoNames API Username. Kostenlos registrieren auf geonames.org', 'city-pages-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpg_api_cache_time"><?php _e('API Cache Zeit', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cpg_api_cache_time" name="cpg_api_cache_time" 
                                   class="small-text" value="<?php echo esc_attr(get_option('cpg_api_cache_time', 3600)); ?>" min="300">
                            <span><?php _e('Sekunden', 'city-pages-generator'); ?></span>
                            <p class="description"><?php _e('Wie lange API-Ergebnisse gecacht werden sollen', 'city-pages-generator'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e('Gemini KI-Integration', 'city-pages-generator'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cpg_gemini_enabled"><?php _e('Gemini KI aktivieren', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="cpg_gemini_enabled" name="cpg_gemini_enabled" 
                                   value="1" <?php checked(get_option('cpg_gemini_enabled', false)); ?>>
                            <label for="cpg_gemini_enabled"><?php _e('KI-Textgenerierung für bessere Inhalte aktivieren', 'city-pages-generator'); ?></label>
                            <p class="description"><?php _e('Generiert automatisch SEO-optimierte Meta-Beschreibungen, Keywords und Texte', 'city-pages-generator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpg_gemini_api_key"><?php _e('Gemini API-Key', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="cpg_gemini_api_key" name="cpg_gemini_api_key" 
                                   class="regular-text" value="<?php echo esc_attr(get_option('cpg_gemini_api_key', '')); ?>">
                            <button type="button" id="cpg-check-gemini" class="button" style="margin-left:10px;">
                                <?php _e('Verbindung prüfen', 'city-pages-generator'); ?>
                            </button>
                            <span id="cpg-gemini-status-text" style="margin-left:8px; color:#6b7280; font-size:12px;">
                                <?php _e('Nicht geprüft', 'city-pages-generator'); ?>
                            </span>
                            <p class="description">
                                <?php _e('Ihr Google Gemini API-Key. Kostenlos bei', 'city-pages-generator'); ?> 
                                <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 
                                <?php _e('erstellen', 'city-pages-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpg_gemini_cache_time"><?php _e('KI Cache Zeit', 'city-pages-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cpg_gemini_cache_time" name="cpg_gemini_cache_time" 
                                   class="small-text" value="<?php echo esc_attr(get_option('cpg_gemini_cache_time', 3600)); ?>" min="300">
                            <span><?php _e('Sekunden', 'city-pages-generator'); ?></span>
                            <p class="description"><?php _e('Wie lange KI-generierte Inhalte gecacht werden sollen', 'city-pages-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
        
            
            <div class="cpg-help">
                <h4><?php _e('API-Konfiguration', 'city-pages-generator'); ?></h4>
                <p><?php _e('Für bessere Ergebnisse empfehlen wir die Nutzung der GeoNames API:', 'city-pages-generator'); ?></p>
                <ol>
                    <li><?php _e('Kostenlosen Account auf', 'city-pages-generator'); ?> <a href="http://www.geonames.org/login" target="_blank">geonames.org</a> <?php _e('erstellen', 'city-pages-generator'); ?></li>
                    <li><?php _e('Username oben eingeben', 'city-pages-generator'); ?></li>
                    <li><?php _e('Einstellungen speichern', 'city-pages-generator'); ?></li>
                </ol>
                <p><?php _e('Ohne GeoNames-Account wird automatisch die OpenStreetMap Nominatim API verwendet (begrenzte Funktionen).', 'city-pages-generator'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert die Builder-Seiten als Optionen
     */
    private function renderBuilderPagesOptions() {
        $selected_builder = get_option('cpg_selected_builder', '');
        
        if (empty($selected_builder)) {
            echo '<option value="" disabled>' . __('Bitte wählen Sie zuerst einen Builder in den Einstellungen', 'city-pages-generator') . '</option>';
            return;
        }
        
        if ($selected_builder === 'elementor') {
            $this->renderElementorPagesOptions();
        } elseif ($selected_builder === 'bricks') {
            $this->renderBricksPagesOptions();
        }
    }

    /**
     * Rendert die Elementor-Seiten als Optionen
     */
    private function renderElementorPagesOptions() {
        // Alle Seiten und Beiträge mit Elementor
        $pages = get_posts([
            'post_type' => ['page', 'post'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_elementor_edit_mode',
                    'value' => 'builder',
                    'compare' => '='
                ]
            ],
            'post_status' => ['publish', 'draft', 'private', 'pending']
        ]);

        // Gruppierung nach Post-Typ
        $grouped_pages = [];
        foreach ($pages as $page) {
            $grouped_pages[$page->post_type][] = $page;
        }

        // Seiten anzeigen
        if (!empty($grouped_pages['page'])) {
            echo '<optgroup label="' . __('Seiten', 'city-pages-generator') . '">';
            foreach ($grouped_pages['page'] as $page) {
                $status_label = $this->getPostStatusLabel($page->post_status);
                printf(
                    '<option value="%d">%s (%s)</option>',
                    $page->ID,
                    esc_html($page->post_title),
                    $status_label
                );
            }
            echo '</optgroup>';
        }

        // Beiträge anzeigen
        if (!empty($grouped_pages['post'])) {
            echo '<optgroup label="' . __('Beiträge', 'city-pages-generator') . '">';
            foreach ($grouped_pages['post'] as $page) {
                $status_label = $this->getPostStatusLabel($page->post_status);
                printf(
                    '<option value="%d">%s (%s)</option>',
                    $page->ID,
                    esc_html($page->post_title),
                    $status_label
                );
            }
            echo '</optgroup>';
        }
    }

    /**
     * Rendert die Bricks-Seiten als Optionen
     */
    private function renderBricksPagesOptions() {
        if (!defined('BRICKS_DB_PAGE_CONTENT')) {
            echo '<option value="" disabled>' . __('Bricks Builder ist nicht installiert', 'city-pages-generator') . '</option>';
            return;
        }

        // Alle Seiten und Beiträge mit Bricks
        $pages = get_posts([
            'post_type' => ['page', 'post'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => BRICKS_DB_PAGE_CONTENT,
                    'compare' => 'EXISTS'
                ]
            ],
            'post_status' => ['publish', 'draft', 'private', 'pending']
        ]);

        // Gruppierung nach Post-Typ
        $grouped_pages = [];
        foreach ($pages as $page) {
            $grouped_pages[$page->post_type][] = $page;
        }

        // Seiten anzeigen
        if (!empty($grouped_pages['page'])) {
            echo '<optgroup label="' . __('Seiten', 'city-pages-generator') . '">';
            foreach ($grouped_pages['page'] as $page) {
                $status_label = $this->getPostStatusLabel($page->post_status);
                printf(
                    '<option value="%d">%s (%s)</option>',
                    $page->ID,
                    esc_html($page->post_title),
                    $status_label
                );
            }
            echo '</optgroup>';
        }

        // Beiträge anzeigen
        if (!empty($grouped_pages['post'])) {
            echo '<optgroup label="' . __('Beiträge', 'city-pages-generator') . '">';
            foreach ($grouped_pages['post'] as $page) {
                $status_label = $this->getPostStatusLabel($page->post_status);
                printf(
                    '<option value="%d">%s (%s)</option>',
                    $page->ID,
                    esc_html($page->post_title),
                    $status_label
                );
            }
            echo '</optgroup>';
        }
    }

    /**
     * Gibt ein lesbares Label für den Post-Status zurück
     * @param string $status
     * @return string
     */
    private function getPostStatusLabel($status) {
        $labels = [
            'publish' => __('Veröffentlicht', 'city-pages-generator'),
            'draft' => __('Entwurf', 'city-pages-generator'),
            'private' => __('Privat', 'city-pages-generator'),
            'pending' => __('Ausstehend', 'city-pages-generator'),
            'trash' => __('Papierkorb', 'city-pages-generator')
        ];
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    /**
     * Sanitize Custom Placeholders
     * @param array $input
     * @return array
     */
    public function sanitizeCustomPlaceholders($input) {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $placeholder) {
            if (!is_array($placeholder)) {
                continue;
            }

            $name = sanitize_text_field($placeholder['name'] ?? '');
            $prompt = sanitize_textarea_field($placeholder['prompt'] ?? '');

            // Nur hinzufügen wenn beide Felder ausgefüllt sind
            if (!empty($name) && !empty($prompt)) {
                // Platzhalter-Name validieren (nur Buchstaben, Zahlen, Unterstriche)
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
                
                if (!empty($name)) {
                    $sanitized[] = [
                        'name' => $name,
                        'prompt' => $prompt
                    ];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize Simple Placeholders
     * @param array $input
     * @return array
     */
    public function sanitizeSimplePlaceholders($input) {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $placeholder) {
            if (!is_array($placeholder)) {
                continue;
            }

            $name = sanitize_text_field($placeholder['name'] ?? '');
            $text = wp_kses_post($placeholder['text'] ?? '');

            // Nur hinzufügen wenn beide Felder ausgefüllt sind
            if (!empty($name) && !empty($text)) {
                // Platzhalter-Name validieren (nur Buchstaben, Zahlen, Unterstriche)
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
                if (!empty($name)) {
                    $sanitized[] = [
                        'name' => $name,
                        'text' => $text
                    ];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize einfache Platzhalter ohne KI
     */
    public function sanitizeSimplePlaceholdersNoKi($input) {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $placeholder) {
            if (!is_array($placeholder)) {
                continue;
            }

            $name = sanitize_text_field($placeholder['name'] ?? '');
            $text = wp_kses_post($placeholder['text'] ?? '');

            // Nur hinzufügen wenn beide Felder ausgefüllt sind
            if (!empty($name) && !empty($text)) {
                // Platzhalter-Name validieren (nur Buchstaben, Zahlen, Unterstriche)
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
                if (!empty($name)) {
                    $sanitized[] = [
                        'name' => $name,
                        'text' => $text
                    ];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Rendert den Hilfe-Tab
     */
    private function renderHelpTab() {
        $simple_placeholders = get_option('cpg_simple_placeholders_no_ki', []);
        $rows = is_array($simple_placeholders) ? $simple_placeholders : [];
        ?>
        <div class="wrap">
            <h2><?php _e('Hilfe & Platzhalter', 'city-pages-generator'); ?></h2>
            
            <div class="card">
                <h3><?php _e('Einfache Platzhalter (ohne KI)', 'city-pages-generator'); ?></h3>
                <p><?php _e('Definiere einfache Platzhalter, die direkt durch den eingegebenen Text ersetzt werden (ohne KI-Adaptierung).', 'city-pages-generator'); ?></p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('cpg_simple_ph_no_ki_settings'); ?>
                    
                    <div style="margin:10px 0; display:flex; gap:8px;">
                        <button type="button" class="button cpg-expand-editors" data-target="#cpg-simple-ph-no-ki-table">Alle ausklappen</button>
                        <button type="button" class="button cpg-collapse-editors" data-target="#cpg-simple-ph-no-ki-table">Alle einklappen</button>
                    </div>
                    <table class="form-table" id="cpg-simple-ph-no-ki-table">
                        <thead>
                            <tr>
                                <th style="width:200px;"><?php _e('Platzhalter ({{name}})', 'city-pages-generator'); ?></th>
                                <th><?php _e('Ersetzungstext', 'city-pages-generator'); ?></th>
                                <th style="width:120px;">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody id="cpg-simple-ph-no-ki-tbody">
                            <?php if (empty($rows)) : ?>
                                <tr>
                                    <td><input type="text" name="cpg_simple_placeholders_no_ki[0][name]" class="regular-text" placeholder="z.B. angebot"></td>
                                    <td>
                                        <?php
                                        $editor_id = 'cpg_simple_placeholders_no_ki_0_text';
                                        wp_editor('', $editor_id, [
                                            'textarea_name' => 'cpg_simple_placeholders_no_ki[0][text]',
                                            'media_buttons' => true,
                                            'textarea_rows' => 5,
                                            'tinymce' => true,
                                            'quicktags' => true,
                                        ]);
                                        ?>
                                    </td>
                                    <td><button type="button" class="button cpg-add-simple-ph-no-ki">+</button></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($rows as $i => $row) : ?>
                                    <tr>
                                        <td><input type="text" name="cpg_simple_placeholders_no_ki[<?php echo intval($i); ?>][name]" class="regular-text" value="<?php echo esc_attr($row['name'] ?? ''); ?>" placeholder="z.B. angebot"></td>
                                        <td>
                                            <?php
                                            $editor_id = 'cpg_simple_placeholders_no_ki_' . intval($i) . '_text';
                                            wp_editor($row['text'] ?? '', $editor_id, [
                                                'textarea_name' => 'cpg_simple_placeholders_no_ki[' . intval($i) . '][text]',
                                                'media_buttons' => true,
                                                'textarea_rows' => 5,
                                                'tinymce' => true,
                                                'quicktags' => true,
                                            ]);
                                            ?>
                                        </td>
                                        <td><button type="button" class="button cpg-add-simple-ph-no-ki">+</button> <button type="button" class="button cpg-remove-simple-ph-no-ki">−</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php submit_button(__('Platzhalter speichern', 'city-pages-generator')); ?>
                </form>
            </div>
            
            <div class="card">
                <h3><?php _e('Verfügbare Standard-Platzhalter', 'city-pages-generator'); ?></h3>
                <p><?php _e('Diese Platzhalter werden automatisch durch entsprechende Werte ersetzt:', 'city-pages-generator'); ?></p>
                <ul>
                    <li><code>{{stadt}}</code> - <?php _e('Name der Stadt', 'city-pages-generator'); ?></li>
                    <li><code>{{thema}}</code> - <?php _e('Gewähltes Thema', 'city-pages-generator'); ?></li>
                    <li><code>{{keyword}}</code> - <?php _e('Hauptkeyword', 'city-pages-generator'); ?></li>
                    <li><code>{{keywords}}</code> - <?php _e('Alle Keywords (kommagetrennt)', 'city-pages-generator'); ?></li>
                    <li><code>{{meta_description}}</code> - <?php _e('Meta-Beschreibung', 'city-pages-generator'); ?></li>
                    <li><code>{{seitentitel}}</code> - <?php _e('Generierter Seitentitel', 'city-pages-generator'); ?></li>
                    <li><code>{{bundesland}}</code> - <?php _e('Bundesland der Stadt', 'city-pages-generator'); ?></li>
                    <li><code>{{einwohner}}</code> - <?php _e('Einwohnerzahl (formatiert)', 'city-pages-generator'); ?></li>
                    <li><code>{{entfernung}}</code> - <?php _e('Entfernung vom Standort', 'city-pages-generator'); ?></li>
                </ul>
            </div>
            
            
        <?php
    }

    /**
     * Rendert den Anleitung-Tab
     * 
     * Delegiert die Anleitung-Darstellung an die spezialisierte Anleitung-Klasse
     */
    private function renderAnleitungTab() {
        $this->anleitung_handler->render();
    }

    /**
     * Rendert den KI-Tab
     */
    private function renderKITab() {
        $gemini_enabled = get_option('cpg_gemini_enabled', false);
        $gemini_api_key = get_option('cpg_gemini_api_key', '');
        ?>
        <div class="cpg-settings-container">

            <h3><?php _e('KI – Thema & Keyword', 'city-pages-generator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('Thema (automatisch)', 'city-pages-generator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="auto_theme_info" class="regular-text" value="<?php echo esc_attr(get_option('blogname', '')); ?>" disabled>
                        <p class="description"><?php _e('Der Platzhalter {{thema}} entspricht dem Titel der Website aus Einstellungen › Allgemein.', 'city-pages-generator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="theme_keyword"><?php _e('Keyword', 'city-pages-generator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="theme_keyword" name="theme_keyword" class="regular-text" placeholder="z.B. Photovoltaik" value="">
                        <p class="description"><?php _e('Dieses Keyword wird für den Platzhalter {{keyword}} verwendet.', 'city-pages-generator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="service_template"><?php _e('Leistung (Vorlage für Meta)', 'city-pages-generator'); ?></label>
                    </th>
                    <td>
                        <textarea id="service_template" class="large-text" rows="3" placeholder="z.B. Schnelle Hilfe, faire Festpreise, zertifizierte Meisterqualität ..."></textarea>
                        <p class="description"><?php _e('Wird der KI als Vorlage/USP für die Meta‑Beschreibung mitgegeben. Optional, pro Generierung anpassbar.', 'city-pages-generator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="theme_keyword_add"><?php _e('Weitere Keywords', 'city-pages-generator'); ?></label>
                    </th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text" id="theme_keyword_add" class="regular-text" placeholder="z.B. Solaranlage">
                            <button type="button" id="cpg-add-keyword" class="button"><?php _e('Hinzufügen', 'city-pages-generator'); ?></button>
                        </div>
                        <p class="description"><?php _e('Fügen Sie mehrere Keywords hinzu. Diese werden bei der Generierung berücksichtigt.', 'city-pages-generator'); ?></p>
                        <table class="widefat fixed striped" style="max-width:640px; margin-top:10px;" id="cpg-keywords-table">
                            <thead>
                                <tr>
                                    <th style="width:60%"><?php _e('Keyword', 'city-pages-generator'); ?></th>
                                    <th style="width:20%"><?php _e('Erstellt', 'city-pages-generator'); ?></th>
                                    <th style="width:20%"><?php _e('Aktionen', 'city-pages-generator'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="cpg-keywords-tbody">
                                <tr id="cpg-keywords-empty"><td colspan="3"><?php _e('Keine Keywords hinzugefügt.', 'city-pages-generator'); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="cpg-settings-container">
            <h3><?php _e('Einfache KI-Platzhalter', 'city-pages-generator'); ?></h3>
            <p class="description"><?php _e('Definieren Sie beliebig viele Platzhalter. Verwendung in Inhalten als {{name}}.', 'city-pages-generator'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('cpg_simple_ph_settings');
                $rows = get_option('cpg_simple_placeholders', []);
                if (!is_array($rows)) { $rows = []; }
                ?>
                <div style="margin:10px 0; display:flex; gap:8px;">
                    <button type="button" class="button cpg-expand-editors" data-target="#cpg-simple-ph-tbody">Alle ausklappen</button>
                    <button type="button" class="button cpg-collapse-editors" data-target="#cpg-simple-ph-tbody">Alle einklappen</button>
                </div>
                <table class="widefat fixed striped" style="max-width:900px;">
                    <thead>
                        <tr>
                            <th style="width:200px;"><?php _e('Schlüssel (NAME)', 'city-pages-generator'); ?></th>
                            <th><?php _e('Beschreibung (Text)', 'city-pages-generator'); ?></th>
                            <th style="width:120px;">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody id="cpg-simple-ph-tbody">
                        <?php if (empty($rows)) : ?>
                            <tr>
                                <td><input type="text" name="cpg_simple_placeholders[0][name]" class="regular-text" placeholder="z.B. angebot"></td>
                                <td>
                                    <?php
                                    $editor_id = 'cpg_simple_placeholders_0_text';
                                    wp_editor('', $editor_id, [
                                        'textarea_name' => 'cpg_simple_placeholders[0][text]',
                                        'media_buttons' => true,
                                        'textarea_rows' => 5,
                                        'tinymce' => true,
                                        'quicktags' => true,
                                    ]);
                                    ?>
                                </td>
                                <td><button type="button" class="button cpg-add-simple-ph">+</button></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($rows as $i => $row) : ?>
                                <tr>
                                    <td><input type="text" name="cpg_simple_placeholders[<?php echo intval($i); ?>][name]" class="regular-text" value="<?php echo esc_attr($row['name'] ?? ''); ?>" placeholder="z.B. angebot"></td>
                                    <td>
                                        <?php
                                        $editor_id = 'cpg_simple_placeholders_' . intval($i) . '_text';
                                        wp_editor($row['text'] ?? '', $editor_id, [
                                            'textarea_name' => 'cpg_simple_placeholders[' . intval($i) . '][text]',
                                            'media_buttons' => true,
                                            'textarea_rows' => 5,
                                            'tinymce' => true,
                                            'quicktags' => true,
                                        ]);
                                        ?>
                                    </td>
                                    <td><button type="button" class="button cpg-add-simple-ph">+</button> <button type="button" class="button cpg-remove-simple-ph">−</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php submit_button(__('Änderungen speichern', 'city-pages-generator')); ?>
            </form>


            
        </div>
        <?php
    }

    /**
     * Rendert eine Platzhalter-Karte
     */
    private function renderPlaceholderCard($placeholder, $description, $seo_level, $seo_info, $example) {
        $seo_labels = [
            'essential' => ['🔴 Essentiell', 'Pflicht für gute SEO'],
            'recommended' => ['🟡 Empfohlen', 'Verbessert Rankings'],
            'optional' => ['🟢 Optional', 'Nice-to-have'],
            'custom' => ['🎛️ Custom', 'Ihr individueller Platzhalter']
        ];
        
        $label_info = $seo_labels[$seo_level] ?? ['🔵 Standard', 'Standard Platzhalter'];
        ?>
        <div class="cpg-placeholder-card <?php echo $seo_level; ?>">
            <div class="cpg-placeholder-header">
                <code class="cpg-placeholder-code"><?php echo esc_html($placeholder); ?></code>
                <span class="cpg-seo-badge <?php echo $seo_level; ?>" title="<?php echo esc_attr($label_info[1]); ?>">
                    <?php echo $label_info[0]; ?>
                </span>
            </div>
            <div class="cpg-placeholder-description">
                <?php echo esc_html($description); ?>
            </div>
            <div class="cpg-placeholder-seo-info">
                <strong>SEO:</strong> <?php echo esc_html($seo_info); ?>
            </div>
            <div class="cpg-placeholder-example">
                <strong>Beispiel:</strong> <code><?php echo esc_html($example); ?></code>
            </div>
        </div>
        <?php
    }
} 