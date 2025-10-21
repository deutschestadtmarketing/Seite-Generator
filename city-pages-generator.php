<?php
/**
 * Plugin Name: Stadtseiten Generator
 * Description: Erstellt automatisch lokalisierte Elementor-Seiten für Städte im Umkreis eines Ausgangsorts.
 * Version: 1.0.0
 * Author: Omar Khalil
 * Author URI: https://www.deutsche-stadtmarketing.de/
 * Plugin URI: https://www.deutsche-stadtmarketing.de/
 * License: GPL v2 or later
 * License URI: https://www.deutsche-stadtmarketing.de/
 * Text Domain: city-pages-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Verhindert direkten Zugriff - WordPress Sicherheitsmaßnahme
// ABSPATH wird von WordPress definiert, wenn das Plugin über WordPress geladen wird
if (!defined('ABSPATH')) {
    exit; // Beendet die Ausführung, wenn die Datei direkt aufgerufen wird
}

// Plugin-Konstanten definieren - Globale Konstanten für das gesamte Plugin
// Diese Konstanten werden in allen Plugin-Dateien verwendet
define('CPG_PLUGIN_URL', plugin_dir_url(__FILE__));        // URL zum Plugin-Verzeichnis (z.B. https://example.com/wp-content/plugins/Seite_Erstellen_lange/)
define('CPG_PLUGIN_PATH', plugin_dir_path(__FILE__));      // Pfad zum Plugin-Verzeichnis (z.B. /var/www/html/wp-content/plugins/Seite_Erstellen_lange/)
define('CPG_PLUGIN_VERSION', '1.0.0');                     // Aktuelle Plugin-Version für Cache-Busting
define('CPG_PLUGIN_BASENAME', plugin_basename(__FILE__));  // Plugin-Basename für WordPress-Funktionen

/**
 * Hauptklasse des City Pages Generator Plugins
 * 
 * Diese Klasse ist das Herzstück des Plugins und verwaltet:
 * - Plugin-Initialisierung und Hooks
 * - AJAX-Handler für Frontend-Interaktionen
 * - Admin-Interface und Menüs
 * - Plugin-Aktivierung/Deaktivierung
 * - Autoloader für andere Plugin-Klassen
 */
class CityPagesGenerator {

    /**
     * Singleton-Instanz - stellt sicher, dass nur eine Instanz der Klasse existiert
     * @var CityPagesGenerator
     */
    private static $instance = null;

    /**
     * Admin-Handler-Instanz - verwaltet das Backend-Interface
     * @var CPG_Admin_Handler
     */
    private $admin_handler = null;

    /**
     * Singleton-Pattern - gibt die einzige Instanz der Klasse zurück
     * Verhindert mehrfache Instanziierung und spart Speicher
     * @return CityPagesGenerator
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Privater Konstruktor - kann nur über getInstance() aufgerufen werden
     * Startet die Plugin-Initialisierung
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Plugin initialisieren - registriert alle WordPress Hooks und Filter
     * Diese Methode wird beim Plugin-Start aufgerufen
     */
    private function init() {
        // WordPress Hooks registrieren - verbindet Plugin-Funktionen mit WordPress-Events
        
        // Textdomain für Übersetzungen laden
        add_action('init', [$this, 'loadTextdomain']);
        
        // Admin-Interface: Menü und Scripts
        add_action('admin_menu', [$this, 'addAdminMenu']);                    // Fügt Menüpunkt im WordPress-Admin hinzu
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);  // Lädt CSS/JS nur auf Plugin-Seiten
        add_action('admin_init', [$this, 'handleActivationRedirect']);       // Weiterleitung nach Plugin-Aktivierung
        
