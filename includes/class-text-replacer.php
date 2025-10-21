<?php
/**
 * Text Replacer Klasse
 * 
 * Diese Klasse verwaltet das Ersetzen von Platzhaltern in Texten:
 * - Standard-Platzhalter ({{stadt}}, {{thema}}, {{bundesland}}, etc.)
 * - Benutzerdefinierte Platzhalter mit und ohne KI-Adaptierung
 * - Spezielle deutsche Grammatik (Genitiv, Präpositionen)
 * - Regionale Anpassungen (bayerisch, schwäbisch, etc.)
 * - Lokale Besonderheiten (Wahrzeichen, Branchen)
 * - Validierung und Bereinigung von Platzhaltern
 * 
 * Die Klasse unterstützt sowohl einfache Text-Ersetzung als auch
 * KI-generierte Anpassungen für natürlichere Texte.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Text_Replacer {

    /**
     * Ersetzt Platzhalter in einem Text
     * @param string $text
     * @param array $city
     * @param array $replacements
     * @return string
     */
    public function replaceText($text, $city, $replacements = []) {
        if (empty($text) || !is_string($text)) {
            return $text;
        }

        // Standard-Platzhalter definieren
        $placeholders = $this->getPlaceholders($city, $replacements);
        
        // Ersetzung durchführen
        $text = str_replace(array_keys($placeholders), array_values($placeholders), $text);
        
        // Spezielle Verarbeitungen
        $text = $this->processSpecialReplacements($text, $city, $replacements);
        
        return $text;
    }

    /**
     * Ersetzt Platzhalter in einem Array rekursiv
     * @param array $array
     * @param array $city
     * @param array $replacements
     * @return array
     */
    public function replaceInArray($array, $city, $replacements = []) {
        if (!is_array($array)) {
            return $array;
        }

        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = $this->replaceText($value, $city, $replacements);
            } elseif (is_array($value)) {
                $array[$key] = $this->replaceInArray($value, $city, $replacements);
            }
        }

        return $array;
    }

    /**
     * Generiert Platzhalter-Array
     * @param array $city
     * @param array $replacements
     * @return array
     */
    private function getPlaceholders($city, $replacements) {
        $placeholders = [
            '{{stadt}}' => $city['name'] ?? '',
            '{{Stadt}}' => ucfirst($city['name'] ?? ''),
            '{{STADT}}' => strtoupper($city['name'] ?? ''),
            '{{bundesland}}' => $city['state'] ?? '',
            '{{Bundesland}}' => ucfirst($city['state'] ?? ''),
            '{{BUNDESLAND}}' => strtoupper($city['state'] ?? ''),
            '{{land}}' => $city['country'] ?? 'Deutschland',
            '{{Land}}' => ucfirst($city['country'] ?? 'Deutschland'),
            '{{LAND}}' => strtoupper($city['country'] ?? 'Deutschland'),
        ];

        // Thema-Platzhalter (kommt automatisch aus Quellseiten-Titel)
        $autoTheme = $replacements['auto_theme'] ?? '';
        if ($autoTheme) {
            $placeholders['{{thema}}'] = $autoTheme;
            $placeholders['{{Thema}}'] = ucfirst($autoTheme);
            $placeholders['{{THEMA}}'] = strtoupper($autoTheme);
        }

        // Keyword-Platzhalter (vom Benutzer eingegeben)
        $keyword = $replacements['theme_keyword'] ?? '';
        if (!$keyword) {
            // Fallback: Keyword = Thema
            $keyword = $autoTheme;
        }
        if ($keyword) {
            $placeholders['{{keyword}}'] = $keyword;
            $placeholders['{{Keyword}}'] = ucfirst($keyword);
            $placeholders['{{KEYWORD}}'] = strtoupper($keyword);
        }


        // Postleitzahl (falls verfügbar)
        if (isset($city['postal_code'])) {
            $placeholders['{{plz}}'] = $city['postal_code'];
        }

        // Einwohnerzahl formatiert
        if (isset($city['population'])) {
            $placeholders['{{einwohner}}'] = number_format($city['population'], 0, ',', '.');
        }

        // Entfernung (falls verfügbar)
        if (isset($city['distance'])) {
            $placeholders['{{entfernung}}'] = round($city['distance'], 1) . ' km';
        }

        // Einfache Platzhalter ohne KI zuerst hinzufügen (können durch KI-Varianten überschrieben werden)
        $simple_rows_no_ki = get_option('cpg_simple_placeholders_no_ki', []);
        if (is_array($simple_rows_no_ki)) {
            foreach ($simple_rows_no_ki as $row) {
                $name = isset($row['name']) ? trim((string)$row['name']) : '';
                $text = isset($row['text']) ? (string)$row['text'] : '';
                if ($name !== '' && $text !== '') {
                    $placeholders['{{' . $name . '}}'] = $text;
                }
            }
        }

        // Einfache benutzerdefinierte Platzhalter MIT KI-Adaptierung (falls aktiviert)
        // Erwartetes Format in Option: [ [ 'name' => 'angebot', 'text' => 'Ihr Angebotstext' ], ... ]
        $simple_rows = get_option('cpg_simple_placeholders', []);
        if (is_array($simple_rows)) {
            $use_ki = (bool) get_option('cpg_gemini_enabled', false);
            $gemini = null;
            if ($use_ki) {
                try { $gemini = new CPG_Gemini_API(); } catch (Exception $e) { $gemini = null; }
            }
            foreach ($simple_rows as $row) {
                $name = isset($row['name']) ? trim((string)$row['name']) : '';
                $text = isset($row['text']) ? (string)$row['text'] : '';
                if ($name === '' || $text === '') { continue; }

                $finalText = $text;
                if ($gemini) {
                    try {
                        $adapted = $gemini->generateAdaptedText($text, $city, $replacements);
                        if (is_string($adapted) && $adapted !== '') {
                            $finalText = $adapted;
                        }
                    } catch (Exception $e) {
                        // Fallback auf Originaltext
                        $finalText = $text;
                    }
                }

                // KI-Platzhalter NUR setzen, wenn noch kein Wert aus "ohne KI" vorhanden ist
                // So behalten manuell gepflegte Vorlagen Vorrang
                $phKey = '{{' . $name . '}}';
                if (!array_key_exists($phKey, $placeholders)) {
                    $placeholders[$phKey] = $finalText;
                }
            }
        }

        return $placeholders;
    }

    /**
     * Verarbeitet spezielle Ersetzungen
     * @param string $text
     * @param array $city
     * @param array $replacements
     * @return string
     */
    private function processSpecialReplacements($text, $city, $replacements) {
        // Genitiv-Formen für deutsche Städte
        $text = $this->processGenitiveForms($text, $city);
        
        // Präpositionen mit Städten
        $text = $this->processPrepositions($text, $city);
        
        // Lokale Anpassungen
        $text = $this->processLocalAdaptations($text, $city, $replacements);
        
        return $text;
    }

    /**
     * Verarbeitet Genitiv-Formen
     * @param string $text
     * @param array $city
     * @return string
     */
    private function processGenitiveForms($text, $city) {
        $city_name = $city['name'] ?? '';
        
        if (empty($city_name)) {
            return $text;
        }

        // Genitiv-Regeln für deutsche Städte
        $genitive = $this->getGenitive($city_name);
        
        $text = str_replace('{{stadt_genitiv}}', $genitive, $text);
        $text = str_replace('{{Stadt_genitiv}}', ucfirst($genitive), $text);
        
        return $text;
    }

    /**
     * Generiert Genitiv-Form einer Stadt
     * @param string $city_name
     * @return string
     */
    private function getGenitive($city_name) {
        // Spezielle Städte mit unregelmäßigen Genitivformen
        $special_cases = [
            'München' => 'Münchens',
            'Köln' => 'Kölns',
            'Nürnberg' => 'Nürnbergs',
            'Würzburg' => 'Würzburgs',
            'Augsburg' => 'Augsburgs',
            'Regensburg' => 'Regensburgs',
            'Freiburg' => 'Freiburgs',
            'Heidelberg' => 'Heidelbergs',
            'Magdeburg' => 'Magdeburgs',
            'Brandenburg' => 'Brandenburgs'
        ];

        if (isset($special_cases[$city_name])) {
            return $special_cases[$city_name];
        }

        // Standardregel: -s anhängen
        $last_char = mb_substr($city_name, -1);
        
        // Wenn Stadt auf s, ß, x, z endet, nur Apostroph
        if (in_array($last_char, ['s', 'ß', 'x', 'z'])) {
            return $city_name . "'";
        }
        
        return $city_name . 's';
    }

    /**
     * Verarbeitet Präpositionen mit Städten
     * @param string $text
     * @param array $city
     * @return string
     */
    private function processPrepositions($text, $city) {
        $city_name = $city['name'] ?? '';
        
        if (empty($city_name)) {
            return $text;
        }

        // "in" + Stadt
        $text = str_replace('{{in_stadt}}', 'in ' . $city_name, $text);
        $text = str_replace('{{In_stadt}}', 'In ' . $city_name, $text);
        
        // "aus" + Stadt
        $text = str_replace('{{aus_stadt}}', 'aus ' . $city_name, $text);
        $text = str_replace('{{Aus_stadt}}', 'Aus ' . $city_name, $text);
        
        // "nach" + Stadt
        $text = str_replace('{{nach_stadt}}', 'nach ' . $city_name, $text);
        $text = str_replace('{{Nach_stadt}}', 'Nach ' . $city_name, $text);
        
        // "für" + Stadt
        $text = str_replace('{{für_stadt}}', 'für ' . $city_name, $text);
        $text = str_replace('{{Für_stadt}}', 'Für ' . $city_name, $text);

        return $text;
    }

    /**
     * Verarbeitet lokale Anpassungen
     * @param string $text
     * @param array $city
     * @param array $replacements
     * @return string
     */
    private function processLocalAdaptations($text, $city, $replacements) {
        $city_name = $city['name'] ?? '';
        $state = $city['state'] ?? '';
        
        // Regionale Begriffe
        $regional_terms = $this->getRegionalTerms($state);
        
        foreach ($regional_terms as $placeholder => $term) {
            $text = str_replace($placeholder, $term, $text);
        }
        
        // Lokale Besonderheiten
        $text = $this->processLocalSpecialties($text, $city_name, $state);
        
        return $text;
    }

    /**
     * Liefert regionale Begriffe
     * @param string $state
     * @return array
     */
    private function getRegionalTerms($state) {
        $terms = [
            'Bayern' => [
                '{{region_begriff}}' => 'bayerisch',
                '{{region_adjektiv}}' => 'bayerische',
                '{{region_spezialität}}' => 'Weißwurst und Brezn'
            ],
            'Baden-Württemberg' => [
                '{{region_begriff}}' => 'schwäbisch',
                '{{region_adjektiv}}' => 'schwäbische',
                '{{region_spezialität}}' => 'Spätzle und Maultaschen'
            ],
            'Nordrhein-Westfalen' => [
                '{{region_begriff}}' => 'rheinisch',
                '{{region_adjektiv}}' => 'rheinische',
                '{{region_spezialität}}' => 'Himmel un Ääd'
            ],
            'Niedersachsen' => [
                '{{region_begriff}}' => 'niedersächsisch',
                '{{region_adjektiv}}' => 'niedersächsische',
                '{{region_spezialität}}' => 'Grünkohl mit Pinkel'
            ]
        ];

        return $terms[$state] ?? [
            '{{region_begriff}}' => 'regional',
            '{{region_adjektiv}}' => 'regionale',
            '{{region_spezialität}}' => 'lokale Spezialitäten'
        ];
    }

    /**
     * Verarbeitet lokale Besonderheiten
     * @param string $text
     * @param string $city_name
     * @param string $state
     * @return string
     */
    private function processLocalSpecialties($text, $city_name, $state) {
        // Bekannte Sehenswürdigkeiten/Wahrzeichen
        $landmarks = $this->getLandmarks($city_name);
        
        if ($landmarks) {
            $text = str_replace('{{wahrzeichen}}', $landmarks, $text);
            $text = str_replace('{{Wahrzeichen}}', ucfirst($landmarks), $text);
        }
        
        // Wirtschaftszweige der Region
        $industries = $this->getLocalIndustries($city_name, $state);
        
        if ($industries) {
            $text = str_replace('{{branchen}}', $industries, $text);
            $text = str_replace('{{Branchen}}', ucfirst($industries), $text);
        }
        
        return $text;
    }

    /**
     * Liefert bekannte Wahrzeichen einer Stadt
     * @param string $city_name
     * @return string
     */
    private function getLandmarks($city_name) {
        $landmarks = [
            'München' => 'Frauenkirche und Marienplatz',
            'Hamburg' => 'Speicherstadt und Elbphilharmonie',
            'Berlin' => 'Brandenburger Tor und Fernsehturm',
            'Köln' => 'Kölner Dom',
            'Frankfurt am Main' => 'Skyline und Römerberg',
            'Stuttgart' => 'Fernsehturm und Staatsoper',
            'Düsseldorf' => 'Rheinturm und Königsallee',
            'Dortmund' => 'Deutsches Fußballmuseum und Phoenix Lake',
            'Essen' => 'Zeche Zollverein',
            'Leipzig' => 'Völkerschlachtdenkmal und Thomaskirche',
            'Bremen' => 'Bremer Stadtmusikanten und Rathaus',
            'Dresden' => 'Frauenkirche und Zwinger',
            'Hannover' => 'Herrenhäuser Gärten und Neues Rathaus',
            'Nürnberg' => 'Kaiserburg und Hauptkirche'
        ];

        return $landmarks[$city_name] ?? '';
    }

    /**
     * Liefert lokale Industrien/Branchen
     * @param string $city_name
     * @param string $state
     * @return string
     */
    private function getLocalIndustries($city_name, $state) {
        $industries = [
            'München' => 'Automobilindustrie und IT',
            'Hamburg' => 'Logistik und Medien',
            'Stuttgart' => 'Automobilindustrie und Maschinenbau',
            'Frankfurt am Main' => 'Finanzwesen und Logistik',
            'Köln' => 'Medien und Chemie',
            'Düsseldorf' => 'Mode und Stahl',
            'Wolfsburg' => 'Automobilindustrie',
            'Ingolstadt' => 'Automobilindustrie',
            'Leverkusen' => 'Chemie und Pharma'
        ];

        if (isset($industries[$city_name])) {
            return $industries[$city_name];
        }

        // Fallback basierend auf Bundesland
        $state_industries = [
            'Bayern' => 'Technologie und Maschinenbau',
            'Baden-Württemberg' => 'Automobilindustrie und Hightech',
            'Nordrhein-Westfalen' => 'Industrie und Logistik',
            'Niedersachsen' => 'Landwirtschaft und Industrie',
            'Hessen' => 'Finanzwesen und Logistik'
        ];

        return $state_industries[$state] ?? 'lokale Wirtschaft';
    }

    /**
     * Bereinigt Text von nicht ersetzten Platzhaltern
     * @param string $text
     * @return string
     */
    public function cleanupUnreplacedPlaceholders($text) {
        // Entfernt alle verbleibenden {{...}} Platzhalter
        $text = preg_replace('/\{\{[^}]+\}\}/', '', $text);
        
        // Mehrfache Leerzeichen entfernen
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }

    /**
     * Validiert Platzhalter-Syntax
     * @param string $text
     * @return array Array mit gefundenen Platzhaltern
     */
    public function validatePlaceholders($text) {
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
        
        $valid_placeholders = [
            'stadt', 'Stadt', 'STADT',
            'bundesland', 'Bundesland', 'BUNDESLAND',
            'land', 'Land', 'LAND',
            'thema', 'Thema', 'THEMA',
            'keyword', 'Keyword', 'KEYWORD',
            'plz', 'einwohner', 'entfernung',
            'stadt_genitiv', 'Stadt_genitiv',
            'in_stadt', 'In_stadt',
            'aus_stadt', 'Aus_stadt',
            'nach_stadt', 'Nach_stadt',
            'für_stadt', 'Für_stadt',
            'region_begriff', 'region_adjektiv', 'region_spezialität',
            'wahrzeichen', 'Wahrzeichen',
            'branchen', 'Branchen'
        ];

        // Dynamische einfache Platzhalter (aus Optionen) ebenfalls als gültig markieren
        $simple_rows = get_option('cpg_simple_placeholders', []);
        if (is_array($simple_rows)) {
            foreach ($simple_rows as $row) {
                if (!empty($row['name'])) {
                    $valid_placeholders[] = (string)$row['name'];
                }
            }
        }

        $found_placeholders = $matches[1] ?? [];
        $invalid_placeholders = [];

        foreach ($found_placeholders as $placeholder) {
            if (!in_array($placeholder, $valid_placeholders)) {
                $invalid_placeholders[] = $placeholder;
            }
        }

        return [
            'all' => $found_placeholders,
            'invalid' => $invalid_placeholders,
            'valid' => array_intersect($found_placeholders, $valid_placeholders)
        ];
    }
} 