<?php
/**
 * Bricks Handler Klasse
 * 
 * Diese Klasse verwaltet alle Bricks Builder-spezifischen Funktionen:
 * - Erkennt Bricks-Seiten und deren Datenstruktur
 * - Kopiert Bricks-Daten zwischen Seiten (Content, Header, Footer, Popups)
 * - Verarbeitet Bricks-Elemente rekursiv für Platzhalter-Ersetzung
 * - Setzt Bricks-spezifische SEO-Meta-Daten
 * - Bereinigt Bricks-Caches nach Generierung
 * 
 * Bricks Builder ist ein visueller Page Builder für WordPress,
 * der eine eigene Datenstruktur für Seiteninhalte verwendet.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Bricks_Handler {

    /**
     * Text Replacer Instanz - für Platzhalter-Ersetzung in Bricks-Daten
     * @var CPG_Text_Replacer
     */
    private $text_replacer;

    /**
     * Konstruktor - initialisiert den Text Replacer
     */
    public function __construct() {
        $this->text_replacer = new CPG_Text_Replacer();
    }

         /**
      * Prüft ob eine Seite eine Bricks-Seite ist
      * @param int $page_id
      * @return bool
      */
     public function isBricksPage($page_id) {
         if (!defined('BRICKS_DB_PAGE_CONTENT')) {
             return false;
         }
         $bricks_data = get_post_meta($page_id, BRICKS_DB_PAGE_CONTENT, true);
         return !empty($bricks_data);
     }

         /**
      * Holt alle Bricks-Seiten
      * @return array
      */
     public function getBricksPages() {
         if (!defined('BRICKS_DB_PAGE_CONTENT')) {
             return [];
         }
         
         $pages = get_posts([
             'post_type' => 'page',
             'posts_per_page' => -1,
             'meta_query' => [
                 [
                     'key' => BRICKS_DB_PAGE_CONTENT,
                     'compare' => 'EXISTS'
                 ]
             ],
             'post_status' => 'publish'
         ]);

         return $pages;
     }

    /**
     * Kopiert Bricks-Daten von einer Seite zur anderen
     * @param int $source_id
     * @param int $target_id
     * @param array $city
     * @param array $replacements
     */
         public function copyBricksData($source_id, $target_id, $city, $replacements) {
         if (!$this->validateBricksInstallation()) {
             return;
         }

         // Bricks-Seiteninhalt kopieren
         $bricks_data = get_post_meta($source_id, BRICKS_DB_PAGE_CONTENT, true);
         
         if (!empty($bricks_data)) {
             // Bricks-Daten verarbeiten und Platzhalter ersetzen
             $processed_data = $this->processBricksData($bricks_data, $city, $replacements);
             
             // Verarbeitete Daten in Zielseite speichern
             update_post_meta($target_id, BRICKS_DB_PAGE_CONTENT, $processed_data);
         }

         // Bricks Header kopieren (falls definiert)
         if (defined('BRICKS_DB_PAGE_HEADER')) {
             $header_data = get_post_meta($source_id, BRICKS_DB_PAGE_HEADER, true);
             if (!empty($header_data)) {
                 $processed_header = $this->processBricksData($header_data, $city, $replacements);
                 update_post_meta($target_id, BRICKS_DB_PAGE_HEADER, $processed_header);
             }
         }

         // Bricks Footer kopieren (falls definiert)
         if (defined('BRICKS_DB_PAGE_FOOTER')) {
             $footer_data = get_post_meta($source_id, BRICKS_DB_PAGE_FOOTER, true);
             if (!empty($footer_data)) {
                 $processed_footer = $this->processBricksData($footer_data, $city, $replacements);
                 update_post_meta($target_id, BRICKS_DB_PAGE_FOOTER, $processed_footer);
             }
         }

         // Bricks Popup kopieren (falls definiert)
         if (defined('BRICKS_DB_POPUP_CONTENT')) {
             $popup_data = get_post_meta($source_id, BRICKS_DB_POPUP_CONTENT, true);
             if (!empty($popup_data)) {
                 $processed_popup = $this->processBricksData($popup_data, $city, $replacements);
                 update_post_meta($target_id, BRICKS_DB_POPUP_CONTENT, $processed_popup);
             }
         }

         // Template-Einstellungen kopieren
         if (defined('BRICKS_DB_TEMPLATE_TYPE')) {
             $template_type = get_post_meta($source_id, BRICKS_DB_TEMPLATE_TYPE, true);
             if ($template_type) {
                 update_post_meta($target_id, BRICKS_DB_TEMPLATE_TYPE, $template_type);
             }
         }

         // Template-Settings kopieren
         if (defined('BRICKS_DB_TEMPLATE_SETTINGS')) {
             $template_settings = get_post_meta($source_id, BRICKS_DB_TEMPLATE_SETTINGS, true);
             if ($template_settings) {
                 $processed_settings = $this->text_replacer->replaceInArray($template_settings, $city, $replacements);
                 update_post_meta($target_id, BRICKS_DB_TEMPLATE_SETTINGS, $processed_settings);
             }
         }

         // Page-Settings kopieren
         if (defined('BRICKS_DB_PAGE_SETTINGS')) {
             $page_settings = get_post_meta($source_id, BRICKS_DB_PAGE_SETTINGS, true);
             if ($page_settings) {
                 $processed_page_settings = $this->text_replacer->replaceInArray($page_settings, $city, $replacements);
                 update_post_meta($target_id, BRICKS_DB_PAGE_SETTINGS, $processed_page_settings);
             }
         }
     }

    /**
     * Verarbeitet Bricks-Daten rekursiv
     * @param array $data
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function processBricksData($data, $city, $replacements) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $element) {
            if (is_array($element)) {
                $data[$key] = $this->processBricksElement($element, $city, $replacements);
            }
        }

        return $data;
    }

    /**
     * Verarbeitet ein einzelnes Bricks-Element
     * @param array $element
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function processBricksElement($element, $city, $replacements) {
        // Element-Settings verarbeiten
        if (isset($element['settings'])) {
            $element['settings'] = $this->processBricksSettings($element['settings'], $city, $replacements);
        }

        // Label verarbeiten
        if (isset($element['label']) && is_string($element['label'])) {
            $element['label'] = $this->text_replacer->replaceText($element['label'], $city, $replacements);
        }

        // Kinder-Elemente rekursiv verarbeiten
        if (isset($element['children']) && is_array($element['children'])) {
            foreach ($element['children'] as $child_key => $child_element) {
                $element['children'][$child_key] = $this->processBricksElement($child_element, $city, $replacements);
            }
        }

        return $element;
    }

    /**
     * Verarbeitet Bricks-Element-Settings
     * @param array $settings
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function processBricksSettings($settings, $city, $replacements) {
        foreach ($settings as $key => $value) {
            if (is_string($value)) {
                $settings[$key] = $this->text_replacer->replaceText($value, $city, $replacements);
            } elseif (is_array($value)) {
                $settings[$key] = $this->text_replacer->replaceInArray($value, $city, $replacements);
            }
        }

        // Spezielle Behandlung für häufige Bricks-Felder
        $text_fields = [
            'text', 'content', 'html', 'title', 'description', 'alt', 'caption',
            'placeholder', 'label', 'value', 'url', 'link', 'heading',
            'subheading', 'excerpt', 'buttonText', 'iconText'
        ];

        foreach ($text_fields as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $settings[$field] = $this->text_replacer->replaceText($settings[$field], $city, $replacements);
            }
        }

        // Link-Objekte verarbeiten
        if (isset($settings['link']) && is_array($settings['link'])) {
            foreach ($settings['link'] as $link_key => $link_value) {
                if (is_string($link_value)) {
                    $settings['link'][$link_key] = $this->text_replacer->replaceText($link_value, $city, $replacements);
                }
            }
        }

        // Image-Objekte verarbeiten
        if (isset($settings['image']) && is_array($settings['image'])) {
            foreach (['alt', 'title', 'caption', 'description'] as $img_field) {
                if (isset($settings['image'][$img_field])) {
                    $settings['image'][$img_field] = $this->text_replacer->replaceText($settings['image'][$img_field], $city, $replacements);
                }
            }
        }

        // Video-Objekte verarbeiten
        if (isset($settings['video']) && is_array($settings['video'])) {
            foreach (['title', 'description', 'caption'] as $video_field) {
                if (isset($settings['video'][$video_field])) {
                    $settings['video'][$video_field] = $this->text_replacer->replaceText($settings['video'][$video_field], $city, $replacements);
                }
            }
        }

        // Query-Builder verarbeiten (für Posts, Custom Fields, etc.)
        if (isset($settings['query']) && is_array($settings['query'])) {
            $settings['query'] = $this->processQuerySettings($settings['query'], $city, $replacements);
        }

        return $settings;
    }

    /**
     * Verarbeitet Query-Settings für dynamische Inhalte
     * @param array $query
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function processQuerySettings($query, $city, $replacements) {
        // Meta-Queries verarbeiten
        if (isset($query['meta_query']) && is_array($query['meta_query'])) {
            foreach ($query['meta_query'] as $key => $meta_query) {
                if (isset($meta_query['value']) && is_string($meta_query['value'])) {
                    $query['meta_query'][$key]['value'] = $this->text_replacer->replaceText($meta_query['value'], $city, $replacements);
                }
            }
        }

        // Search-Parameter verarbeiten
        if (isset($query['s']) && is_string($query['s'])) {
            $query['s'] = $this->text_replacer->replaceText($query['s'], $city, $replacements);
        }

        // Taxonomy-Queries verarbeiten
        if (isset($query['tax_query']) && is_array($query['tax_query'])) {
            foreach ($query['tax_query'] as $key => $tax_query) {
                if (isset($tax_query['terms']) && is_array($tax_query['terms'])) {
                    foreach ($tax_query['terms'] as $term_key => $term) {
                        if (is_string($term)) {
                            $query['tax_query'][$key]['terms'][$term_key] = $this->text_replacer->replaceText($term, $city, $replacements);
                        }
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Erstellt Bricks-spezifische SEO-Meta
     * @param int $page_id
     * @param array $city
     * @param array $replacements
     * @param string $title
     */
    public function setBricksSEOMeta($page_id, $city, $replacements, $title) {
        // Bricks eigene SEO-Felder
        $seo_title = $this->text_replacer->replaceText($title . ' in {{stadt}} | {{thema}}', $city, $replacements);
        $seo_description = $this->text_replacer->replaceText(
            '{{thema}} in {{stadt}}, {{bundesland}}. Professionelle Beratung und Service für {{stadt}} und Umgebung.',
            $city,
            $replacements
        );

        // Bricks SEO-Meta setzen
        update_post_meta($page_id, '_bricks_page_seo_title', $seo_title);
        update_post_meta($page_id, '_bricks_page_seo_description', $seo_description);

        // Noindex-Logik entfernt
    }

    /**
     * Bereinigt Bricks-spezifische Caches
     * @param int $page_id
     */
    public function clearBricksCache($page_id) {
        // Bricks eigenen Cache löschen falls verfügbar
        if (class_exists('\Bricks\Database')) {
            \Bricks\Database::clear_cache();
        }

                 // CSS-Cache für spezifische Seite löschen (falls definiert)
         if (defined('BRICKS_DB_PAGE_CSS')) {
             delete_post_meta($page_id, BRICKS_DB_PAGE_CSS);
         }
         if (defined('BRICKS_DB_INLINE_CSS')) {
             delete_post_meta($page_id, BRICKS_DB_INLINE_CSS);
         }
    }

    /**
     * Validiert Bricks-Installation
     * @return bool
     */
    public function validateBricksInstallation() {
        return defined('BRICKS_VERSION') && class_exists('\Bricks\Database');
    }

    /**
     * Holt verfügbare Bricks-Templates
     * @return array
     */
    public function getBricksTemplates() {
        if (!$this->validateBricksInstallation()) {
            return [];
        }

                 $templates = get_posts([
             'post_type' => defined('BRICKS_DB_TEMPLATE_SLUG') ? BRICKS_DB_TEMPLATE_SLUG : 'bricks_template',
             'posts_per_page' => -1,
             'post_status' => 'publish',
             'meta_query' => [
                 [
                     'key' => defined('BRICKS_DB_TEMPLATE_TYPE') ? BRICKS_DB_TEMPLATE_TYPE : '_bricks_template_type',
                     'value' => ['page', 'section', 'header', 'footer'],
                     'compare' => 'IN'
                 ]
             ]
         ]);

        return $templates;
    }
} 