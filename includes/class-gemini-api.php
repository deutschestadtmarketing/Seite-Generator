<?php
/**
 * Gemini API Klasse
 * 
 * Diese Klasse verwaltet die Integration mit Google Gemini für KI-Textgenerierung:
 * - Generiert SEO-optimierte Meta-Beschreibungen für Städte
 * - Erstellt angepasste Texte basierend auf Vorlagen
 * - Implementiert intelligentes Caching für bessere Performance
 * - Unterstützt verschiedene Gemini-Modelle (Flash, Pro, etc.)
 * - Fallback-Mechanismen bei API-Fehlern
 * 
 * Google Gemini ist Googles KI-Modell für Textgenerierung und -anpassung.
 * Es wird verwendet um lokalisierte, SEO-optimierte Inhalte zu erstellen.
 * 
 * Beachte: generateLocalKeywords liefert nur noch manuelle Keywords (kein KI‑Call).
 * Dies ist ein Lesbarkeits-Refactor ohne funktionale Änderungen.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Gemini_API {

    /**
     * Google Gemini API-Key für authentifizierte Anfragen
     * @var string
     */
    private $api_key;
    
    /**
     * API-URL wird dynamisch ermittelt - probiert mehrere gültige Endpunkte nacheinander
     * @var string
     */
    private $api_url = '';
    
    /**
     * Cache-Zeit in Sekunden für KI-generierte Inhalte
     * @var int
     */
    private $cache_time;
    
    /**
     * Letzter Fehler für Debugging-Zwecke
     * @var string
     */
    private $last_error = '';

    /**
     * Konstruktor - lädt API-Konfiguration aus WordPress-Optionen
     */
    public function __construct() {
        $this->api_key = get_option('cpg_gemini_api_key', '');           // Gemini API-Key aus Einstellungen
        $this->cache_time = get_option('cpg_gemini_cache_time', 3600);   // Cache-Zeit (Standard: 1 Stunde)
    }

    /**
     * Generiert SEO-optimierte Meta-Beschreibung für eine Stadt
     * @param array $city Stadtinformationen
     * @param array $replacements Ersetzungen
     * @return string
     */
    public function generateMetaDescription($city, $replacements = []) {
        $cache_key = 'cpg_gemini_meta_' . md5($city['name'] . serialize($replacements));
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
    
        $theme = $replacements['auto_theme'] ?? 'Service';
        $keyword = $replacements['theme_keyword'] ?? $theme;
        
        // --- HIER BEGINNT DER ANGEPASSTE PROMPT ---
    
        $prompt = <<<'PROMPT'
    ## ROLE ##
    Act as a senior performance marketing manager specializing in SERP click-through-rate (CTR) optimization for the German market.
    
    ## MISSION ##
    Your mission is to craft a compelling German meta description that stops the scroll, communicates immediate value, and persuades users searching for services in {stadt} to click our result over all competitors.
    
    ## CONTEXT & DATA ##
    * **Core Topic/Brand:** {theme}
    * **Target City:** {stadt}
    * **Target State:** {bundesland}
   

    
    ## CRITICAL EXECUTION RULES ##
    1.  **Character limit:** The final output MUST be between 130 and 150 characters to avoid being truncated by Google.
    2.  **High-CTR Formula:** Structure the description logically: Start with the primary keyword and location, present a unique selling proposition (USP), and end with a strong call-to-action (CTA).
    3.  **Active Voice & Benefits:** Use active language and focus on the customer's benefit. Instead of "We offer...", write "Get..." or "Benefit from...".
    4.  **Trust & Locality:** Clearly mention "{stadt}" to build local trust and relevance.
    5.  **Negative Constraints:**
        * Do NOT use quotation marks ("") in the description.
        * Do NOT just list keywords.
    
    ## FINAL OUTPUT ##
    Produce only the final, ready-to-use meta description string. Do not add any commentary.
    If helpful, incorporate these service/USP hints: {leistung}
    PROMPT;
    
        // Ersetzen der Platzhalter im Prompt mit den PHP-Variablen (inkl. Leistung)
        $service_template = isset($replacements['service_template']) ? trim((string)$replacements['service_template']) : '';
        $prompt = str_replace(
            ['{stadt}', '{bundesland}', '{theme}', '{keyword}', '{leistung}'],
            [
                isset($city['name']) ? $city['name'] : '',
                isset($city['state']) ? $city['state'] : '',
                $theme,
                $keyword,
                $service_template
            ],
            $prompt
        );
    
        // --- HIER ENDET DER ANGEPASSTE PROMPT ---
    
        $result = $this->makeApiCall($prompt);
        
        if ($result && !empty(trim($result))) {
            // Zuerst Leerzeichen am Anfang/Ende entfernen
            $result = trim($result);
    
            // Platzhalter aus KI-Output sicher ersetzen (falls Modell sie wörtlich ausgibt)
            $keyword_safe = trim((string)$keyword);
            if ($keyword_safe !== '') {
                $patterns = [
                    '/\[\s*primary\s*keyword\s*\]/iu',
                    '/\{\s*keyword\s*\}/iu',
                    '/\(\s*keyword\s*\)/iu'
                ];
                $result = preg_replace($patterns, $keyword_safe, $result);
            }

            // Falls {leistung} wörtlich im Output steht → mit service_template ersetzen
            $service_template_out = isset($replacements['service_template']) ? trim((string)$replacements['service_template']) : '';
            // Immer ersetzen – auch wenn leer, damit kein Platzhalter übrig bleibt
            $patterns_leistung = [
                '/\{\s*leistung\s*\}/iu',
                '/\[\s*leistung\s*\]/iu',
                '/\(\s*leistung\s*\)/iu'
            ];
            $result = preg_replace($patterns_leistung, $service_template_out, $result);

            // --- INTELLIGENTE LÄNGENKÜRZUNG ---
            if (mb_strlen($result) > 150) {
                // Auf 150 Zeichen kürzen
                $result = mb_substr($result, 0, 150);
                // Am letzten Leerzeichen abschneiden, um Worttrennungen zu vermeiden
                $result = preg_replace('/\s+\S*$/u', '', $result);
            }
            
            // Cache für 1 Stunde
            set_transient($cache_key, $result, 3600);
            return $result;
        }
    
        // Fallback
        return "{$theme} in {$city['name']}. Professionelle Beratung & Service vor Ort. Jetzt informieren!";
    }

    

    /**
     * Generiert lokale Keywords für eine Stadt
     * @param array $city Stadtinformationen
     * @param array $replacements Ersetzungen
     * @return array
     */
    public function generateLocalKeywords($city, $replacements = []) {
		// KI-Generierung entfernt – nur manuelle Keywords werden verwendet
		if (!empty($replacements['keywords']) && is_array($replacements['keywords'])) {
			$manual = array_map('sanitize_text_field', $replacements['keywords']);
			$manual = array_values(array_filter(array_map('trim', $manual), function($s){ return $s !== ''; }));
			return $manual;
		}
		return [];
    }

    

    

    /**
     * Führt API-Aufruf an Gemini durch
     * @param string $prompt
     * @return string|false
     */
    private function makeApiCall($prompt) {
        if (empty($this->api_key)) {
            $this->last_error = 'Kein API-Key konfiguriert';
            error_log('CPG Gemini API: Kein API-Key konfiguriert');
            return false;
        }

        // Versuche zuerst, ein verfügbares Modell dynamisch zu ermitteln
        $resolved = $this->resolveWorkingModelUrl();
        $candidate_urls = $resolved ?: $this->getCandidateUrls();

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];

        foreach ($candidate_urls as $url_base) {
            $url = $url_base . '?key=' . $this->api_key;

            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->last_error = $response->get_error_message();
                error_log('CPG Gemini API Error: ' . $response->get_error_message());
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $body_err = wp_remote_retrieve_body($response);
                $this->last_error = 'HTTP ' . $response_code . ' ' . $body_err;
                error_log('CPG Gemini API HTTP Error ' . $response_code . ': ' . $body_err);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->last_error = 'Ungültige JSON-Antwort';
                error_log('CPG Gemini API: Ungültige JSON-Antwort');
                continue;
            }

            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                // Erfolgreich – künftige Calls direkt über diese URL
                $this->api_url = $url_base;
                return trim($decoded['candidates'][0]['content']['parts'][0]['text']);
            }

            // Wenn wir hier landen, passte die Struktur nicht – nächsten Kandidaten probieren
            $this->last_error = 'Unerwartete Antwort-Struktur';
        }

        // Nichts hat funktioniert
        return false;
    }

    /**
     * Liefert eine Liste möglicher API-Endpoints (v1 & v1beta, verschiedene Modelle)
     * @return array
     */
    private function getCandidateUrls() {
        $bases = ['https://generativelanguage.googleapis.com/v1', 'https://generativelanguage.googleapis.com/v1beta'];
        $models = [
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-1.5-pro',
            'gemini-1.5-pro-latest',
            'gemini-1.5-flash-8b',
            'gemini-1.5-flash-8b-latest',
            // Letzter Fallback – ältere Generation
            'gemini-1.0-pro'
        ];

        $urls = [];
        foreach ($bases as $base) {
            foreach ($models as $model) {
                $urls[] = $base . '/models/' . $model . ':generateContent';
            }
        }
        return $urls;
    }

    /**
     * Fragt die Liste der verfügbaren Modelle ab und gibt bevorzugte generateContent‑Endpoints zurück
     * @return array|null
     */
    private function resolveWorkingModelUrl() {
        // Kurzer Cache in Optionen, um wiederholte ListModels zu vermeiden
        $cached = get_transient('cpg_gemini_resolved_urls');
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $bases = ['https://generativelanguage.googleapis.com/v1', 'https://generativelanguage.googleapis.com/v1beta'];
        $preferred_order = [
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-1.5-pro',
            'gemini-1.5-pro-latest',
            'gemini-1.5-flash-8b',
            'gemini-1.5-flash-8b-latest',
        ];

        $available = [];

        foreach ($bases as $base) {
            $list_url = $base . '/models?key=' . $this->api_key;
            $resp = wp_remote_get($list_url, [ 'timeout' => 20 ]);
            if (is_wp_error($resp)) {
                continue;
            }
            if (wp_remote_retrieve_response_code($resp) !== 200) {
                continue;
            }
            $body = wp_remote_retrieve_body($resp);
            $decoded = json_decode($body, true);
            if (!isset($decoded['models']) || !is_array($decoded['models'])) {
                continue;
            }
            foreach ($decoded['models'] as $model) {
                $name = isset($model['name']) ? (string)$model['name'] : '';
                $methods = isset($model['supportedGenerationMethods']) ? (array)$model['supportedGenerationMethods'] : [];
                if (!$name || !in_array('generateContent', $methods, true)) {
                    continue;
                }
                // name kommt meist als "models/<model>"
                $model_id = str_starts_with($name, 'models/') ? substr($name, 7) : $name;
                $available[$model_id] = $base . '/models/' . $model_id . ':generateContent';
            }
        }

        if (empty($available)) {
            return null;
        }

        // In gewünschter Reihenfolge sortieren und als Liste zurückgeben
        $urls = [];
        foreach ($preferred_order as $pref) {
            if (isset($available[$pref])) {
                $urls[] = $available[$pref];
            }
        }
        // Füge alle restlichen verfügbaren Modelle als Fallback hinzu
        foreach ($available as $mid => $u) {
            if (!in_array($u, $urls, true)) {
                $urls[] = $u;
            }
        }

        // 30 Minuten cachen
        set_transient('cpg_gemini_resolved_urls', $urls, 30 * MINUTE_IN_SECONDS);

        return $urls;
    }

    /**
     * Testet die API-Verbindung
     * @return array
     */
    public function testConnection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => __('Gemini API-Key nicht konfiguriert', 'city-pages-generator')
            ];
        }

        // Jede nicht-leere Antwort werten wir als gültige Verbindung
        $result = $this->makeApiCall('Sag ok.');

        if (is_string($result) && trim($result) !== '') {
            return [
                'success' => true,
                'message' => __('Gemini API-Verbindung erfolgreich', 'city-pages-generator')
            ];
        }

        $msg = $this->last_error ? ('Gemini API-Verbindung fehlgeschlagen: ' . $this->last_error) : __('Gemini API-Verbindung fehlgeschlagen', 'city-pages-generator');
        return [
            'success' => false,
            'message' => $msg
        ];
    }

    /**
     * Liefert den letzten Fehlertext
     * @return string
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Bereinigt Cache
     */
    public function clearCache() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", 'cpg_gemini_%'));
    }

    /**
     * Setzt API-Key
     * @param string $api_key
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        update_option('cpg_gemini_api_key', $api_key);
    }

    /**
     * Setzt Cache-Zeit
     * @param int $cache_time
     */
    public function setCacheTime($cache_time) {
        $this->cache_time = $cache_time;
        update_option('cpg_gemini_cache_time', $cache_time);
    }

    /**
     * Generiert angepassten Text für eine Stadt basierend auf Vorlage
     * @param string $template_text Der ursprüngliche Text aus dem Textarea
     * @param array $city Stadtinformationen
     * @param array $replacements Ersetzungen
     * @return string
     */
    public function generateAdaptedText($template_text, $city, $replacements = []) {
        if (empty($template_text)) {
            return '';
        }
    
        // Die serialize() Funktion stellt sicher, dass der Cache-Key auch bei komplexen Arrays eindeutig ist.
        $cache_key = 'cpg_gemini_adapted_' . md5($template_text . $city['name'] . serialize($replacements));
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
    
        // Variablen aus dem replacements-Array sicher extrahieren
        $theme = $replacements['auto_theme'] ?? 'Dienstleistung';
        $keyword = $replacements['theme_keyword'] ?? $theme;
        
        // BUGFIX: $extra_keywords_str definieren
        $extra_keywords = $replacements['keywords'] ?? []; // Annahme: weitere Keywords sind im Array ['keywords']
        $extra_keywords_str = !empty($extra_keywords) ? implode(', ', $extra_keywords) : '';
    
		// --- HIER BEGINNT DER NEUE, VERBESSERTE PROMPT ---
    
		// Der Prompt wird als PHP "Nowdoc" definiert, fokussiert auf reine Lokal-Anpassung ohne neue Infos.
		$prompt = <<<'PROMPT'
	Bitte formuliere den folgenden Vorlage-Text minimal um, ausschließlich auf die Stadt {stadt}, {bundesland} zugeschnitten.
	
	Strikte Regeln:
	- Verwende nur Informationen aus dem Vorlage-Text.
	- Füge keine neuen Fakten, Services, Preise, Öffnungszeiten, USPs oder Behauptungen hinzu, die nicht im Vorlage-Text stehen.
	- Mache den Stadtbezug deutlich (verwende {stadt} natürlich im Text), ohne künstlich zu wirken.
	- Erhalte Sinn, Reihenfolge der Abschnitte und ungefähre Länge des Vorlage-Texts.
	- Sprache: Deutsch (Deutschland). Keine Markdown-Formatierung. Keine Erklärungen – gib nur den finalen Text aus.
	
	Vorlage-Text:
	---
	{template_text}
	---
	PROMPT;
    
		// Ersetzen der Platzhalter im Prompt mit den PHP-Variablen (ohne Keywords/Theme)
		$prompt = str_replace(
			['{stadt}', '{bundesland}', '{template_text}'],
			[$city['name'], $city['state'], $template_text],
			$prompt
		);
    
        // --- HIER ENDET DER NEUE PROMPT ---
        
        // Annahme: Ihre API-Call-Funktion heißt makeApiCall
        $result = $this->makeApiCall($prompt); 
        
        if ($result && !empty(trim($result))) {
            set_transient($cache_key, $result, $this->cache_time);
            return $result;
        }
    
        // Fallback: Einfache Platzhalter-Ersetzung, falls die KI fehlschlägt
        $fallback = str_replace(
            ['{{stadt}}', '{{Stadt}}', '{{thema}}', '{{keyword}}', '{{bundesland}}'],
            [$city['name'], ucfirst($city['name']), $theme, $keyword, $city['state']],
            $template_text
        );
        
        return $fallback;
    }
}