        // AJAX-Handler für Frontend-Interaktionen (alle mit 'wp_ajax_' Prefix)
        add_action('wp_ajax_cpg_get_cities', [$this, 'ajaxGetCities']);           // Lädt Städte über API
        add_action('wp_ajax_cpg_generate_pages', [$this, 'ajaxGeneratePages']); // Generiert Seiten (kleine Mengen)
        add_action('wp_ajax_cpg_generate_pages_batch', [$this, 'ajaxGeneratePagesBatch']); // Batch-Verarbeitung für große Mengen
        add_action('wp_ajax_cpg_publish_page', [$this, 'ajaxPublishPage']);      // Veröffentlicht eine Seite
        add_action('wp_ajax_cpg_unpublish_page', [$this, 'ajaxUnpublishPage']);  // Setzt Seite auf Entwurf
        add_action('wp_ajax_cpg_delete_page', [$this, 'ajaxDeletePage']);        // Löscht eine Seite
        add_action('wp_ajax_cpg_test_gemini', [$this, 'ajaxTestGemini']);        // Testet Gemini API-Verbindung

        // Frontend-Filter: ALT-Attribute für generierte Seiten im finalen HTML erzwingen
        add_filter('the_content', [$this, 'enforceAltOnGeneratedPages'], 999);  // Sehr hohe Priorität (999)
        // Falls der Builder nicht über the_content rendert: gesamten Output abfangen
        add_action('template_redirect', [$this, 'maybeStartAltOutputBuffer'], 1); // Sehr hohe Priorität (1)

        // Plugin-Lebenszyklus: Aktivierung/Deaktivierung
        register_activation_hook(__FILE__, [$this, 'activate']);     // Wird bei Plugin-Aktivierung ausgeführt
        register_deactivation_hook(__FILE__, [$this, 'deactivate']); // Wird bei Plugin-Deaktivierung ausgeführt

        // Autoloader für Plugin-Klassen - lädt Klassen automatisch bei Bedarf
        spl_autoload_register([$this, 'autoload']);

        // Admin-Handler initialisieren (für Settings-Registrierung)
        if (is_admin()) {
            add_action('init', [$this, 'initAdminHandler']);
        }

