<?php
/**
 * Page Generator Klasse
 * 
 * Diese Klasse ist das Herzstück der Seiten-Generierung und verwaltet:
 * - Duplizierung von Elementor- und Bricks-Seiten
 * - Platzhalter-Ersetzung in allen Inhalten
 * - SEO-Meta-Daten Generierung
 * - Batch-Verarbeitung für große Mengen
 * - Tracking der generierten Seiten
 * - KI-Integration für bessere Inhalte
 * 
 * Die Klasse unterstützt sowohl Elementor als auch Bricks Builder
 * und kann hunderte von Seiten gleichzeitig generieren.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Page_Generator {

    /**
     * Text Replacer für Platzhalter-Ersetzung in allen Inhalten
     * @var CPG_Text_Replacer
     */
    private $text_replacer;
    
    /**
     * Bricks Handler für Bricks Builder-spezifische Funktionen
     * @var CPG_Bricks_Handler
     */
    private $bricks_handler;
    
    /**
     * Ausgewählter Page Builder (elementor oder bricks)
     * @var string
     */
    private $selected_builder;
    
    /**
     * Gemini API für KI-Textgenerierung (optional)
     * @var CPG_Gemini_API|null
     */
    private $gemini_api;

    /**
     * Konstruktor - initialisiert alle Handler und lädt Konfiguration
     */
    public function __construct() {
        $this->text_replacer = new CPG_Text_Replacer();                    // Text-Ersetzung initialisieren
        $this->bricks_handler = new CPG_Bricks_Handler();                  // Bricks Handler initialisieren
        $this->selected_builder = get_option('cpg_selected_builder', 'elementor'); // Builder aus Einstellungen
        
        // Gemini API nur laden wenn aktiviert (spart Ressourcen)
        if (get_option('cpg_gemini_enabled', false)) {
            $this->gemini_api = new CPG_Gemini_API();
        }
    }

    /**
     * Generiert Seiten für mehrere Städte mit Rate Limiting und Timeout-Schutz
     * @param int $source_page_id
     * @param array $cities
     * @param string $slug_pattern
     * @param array $replacements
     * @return array
     */
    public function generatePages($source_page_id, $cities, $slug_pattern, $replacements = []) {
        // Execution Time für längere Verarbeitung erhöhen
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(0);
        
        // Memory Limit erhöhen wenn nötig
        $current_memory = ini_get('memory_limit');
        if (intval($current_memory) < 256) {
            ini_set('memory_limit', '256M');
        }
        
        $results = [];
        $source_page = get_post($source_page_id);
        
        if (!$source_page || !in_array($source_page->post_type, ['page', 'post'])) {
            return ['error' => __('Quellseite nicht gefunden oder ungültiger Typ', 'city-pages-generator')];
        }

        // Prüfen ob die Seite für den gewählten Builder geeignet ist
        if (!$this->isValidBuilderPage($source_page_id)) {
            $builder_name = $this->selected_builder === 'bricks' ? 'Bricks' : 'Elementor';
            return ['error' => sprintf(__('Quellseite ist keine %s-Seite', 'city-pages-generator'), $builder_name)];
        }

        $total_cities = count($cities);

        // Thema automatisch aus den WP-Einstellungen (Titel der Website) bereitstellen
        if (!isset($replacements['auto_theme']) || empty($replacements['auto_theme'])) {
            $replacements['auto_theme'] = get_option('blogname', '');
        }
        
        // Bei vielen Städten: Warnung und Batch-Verarbeitung
        if ($total_cities > 20) {
            error_log("CPG: Große Batch-Generierung mit {$total_cities} Städten gestartet");
        }
        
        foreach ($cities as $index => $city) {
            try {
                // Progress-Ausgabe für große Batches
                if ($total_cities > 10 && $index % 5 === 0) {
                    error_log("CPG: Verarbeite Stadt {$index}/{$total_cities}: {$city['name']}");
                }
                
                
                $result = $this->generateSinglePage($source_page, $city, $slug_pattern, $replacements);
                $results[] = $result;
                
                // Garbage Collection bei großen Batches
                if ($index % 10 === 0) {
                    gc_collect_cycles();
                }
                
            } catch (Exception $e) {
                error_log("CPG: Fehler bei Stadt {$city['name']}: " . $e->getMessage());
                
                $results[] = [
                    'success' => false,
                    'city' => $city['name'],
                    'error' => $e->getMessage()
                ];
                
                // Bei Rate Limit Fehler: Längerer Delay
                if (strpos($e->getMessage(), 'Rate Limit') !== false) {
                    sleep(10);
                }
                
                // Bei kritischen Fehlern: Batch abbrechen
                if (strpos($e->getMessage(), 'memory') !== false || 
                    strpos($e->getMessage(), 'timeout') !== false) {
                    error_log("CPG: Kritischer Fehler - Batch abgebrochen bei Stadt {$index}");
                    break;
                }
            }
        }

        // Original Time Limit wiederherstellen
        set_time_limit($original_time_limit);
        
        error_log("CPG: Batch-Generierung abgeschlossen. {$total_cities} Städte verarbeitet.");
        
        return $results;
    }

    /**
     * Generiert Seiten in Batches für große Mengen
     * @param int $source_page_id
     * @param array $cities
     * @param string $slug_pattern
     * @param array $replacements
     * @param int $batch_number
     * @return array
     */
    public function generatePagesBatch($source_page_id, $cities, $slug_pattern, $replacements, $batch_number, $batch_size = 15) {
        // Execution Time für längere Verarbeitung erhöhen
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(0);
        
        // Memory Limit erhöhen wenn nötig
        $current_memory = ini_get('memory_limit');
        if (intval($current_memory) < 256) {
            ini_set('memory_limit', '256M');
        }
        
        // Batch-Größe aus Parameter verwenden
        $batch_size = intval($batch_size);
        $start_index = $batch_number * $batch_size;
        $end_index = min($start_index + $batch_size, count($cities));
        
        // Städte für diesen Batch extrahieren
        $batch_cities = array_slice($cities, $start_index, $batch_size);
        
        $results = [];
        $source_page = get_post($source_page_id);
        
        if (!$source_page || !in_array($source_page->post_type, ['page', 'post'])) {
            return ['error' => __('Quellseite nicht gefunden oder ungültiger Typ', 'city-pages-generator')];
        }

        // Prüfen ob die Seite für den gewählten Builder geeignet ist
        if (!$this->isValidBuilderPage($source_page_id)) {
            $builder_name = $this->selected_builder === 'bricks' ? 'Bricks' : 'Elementor';
            return ['error' => sprintf(__('Quellseite ist keine %s-Seite', 'city-pages-generator'), $builder_name)];
        }

        // Thema automatisch aus den WP-Einstellungen bereitstellen
        if (!isset($replacements['auto_theme']) || empty($replacements['auto_theme'])) {
            $replacements['auto_theme'] = get_option('blogname', '');
        }
        
        foreach ($batch_cities as $index => $city) {
            try {
                $result = $this->generateSinglePage($source_page, $city, $slug_pattern, $replacements);
                $results[] = $result;
                
                // Kleine Pause zwischen Seiten um Server zu entlasten
                usleep(100000); // 0.1 Sekunde
                
            } catch (Exception $e) {
                error_log("CPG Batch Fehler bei Stadt {$city['name']}: " . $e->getMessage());
                
                $results[] = [
                    'success' => false,
                    'city' => $city['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Original Time Limit wiederherstellen
        set_time_limit($original_time_limit);
        
        return [
            'batch_number' => $batch_number,
            'batch_size' => count($batch_cities),
            'total_processed' => $start_index + count($batch_cities),
            'results' => $results
        ];
    }


    /**
     * Generiert eine einzelne Seite für eine Stadt
     * @param WP_Post $source_page
     * @param array $city
     * @param string $slug_pattern
     * @param array $replacements
     * @return array
     */
    private function generateSinglePage($source_page, $city, $slug_pattern, $replacements = []) {
        global $wpdb;


        // Slug generieren
        $slug = $this->generateSlug($slug_pattern, $city, $replacements);
        
        // Prüfen ob Seite bereits existiert
        if ($this->pageExists($slug)) {
            throw new Exception(sprintf(__('Seite mit Slug "%s" existiert bereits', 'city-pages-generator'), $slug));
        }

        // Seitentitel generieren - verwende benutzerdefinierten Pattern falls vorhanden
        $title_pattern = isset($replacements['page_title_pattern']) ? $replacements['page_title_pattern'] : $source_page->post_title;
        $title = $this->text_replacer->replaceText($title_pattern, $city, $replacements);

        // Neue Seite erstellen (gleicher Post-Typ wie Quellseite)
        $new_page_data = [
            'post_title' => $title,
            'post_content' => $source_page->post_content,
            'post_status' => 'draft',
            'post_type' => $source_page->post_type, // Gleicher Typ wie Quellseite
            'post_name' => $slug,
            'post_author' => get_current_user_id(),
            'post_parent' => $source_page->post_parent,
            'menu_order' => $source_page->menu_order
        ];

        $new_page_id = wp_insert_post($new_page_data);
        
        if (is_wp_error($new_page_id)) {
            throw new Exception(__('Fehler beim Erstellen der Seite', 'city-pages-generator'));
        }

        // Post-Meta kopieren und anpassen
        $this->copyAndUpdatePostMeta($source_page->ID, $new_page_id, $city, $replacements);

        // Builder-spezifische Daten kopieren und anpassen
        if ($this->selected_builder === 'bricks') {
            $this->bricks_handler->copyBricksData($source_page->ID, $new_page_id, $city, $replacements);
            $this->bricks_handler->setBricksSEOMeta($new_page_id, $city, $replacements, $title);
        } else {
            $this->copyAndUpdateElementorData($source_page->ID, $new_page_id, $city, $replacements);
        }

		// Quellseiten-Kontext kompakt aufbauen (ohne Verhaltensänderung)
		$ctx_replacements = $this->buildContextReplacements($source_page, $replacements);

        // SEO-Meta setzen
        $this->setSEOMeta($new_page_id, $city, $ctx_replacements, $title);

        // In Tracking-Tabelle eintragen
        $this->addToTrackingTable($new_page_id, $source_page->ID, $city, $slug);

        return [
            'success' => true,
            'page_id' => $new_page_id,
            'city' => $city['name'],
            'title' => $title,
            'slug' => $slug,
            'edit_url' => get_edit_post_link($new_page_id),
            'preview_url' => get_preview_post_link($new_page_id),
        ];
    }


    /**
     * Prüft ob eine Seite bereits existiert
     * @param string $slug
     * @return bool
     */
    private function pageExists($slug) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page', 'post')",
            $slug
        ));
        
        return $count > 0;
    }

    /**
     * Generiert einen Slug basierend auf dem Pattern
     * @param string $pattern
     * @param array $city
     * @param array $replacements
     * @return string
     */
    private function generateSlug($pattern, $city, $replacements) {
        $slug = $this->text_replacer->replaceText($pattern, $city, $replacements);
        return sanitize_title($slug);
    }

    /**
     * Prüft ob eine Seite für den gewählten Builder geeignet ist
     * @param int $page_id
     * @return bool
     */
    private function isValidBuilderPage($page_id) {
        if ($this->selected_builder === 'bricks') {
            return $this->bricks_handler->isBricksPage($page_id);
        } else {
            return $this->isElementorPage($page_id);
        }
    }

    /**
     * Prüft ob eine Seite eine Elementor-Seite ist
     * @param int $page_id
     * @return bool
     */
    private function isElementorPage($page_id) {
        $elementor_mode = get_post_meta($page_id, '_elementor_edit_mode', true);
        return $elementor_mode === 'builder';
    }

    /**
     * Kopiert und aktualisiert Post-Meta
     * @param int $source_id
     * @param int $target_id
     * @param array $city
     * @param array $replacements
     */
    private function copyAndUpdatePostMeta($source_id, $target_id, $city, $replacements) {
        $meta_data = get_post_meta($source_id);
        
        foreach ($meta_data as $key => $values) {
            foreach ($values as $value) {
                $value = maybe_unserialize($value);
                
                // Text-Felder ersetzen und IMG-ALT ergänzen
                if (is_string($value)) {
                    $value = $this->text_replacer->replaceText($value, $city, $replacements);
                    $altText = $this->buildImageAltText($city, $replacements);
                    if ($altText !== '') {
                        $value = $this->addImageAltAttributes($value, $altText);
                    }
                } elseif (is_array($value)) {
                    $value = $this->text_replacer->replaceInArray($value, $city, $replacements);
                }
                
                update_post_meta($target_id, $key, $value);
            }
        }
    }

    /**
     * Kopiert und aktualisiert Elementor-Daten
     * @param int $source_id
     * @param int $target_id
     * @param array $city
     * @param array $replacements
     */
    private function copyAndUpdateElementorData($source_id, $target_id, $city, $replacements) {
        // Elementor-Daten abrufen
        $elementor_data = get_post_meta($source_id, '_elementor_data', true);
        
        if (!empty($elementor_data)) {
            $elementor_data = json_decode($elementor_data, true);
            
            if (is_array($elementor_data)) {
                // Rekursiv durch Elementor-Daten gehen und Texte ersetzen
                $elementor_data = $this->processElementorData($elementor_data, $city, $replacements);
                
                // Aktualisierte Daten speichern
                update_post_meta($target_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
                
                // Elementor-Meta-Daten kopieren
                $elementor_page_settings = get_post_meta($source_id, '_elementor_page_settings', true);
                if ($elementor_page_settings) {
                    $elementor_page_settings = $this->text_replacer->replaceInArray($elementor_page_settings, $city, $replacements);
                    update_post_meta($target_id, '_elementor_page_settings', $elementor_page_settings);
                }
                
                // Version und andere Metadaten
                update_post_meta($target_id, '_elementor_edit_mode', 'builder');
                update_post_meta($target_id, '_elementor_version', get_post_meta($source_id, '_elementor_version', true));
                update_post_meta($target_id, '_elementor_pro_version', get_post_meta($source_id, '_elementor_pro_version', true));

                // Seiten-Template übernehmen (Elementor Canvas o.ä.)
                $template = get_post_meta($source_id, '_wp_page_template', true);
                if (!empty($template)) {
                    update_post_meta($target_id, '_wp_page_template', $template);
                }

                // Elementor CSS/Files für neue Seite neu generieren (falls Elementor Funktionen vorhanden)
                if (class_exists('Elementor\Plugin')) {
                    try {
                        \Elementor\Plugin::$instance->files_manager->clear_cache();
                        if (method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache_for_post')) {
                            \Elementor\Plugin::$instance->files_manager->clear_cache_for_post($target_id);
                        }
                        if (class_exists('Elementor\Plugin') && method_exists(\Elementor\Plugin::instance()->assets_manager, 'clear_cache')) {
                            \Elementor\Plugin::instance()->assets_manager->clear_cache();
                        }
                    } catch (\Throwable $e) {
                        error_log('CPG Elementor CSS Regeneration Error: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Verarbeitet Elementor-Daten rekursiv
     * @param array $data
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function processElementorData($data, $city, $replacements) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->processElementorData($value, $city, $replacements);
            } elseif (is_string($value)) {
                $replaced = $this->text_replacer->replaceText($value, $city, $replacements);
                $altText = $this->buildImageAltText($city, $replacements);
                $data[$key] = $altText !== '' ? $this->addImageAltAttributes($replaced, $altText) : $replaced;
            }
        }
        
        return $data;
    }

    // Hinweis: Kein Update von _wp_attachment_image_alt, damit jedes Bild je Seite
    // den korrekten seitenbezogenen ALT behält und nicht global überschrieben wird.

    /**
     * Setzt SEO-Meta-Daten
     * @param int $page_id
     * @param array $city
     * @param array $replacements
     * @param string $title
     */
    private function setSEOMeta($page_id, $city, $replacements, $title) {
        // KI-generierte Meta-Description falls verfügbar
        $meta_description = $this->getMetaDescription($city, $replacements);
        
        // KI-generierte Keywords falls verfügbar
        $keywords = $this->getKeywords($city, $replacements);
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            // Keywords zusammenführen (KI + manuelle)
            $all_keywords = $this->mergeKeywords($keywords, $city, $replacements);

            update_post_meta($page_id, '_yoast_wpseo_title', $title);
            update_post_meta($page_id, '_yoast_wpseo_metadesc', $meta_description);
            // Yoast unterstützt in der freien Version primär ein Fokus‑Keyword
            if (!empty($all_keywords)) {
                update_post_meta($page_id, '_yoast_wpseo_focuskw', $all_keywords[0]);
            }
        }
        
        // RankMath SEO
        if (defined('RANK_MATH_VERSION')) {
            // Keywords zusammenführen (KI + manuelle)
            $all_keywords = $this->mergeKeywords($keywords, $city, $replacements);

            update_post_meta($page_id, 'rank_math_title', $title);
            update_post_meta($page_id, 'rank_math_description', $meta_description);

            // Rank Math: Erstes Keyword (mit Stern) + Keyword-Feld + weitere manuelle Keywords aus dem KI-Tab
            $rank_math_keywords = [];
            if (!empty($replacements['auto_theme'])) {
                $rank_math_keywords[] = $replacements['auto_theme'] . ' in ' . $city['name'];
            } elseif (!empty($all_keywords)) {
                $rank_math_keywords[] = $all_keywords[0];
            }

            // Keyword-Feld (theme_keyword) hinzufügen
            if (!empty($replacements['theme_keyword'])) {
                $rank_math_keywords[] = sanitize_text_field($replacements['theme_keyword']);
            }

            if (!empty($replacements['keywords']) && is_array($replacements['keywords'])) {
                $extra = array_filter(array_map('sanitize_text_field', $replacements['keywords']));
                $rank_math_keywords = array_merge($rank_math_keywords, $extra);
            }

            // Deduplizieren und leere entfernen
            $rank_math_keywords = array_values(array_filter(array_unique($rank_math_keywords), function($v){ return trim($v) !== ''; }));

            // Fallback: wenn leer, nichts speichern; sonst kommasepariert setzen
            if (!empty($rank_math_keywords)) {
                update_post_meta($page_id, 'rank_math_focus_keyword', implode(',', $rank_math_keywords));
            } else {
                delete_post_meta($page_id, 'rank_math_focus_keyword');
            }
        }
        
        // Standard WordPress Meta (falls kein SEO-Plugin)
        if (!defined('WPSEO_VERSION') && !defined('RANK_MATH_VERSION')) {
            // Keywords zusammenführen (KI + manuelle)
            $all_keywords = $this->mergeKeywords($keywords, $city, $replacements);
            $keywords_string = implode(', ', $all_keywords);
            
            update_post_meta($page_id, '_cpg_meta_description', $meta_description);
            update_post_meta($page_id, '_cpg_keywords', $keywords_string);
        }
    }

    /**
     * Fügt Seite zur Tracking-Tabelle hinzu
     * @param int $page_id
     * @param int $source_page_id
     * @param array $city
     * @param string $slug
     */
    private function addToTrackingTable($page_id, $source_page_id, $city, $slug) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpg_generated_pages';
        
        $wpdb->insert(
            $table_name,
            [
                'page_id' => $page_id,
                'source_page_id' => $source_page_id,
                'city_name' => $city['name'],
                'state' => $city['state'] ?? '',
                'country' => $city['country'] ?? 'Deutschland',
                'slug' => $slug,
                'status' => 'draft'
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
    }

    /**
     * Löscht generierte Seite und Tracking-Eintrag
     * @param int $page_id
     * @return bool
     */
    public function deletePage($page_id) {
        global $wpdb;
        
        // Seite löschen
        $result = wp_delete_post($page_id, true);
        
        if ($result) {
            // Tracking-Eintrag löschen
            $table_name = $wpdb->prefix . 'cpg_generated_pages';
            $wpdb->delete($table_name, ['page_id' => $page_id], ['%d']);
            
            return true;
        }
        
        return false;
    }

    /**
     * Aktualisiert Status in Tracking-Tabelle
     * @param int $page_id
     * @param string $status
     */
    public function updatePageStatus($page_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpg_generated_pages';
        
        $wpdb->update(
            $table_name,
            ['status' => $status],
            ['page_id' => $page_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Generiert oder holt Meta-Description (KI oder Fallback)
     * @param array $city
     * @param array $replacements
     * @return string
     */
    private function getMetaDescription($city, $replacements) {
        // KI-generierte Meta-Description falls verfügbar
        if ($this->gemini_api) {
            try {
                $ki_description = $this->gemini_api->generateMetaDescription($city, $replacements);
                if (!empty($ki_description)) {
                    // Sicherheit: Falls {leistung} noch wörtlich enthalten ist, hier ersetzen
                    $service_template_out = isset($replacements['service_template']) ? trim((string)$replacements['service_template']) : '';
                    if ($service_template_out !== '') {
                        $ki_description = preg_replace('/\{\s*leistung\s*\}/iu', $service_template_out, $ki_description);
                    }
                    return $ki_description;
                }
            } catch (Exception $e) {
                error_log('CPG Gemini Meta Description Error: ' . $e->getMessage());
            }
        }

        // Fallback deaktiviert: keine automatische Meta-Description setzen
        return '';
    }

    /**
     * Generiert oder holt Keywords (KI oder Fallback)
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function getKeywords($city, $replacements) {
		// Nur manuelle Keywords verwenden; keine KI-Aufrufe mehr
		$manual = [];
		if (!empty($replacements['keywords']) && is_array($replacements['keywords'])) {
			$manual = array_filter(array_map('sanitize_text_field', $replacements['keywords']));
		}
		if (!empty($replacements['theme_keyword'])) {
			$manual[] = sanitize_text_field($replacements['theme_keyword']);
		}
		return $this->deduplicateKeywords($manual);
    }

    /**
     * Führt KI- und manuelle Keywords zusammen
     * @param array $ki_keywords
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function mergeKeywords($ki_keywords, $city, $replacements) {
        $all_keywords = [];
        
        // KI-Keywords hinzufügen
        if (!empty($ki_keywords) && is_array($ki_keywords)) {
            $all_keywords = array_merge($all_keywords, $ki_keywords);
        }
        
        // Manuelle Keywords hinzufügen
        if (!empty($replacements['keywords']) && is_array($replacements['keywords'])) {
            $extra_keywords = array_filter(array_map('sanitize_text_field', $replacements['keywords']));
            $all_keywords = array_merge($all_keywords, $extra_keywords);
        }
        
        // Optional: Einzelnes Feld-Keyword hinzufügen
        if (!empty($replacements['theme_keyword'])) {
            $all_keywords[] = $replacements['theme_keyword'];
        }
        
		return $this->deduplicateKeywords($all_keywords);
    }

	/**
	 * Baut einen kompakten Kontext für Ersetzungen auf
	 * - Titel der Quelle
	 * - Bereinigter Inhalt (max. 2000 Zeichen)
	 * Keine Verhaltensänderung gegenüber dem bisherigen Inline-Code.
	 * @param WP_Post $source_page
	 * @param array $replacements
	 * @return array
	 */
	private function buildContextReplacements($source_page, $replacements) {
		$ctx = $replacements;
		$ctx['source_title'] = $source_page->post_title;
		$clean_content = wp_strip_all_tags($source_page->post_content);
		if (mb_strlen($clean_content) > 2000) {
			$clean_content = mb_substr($clean_content, 0, 2000);
		}
		$ctx['source_content'] = $clean_content;
		return $ctx;
	}

	/**
	 * Entfernt leere Einträge und dedupliziert Keywords case-insensitiv.
	 * @param array $keywords
	 * @return array
	 */
	private function deduplicateKeywords($keywords) {
		$lower_seen = [];
		$unique = [];
		foreach ((array)$keywords as $kw) {
			$key = mb_strtolower(trim((string)$kw));
			if ($key === '') continue;
			if (!isset($lower_seen[$key])) {
				$lower_seen[$key] = true;
				$unique[] = $kw;
			}
		}
		return $unique;
	}

    /**
     * Baut den ALT-Text für Bilder aus Thema/Keyword und Stadt.
     * Bevorzugt das spezifische Keyword, fällt auf auto_theme zurück.
     */
    private function buildImageAltText($city, $replacements) {
        $theme = '';
        if (!empty($replacements['theme_keyword'])) {
            $theme = sanitize_text_field($replacements['theme_keyword']);
        } elseif (!empty($replacements['auto_theme'])) {
            $theme = sanitize_text_field($replacements['auto_theme']);
        }
        $cityName = isset($city['name']) ? trim((string)$city['name']) : '';
        if ($theme === '' || $cityName === '') {
            return '';
        }
        return $theme . ' in ' . $cityName;
    }

    /**
     * Ergänzt in HTML alle <img>-Tags um ein alt="...", falls fehlend oder leer.
     * Bestehende, nicht-leere ALT-Texte werden nicht überschrieben.
     */
    private function addImageAltAttributes($html, $altText) {
        if (!is_string($html) || $html === '') return $html;
        $callback = function($m) use ($altText) {
            $tag = $m[0];
            // Wenn alt vorhanden und nicht leer, nichts ändern
            if (preg_match('/\balt\s*=\s*(["\'])\s*([^\"\']+)\1/i', $tag)) {
                return $tag;
            }
            // alt fehlt oder ist leer -> einfügen oder ersetzen
            if (preg_match('/\balt\s*=\s*(["\'])\s*\1/i', $tag)) {
                // alt="" -> mit Text füllen
                return preg_replace('/\balt\s*=\s*(["\'])\s*\1/i', 'alt="$altText"', $tag, 1);
            }
            // alt fehlt -> vor dem schließenden > einfügen
            $insert = ' alt="' . esc_attr($altText) . '"';
            if (substr($tag, -2) === '/>') {
                return substr($tag, 0, -2) . $insert . '/>';
            }
            return substr($tag, 0, -1) . $insert . '>';
        };
        return preg_replace_callback('/<img\b[^>]*>/i', $callback, $html);
    }

    /**
     * Generiert KI-Inhalte für eine Seite
     * @param int $page_id
     * @param array $city
     * @param array $replacements
     * @return array
     */
    public function generateKIContent($page_id, $city, $replacements) {
        if (!$this->gemini_api) {
            return ['error' => 'Gemini API nicht verfügbar'];
        }

        $results = [];
        
        try {
            // Meta-Description
            $results['meta_description'] = $this->gemini_api->generateMetaDescription($city, $replacements);
            
            // Keywords
            $manual = [];
            if (!empty($replacements['keywords']) && is_array($replacements['keywords'])) {
                $manual = array_filter(array_map('sanitize_text_field', $replacements['keywords']));
            }
            if (!empty($replacements['theme_keyword'])) {
                $manual[] = sanitize_text_field($replacements['theme_keyword']);
            }
            $results['keywords'] = array_values(array_filter(array_unique($manual), function($v){ return trim($v) !== ''; }));
            
            // Entfernt: intro_text, faq_text, cta_text
            
            // Als Post-Meta speichern
            update_post_meta($page_id, '_cpg_ki_content', $results);
            
            return $results;
            
        } catch (Exception $e) {
            error_log('CPG KI Content Generation Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
} 