        // Überprüfung auf Builder-Verfügbarkeit - zeigt Warnungen im Admin
        add_action('admin_notices', [$this, 'checkBuilderRequirements']);
    }

    /**
     * Autoloader für Plugin-Klassen - lädt Klassen automatisch bei Bedarf
     * Konvertiert Klassennamen zu Dateipfaden und lädt sie dynamisch
     * @param string $class_name Name der zu ladenden Klasse
     */
    public function autoload($class_name) {
        // Nur Plugin-Klassen laden (alle beginnen mit 'CPG_')
        if (strpos($class_name, 'CPG_') === 0) {
            // Konvertiert z.B. 'CPG_Admin_Handler' zu 'class-admin-handler.php'
            $class_file = CPG_PLUGIN_PATH . 'includes/class-' . strtolower(str_replace('_', '-', substr($class_name, 4))) . '.php';
            if (file_exists($class_file)) {
                require_once $class_file;
            }
        }
    }

    /**
     * Textdomain laden - ermöglicht Übersetzungen des Plugins
     * Lädt Sprachdateien aus dem /languages Verzeichnis
     */
    public function loadTextdomain() {
        load_plugin_textdomain('city-pages-generator', false, dirname(CPG_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Admin-Menü hinzufügen - erstellt den Hauptmenüpunkt im WordPress-Admin
     * Erscheint im linken Admin-Menü unter "Seitengenerator"
     */
    public function addAdminMenu() {
        add_menu_page(
            __('Stadtseiten Generator', 'city-pages-generator'),  // 1. Seitentitel (im Browser-Tab)
            __('Seitengenerator', 'city-pages-generator'),       // 2. Menütitel (was man links sieht)
            'manage_options',                                   // 3. Benötigte Berechtigung (nur Admins)
            'city-pages-generator',                            // 4. Eindeutige URL-Kennung (Slug)
            [$this, 'adminPage'],                             // 5. Callback-Funktion zur Ausgabe der Seite
            'dashicons-admin-page',                          // 6. WordPress Dashicon (Seiten-Symbol)
            30                                              // 7. Position im Menü (nach "Seiten")
        );
    }

    /**
     * Admin-Scripts einbinden - lädt CSS und JavaScript nur auf Plugin-Seiten
     * Verhindert Konflikte mit anderen Plugins durch gezieltes Laden
     * @param string $hook Aktuelle Admin-Seite (z.B. 'toplevel_page_city-pages-generator')
     */
    public function enqueueAdminScripts($hook) {
        // Nur auf Plugin-Seiten laden (nicht auf allen Admin-Seiten)
        if ($hook !== 'toplevel_page_city-pages-generator') {
            return;
        }

        // JavaScript für AJAX-Funktionalität laden
        wp_enqueue_script(
            'cpg-admin-script',                                    // Eindeutiger Handle
            CPG_PLUGIN_URL . 'assets/js/admin.js',               // Pfad zur JS-Datei
            ['jquery'],                                           // Abhängigkeiten (jQuery)
            CPG_PLUGIN_VERSION,                                  // Version für Cache-Busting
            true                                                 // Im Footer laden
        );

        // CSS für Plugin-Styling laden
        wp_enqueue_style(
            'cpg-admin-style',                                    // Eindeutiger Handle
            CPG_PLUGIN_URL . 'assets/css/admin.css',            // Pfad zur CSS-Datei
            [],                                                  // Keine Abhängigkeiten
            CPG_PLUGIN_VERSION                                  // Version für Cache-Busting
        );

        // JavaScript-Variablen für AJAX-Calls an Frontend übergeben
        wp_localize_script('cpg-admin-script', 'cpgAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),            // WordPress AJAX-URL
            'nonce' => wp_create_nonce('cpg_nonce'),            // Sicherheits-Token
            'strings' => [                                      // Übersetzbare Strings
                'loading' => __('Lädt...', 'city-pages-generator'),
                'error' => __('Fehler aufgetreten', 'city-pages-generator'),
                'confirm_delete' => __('Seite wirklich löschen?', 'city-pages-generator'),
                'success' => __('Erfolgreich abgeschlossen', 'city-pages-generator')
            ]
        ]);
    }

    /**
     * Admin-Handler initialisieren
     */
    public function initAdminHandler() {
        if (!isset($this->admin_handler)) {
            $this->admin_handler = new CPG_Admin_Handler();
        }
    }

    /**
     * Admin-Seite anzeigen
     */
    public function adminPage() {
        if (!isset($this->admin_handler)) {
            $this->admin_handler = new CPG_Admin_Handler();
        }
        $this->admin_handler->render();
    }

    /**
     * AJAX: Städte abrufen - lädt Städte in der Nähe eines Ausgangsorts
     * Wird vom Frontend aufgerufen, wenn der Benutzer "Städte laden" klickt
     * Verwendet GeoNames API oder OpenStreetMap als Fallback
     */
    public function ajaxGetCities() {
        // Sicherheitsprüfung: AJAX-Nonce validieren
        check_ajax_referer('cpg_nonce', 'nonce');
        
        // Berechtigung prüfen: nur Admins dürfen Städte laden
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        // Eingabedaten aus POST-Request extrahieren und sanitisieren
        $location = sanitize_text_field($_POST['location'] ?? '');        // Ausgangsort (z.B. "Krefeld")
        $radius = intval($_POST['radius'] ?? 50);                       // Radius in km
        $min_population = intval($_POST['min_population'] ?? 50000);     // Mindesteinwohner

        // City API verwenden um Städte zu finden
        $city_api = new CPG_City_API();
        $cities = $city_api->getCitiesNearby($location, $radius, $min_population);

        // Prüfen ob ein Fehler aufgetreten ist (z.B. API nicht erreichbar)
        if (isset($cities['error'])) {
            wp_send_json_error($cities['error']);
        }

        // Erfolgreiche Antwort mit Städte-Array zurückgeben
        wp_send_json_success($cities);
    }

    /**
     * AJAX: Seiten generieren - erstellt neue Seiten basierend auf einer Vorlage
     * Wird vom Frontend aufgerufen, wenn der Benutzer "Seiten generieren" klickt
     * Entscheidet automatisch zwischen direkter Verarbeitung und Batch-Verarbeitung
     */
    public function ajaxGeneratePages() {
        // Sicherheitsprüfung: AJAX-Nonce validieren
        check_ajax_referer('cpg_nonce', 'nonce');
        
        // Berechtigung prüfen: nur Admins dürfen Seiten generieren
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        // Eingabedaten aus POST-Request extrahieren und sanitisieren
        $source_page_id = intval($_POST['source_page_id'] ?? 0);                    // ID der Quellseite
        $cities = json_decode(stripslashes($_POST['cities'] ?? '[]'), true);        // Array der ausgewählten Städte
        $slug_pattern = sanitize_text_field($_POST['slug_pattern'] ?? '');          // URL-Slug Schema
        $replacements = json_decode(stripslashes($_POST['replacements'] ?? '{}'), true); // Ersetzungen (Keywords, etc.)

        // Intelligente Verarbeitung: kleine Mengen direkt, große Mengen in Batches
        if (count($cities) < 20) {
            // Direkte Verarbeitung für kleine Mengen (< 20 Städte)
            $page_generator = new CPG_Page_Generator();
            $results = $page_generator->generatePages($source_page_id, $cities, $slug_pattern, $replacements);
            wp_send_json_success($results);
        } else {
            // Batch-Verarbeitung für große Mengen (≥ 20 Städte) - schont den Server
            $this->startBatchProcessing($source_page_id, $cities, $slug_pattern, $replacements);
        }
    }

    /**
     * AJAX: Batch-Verarbeitung für große Seitenmengen
     */
    public function ajaxGeneratePagesBatch() {
        check_ajax_referer('cpg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');
        $batch_number = intval($_POST['batch_number'] ?? 0);
        
        if (empty($batch_id)) {
            wp_send_json_error(['message' => __('Batch-ID fehlt', 'city-pages-generator')]);
        }

        // Batch-Daten aus Transient abrufen
        $batch_data = get_transient('cpg_batch_' . $batch_id);
        if (!$batch_data) {
            wp_send_json_error(['message' => __('Batch-Daten nicht gefunden', 'city-pages-generator')]);
        }
        
        $page_generator = new CPG_Page_Generator();
        $results = $page_generator->generatePagesBatch(
            $batch_data['source_page_id'],
            $batch_data['cities'],
            $batch_data['slug_pattern'],
            $batch_data['replacements'],
            $batch_number,
            $batch_data['batch_size']
        );

        wp_send_json_success($results);
    }

    /**
     * Startet die Batch-Verarbeitung für große Seitenmengen
     */
    private function startBatchProcessing($source_page_id, $cities, $slug_pattern, $replacements) {
        // Eindeutige Batch-ID generieren
        $batch_id = 'batch_' . time() . '_' . wp_generate_password(8, false);
        
        // Benutzerdefinierte Batch-Einstellungen abrufen
        $batch_size = intval($_POST['batch_size'] ?? 15);
        $batch_delay = intval($_POST['batch_delay'] ?? 2);
        
        // Batch-Daten für spätere Verarbeitung speichern
        $batch_data = [
            'source_page_id' => $source_page_id,
            'cities' => $cities,
            'slug_pattern' => $slug_pattern,
            'replacements' => $replacements,
            'total_cities' => count($cities),
            'batch_size' => $batch_size,
            'batch_delay' => $batch_delay,
            'created_at' => current_time('mysql')
        ];
        
        // Batch-Daten für 1 Stunde speichern
        set_transient('cpg_batch_' . $batch_id, $batch_data, 3600);
        
        $total_batches = ceil(count($cities) / $batch_data['batch_size']);
        
        wp_send_json_success([
            'batch_processing' => true,
            'batch_id' => $batch_id,
            'total_cities' => count($cities),
            'total_batches' => $total_batches,
            'batch_size' => $batch_data['batch_size'],
            'message' => sprintf(
                __('Batch-Verarbeitung gestartet: %d Seiten in %d Batches', 'city-pages-generator'),
                count($cities),
                $total_batches
            )
        ]);
    }

    /**
     * AJAX: Seite veröffentlichen
     */
    public function ajaxPublishPage() {
        check_ajax_referer('cpg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        
        $result = wp_update_post([
            'ID' => $page_id,
            'post_status' => 'publish'
        ]);

        if ($result) {
            wp_send_json_success(['message' => __('Seite veröffentlicht', 'city-pages-generator')]);
        } else {
            wp_send_json_error(['message' => __('Fehler beim Veröffentlichen', 'city-pages-generator')]);
        }
    }

    /**
     * AJAX: Seite auf Entwurf zurücksetzen
     */
    public function ajaxUnpublishPage() {
        check_ajax_referer('cpg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        
        $result = wp_update_post([
            'ID' => $page_id,
            'post_status' => 'draft'
        ]);

        if ($result) {
            wp_send_json_success(['message' => __('Seite auf Entwurf gesetzt', 'city-pages-generator')]);
        } else {
            wp_send_json_error(['message' => __('Fehler beim Zurücksetzen', 'city-pages-generator')]);
        }
    }

    /**
     * AJAX: Seite löschen
     */
    public function ajaxDeletePage() {
        check_ajax_referer('cpg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        
        $result = wp_delete_post($page_id, true);

        if ($result) {
            wp_send_json_success(['message' => __('Seite gelöscht', 'city-pages-generator')]);
        } else {
            wp_send_json_error(['message' => __('Fehler beim Löschen', 'city-pages-generator')]);
        }
    }

    /**
     * AJAX: Gemini API-Verbindung testen
     */
    public function ajaxTestGemini() {
        check_ajax_referer('cpg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'city-pages-generator'));
        }

        $temp_key = isset($_POST['temp_api_key']) ? sanitize_text_field($_POST['temp_api_key']) : '';
        $gemini_api = new CPG_Gemini_API();
        if (!empty($temp_key)) {
            // Temporär den eingegebenen Key verwenden (ohne zu speichern)
            $gemini_api->setApiKey($temp_key);
        }
        $result = $gemini_api->testConnection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    

    /**
     * Plugin aktivieren - wird beim ersten Aktivieren des Plugins ausgeführt
     * Erstellt notwendige Datenbank-Tabellen und setzt Initialwerte
     */
    public function activate() {
        // Datenbank-Tabellen erstellen falls nötig (für Tracking generierter Seiten)
        $this->createTables();
        
        // Flag für Weiterleitung setzen - leitet Benutzer nach Aktivierung zur Plugin-Seite
        add_option('cpg_activation_redirect', true);
        
        // WordPress URL-Rewrite-Regeln neu generieren
        flush_rewrite_rules();
    }

    /**
     * Plugin deaktivieren - wird beim Deaktivieren des Plugins ausgeführt
     * Bereinigt temporäre Daten und regeneriert URL-Regeln
     */
    public function deactivate() {
        // WordPress URL-Rewrite-Regeln neu generieren
        flush_rewrite_rules();
    }

    /**
     * Datenbank-Tabellen erstellen - erstellt Tracking-Tabelle für generierte Seiten
     * Verwendet WordPress dbDelta() für sichere Tabellenerstellung
     */
    private function createTables() {
        global $wpdb;

        // Tabellenname mit WordPress-Prefix (z.B. wp_cpg_generated_pages)
        $table_name = $wpdb->prefix . 'cpg_generated_pages';

        // WordPress Charset und Collation verwenden
        $charset_collate = $wpdb->get_charset_collate();

        // SQL für Tracking-Tabelle - speichert alle generierten Seiten
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,           // Eindeutige ID
            page_id bigint(20) NOT NULL,                       // WordPress Post-ID der generierten Seite
            source_page_id bigint(20) NOT NULL,               // ID der ursprünglichen Vorlage
            city_name varchar(255) NOT NULL,                  // Name der Stadt
            state varchar(255) DEFAULT '',                     // Bundesland/Staat
            country varchar(255) DEFAULT '',                   // Land
            slug varchar(255) NOT NULL,                        // URL-Slug der Seite
            status varchar(20) DEFAULT 'draft',                // Status (draft/publish)
            created_at datetime DEFAULT CURRENT_TIMESTAMP,     // Erstellungsdatum
            PRIMARY KEY (id),                                  // Primärschlüssel
            KEY page_id (page_id),                            // Index für schnelle Suche nach Post-ID
            KEY source_page_id (source_page_id)               // Index für schnelle Suche nach Quellseite
        ) $charset_collate;";

        // WordPress dbDelta() verwenden für sichere Tabellenerstellung
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Weiterleitung nach Aktivierung handhaben
     */
    public function handleActivationRedirect() {
        if (get_option('cpg_activation_redirect', false)) {
            delete_option('cpg_activation_redirect');
            wp_redirect(admin_url('admin.php?page=city-pages-generator&tab=settings'));
            exit;
        }
    }


    /**
     * Builder-Voraussetzungen prüfen
     */
    public function checkBuilderRequirements() {
        $selected_builder = get_option('cpg_selected_builder', '');
        
        if (empty($selected_builder)) {
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(
                __('City Pages Generator: Bitte wählen Sie einen Page Builder in den <a href="%s">Einstellungen</a> aus.', 'city-pages-generator'),
                admin_url('admin.php?page=city-pages-generator&tab=settings')
            );
            echo '</p></div>';
            return;
        }
        
        $requirements_met = false;
        
        if ($selected_builder === 'elementor' && is_plugin_active('elementor/elementor.php')) {
            $requirements_met = true;
        } elseif ($selected_builder === 'bricks' && defined('BRICKS_VERSION')) {
            $requirements_met = true;
        }
        
        if (!$requirements_met) {
            echo '<div class="notice notice-error"><p>';
            if ($selected_builder === 'elementor') {
                echo __('City Pages Generator: Elementor ist nicht installiert oder aktiviert. Bitte installieren Sie Elementor oder wählen Sie einen anderen Builder.', 'city-pages-generator');
            } elseif ($selected_builder === 'bricks') {
                echo __('City Pages Generator: Bricks Builder ist nicht installiert oder aktiviert. Bitte installieren Sie Bricks oder wählen Sie einen anderen Builder.', 'city-pages-generator');
            }
            echo '</p></div>';
        }
    }

    /**
     * Setzt im finalen HTML-Inhalt bei generierten Seiten das ALT-Attribut
     * aller <img>-Tags auf "Thema in {Stadt}". Überschreibt bestehende ALTs.
     * 
     * Diese Funktion verbessert die SEO und Barrierefreiheit generierter Seiten,
     * indem sie automatisch aussagekräftige ALT-Texte für Bilder hinzufügt.
     * 
     * Greift nur im Frontend und nur für Seiten, die in der Tracking-Tabelle stehen.
     * @param string $content Der HTML-Inhalt der Seite
     * @return string Der modifizierte HTML-Inhalt mit ALT-Attributen
     */
    public function enforceAltOnGeneratedPages($content) {
        if (is_admin() || (!is_singular() && !is_preview())) {
            return $content;
        }
        global $post, $wpdb;
        if (!$post) {
            return $content;
        }
        $table_name = $wpdb->prefix . 'cpg_generated_pages';
        $row = $wpdb->get_row($wpdb->prepare("SELECT city_name FROM {$table_name} WHERE page_id = %d LIMIT 1", $post->ID));
        // Thema ermitteln: bevorzugt gespeicherter theme_keyword, Fallback Blogname
        $theme = get_option('blogname', '');
        // Optional: Wenn die Seite ein eigenes Theme-Keyword als Meta hat, nutzen
        $meta_theme = get_post_meta($post->ID, '_cpg_theme_keyword', true);
        if (is_string($meta_theme) && trim($meta_theme) !== '') {
            $theme = sanitize_text_field($meta_theme);
        }
        $city = '';
        if ($row && !empty($row->city_name)) {
            $city = sanitize_text_field($row->city_name);
        } else {
            // Fallbacks für Preview bzw. ältere Seiten ohne Tracking: erst Post-Meta, dann Slug ableiten
            $meta_city = get_post_meta($post->ID, '_cpg_city_name', true);
            if (is_string($meta_city) && trim($meta_city) !== '') {
                $city = sanitize_text_field($meta_city);
            } else {
                $slug = is_string($post->post_name) ? $post->post_name : '';
                if ($slug !== '') {
                    // Letztes Segment des Slugs als Stadt interpretieren (best guess)
                    $parts = explode('-', $slug);
                    $last = end($parts);
                    if (is_string($last) && $last !== '') {
                        $city = ucwords(str_replace('-', ' ', $last));
                    }
                }
            }
        }
        if ($theme === '' || $city === '') {
            return $content;
        }
        $alt = esc_attr($theme . ' in ' . $city);
        // ALT immer überschreiben oder setzen
        $callback = function($m) use ($alt) {
            $tag = $m[0];
            if (preg_match('/\balt\s*=\s*(["\'])[\s\S]*?\1/i', $tag)) {
                return preg_replace('/\balt\s*=\s*(["\'])[\s\S]*?\1/i', 'alt="' . $alt . '"', $tag, 1);
            }
            $insert = ' alt="' . $alt . '"';
            if (substr($tag, -2) === '/>') {
                return substr($tag, 0, -2) . $insert . '/>';
            }
            return substr($tag, 0, -1) . $insert . '>';
        };
        return preg_replace_callback('/<img\b[^>]*>/i', $callback, $content);
    }

    /**
     * Startet bei generierten Seiten eine Output-Pufferung, die am Ende
     * dieselbe ALT-Ersetzung auf den gesamten HTML-Output anwendet.
     */
    public function maybeStartAltOutputBuffer() {
        if (is_admin() || (!is_singular() && !is_preview())) {
            return;
        }
        global $post, $wpdb;
        if (!$post) {
            return;
        }
        $table_name = $wpdb->prefix . 'cpg_generated_pages';
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table_name} WHERE page_id = %d", $post->ID));
        if ($exists !== 1 && !is_preview()) {
            return; // Für Previews erlauben wir das Überschreiben auch ohne Tracking
        }
        ob_start(function($html) use ($post) {
            // Thema und Stadt wie in enforceAltOnGeneratedPages ermitteln
            $theme = get_option('blogname', '');
            $meta_theme = get_post_meta($post->ID, '_cpg_theme_keyword', true);
            if (is_string($meta_theme) && trim($meta_theme) !== '') {
                $theme = sanitize_text_field($meta_theme);
            }
            global $wpdb;
            $table_name = $wpdb->prefix . 'cpg_generated_pages';
            $city = (string)$wpdb->get_var($wpdb->prepare("SELECT city_name FROM {$table_name} WHERE page_id = %d LIMIT 1", $post->ID));
            if ($city === '') {
                $meta_city = get_post_meta($post->ID, '_cpg_city_name', true);
                if (is_string($meta_city) && trim($meta_city) !== '') {
                    $city = sanitize_text_field($meta_city);
                } else {
                    $slug = is_string($post->post_name) ? $post->post_name : '';
                    if ($slug !== '') {
                        $parts = explode('-', $slug);
                        $last = end($parts);
                        if (is_string($last) && $last !== '') {
                            $city = ucwords(str_replace('-', ' ', $last));
                        }
                    }
                }
            }
            if ($theme === '' || $city === '') {
                return $html;
            }
            $alt = esc_attr($theme . ' in ' . $city);
            $callback = function($m) use ($alt) {
                $tag = $m[0];
                if (preg_match('/\balt\s*=\s*(["\'])[\s\S]*?\1/i', $tag)) {
                    return preg_replace('/\balt\s*=\s*(["\'])[\s\S]*?\1/i', 'alt="' . $alt . '"', $tag, 1);
                }
                $insert = ' alt="' . $alt . '"';
                if (substr($tag, -2) === '/>') {
                    return substr($tag, 0, -2) . $insert . '/>';
                }
                return substr($tag, 0, -1) . $insert . '>';
            };
            return preg_replace_callback('/<img\b[^>]*>/i', $callback, $html);
        });
    }
}

// Plugin instanziieren - startet das Plugin nach dem Laden aller anderen Plugins
// Verwendet 'plugins_loaded' Hook um sicherzustellen, dass alle Abhängigkeiten verfügbar sind
add_action('plugins_loaded', function() {
    CityPagesGenerator::getInstance(); // Singleton-Pattern: erstellt die einzige Plugin-Instanz
}); 