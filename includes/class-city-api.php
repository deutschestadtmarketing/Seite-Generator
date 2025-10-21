<?php
/**
 * City API Klasse
 * 
 * Diese Klasse verwaltet alle API-Aufrufe zur Ermittlung von Städten:
 * - Verwendet GeoNames API als primäre Datenquelle für Städte
 * - Implementiert Caching für bessere Performance
 * - Fallback auf OpenStreetMap Nominatim API bei GeoNames-Problemen
 * - Filtert Städte nach Einwohnerzahl und Entfernung
 * - Unterstützt verschiedene Länder und Sprachen
 * 
 * Die API ermittelt Städte in einem bestimmten Radius um einen Ausgangsort
 * und liefert detaillierte Informationen wie Einwohnerzahl, Bundesland, etc.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_City_API {

    /**
     * Cache-Präfix für WordPress Transients
     * @var string
     */
    private $cache_prefix = 'cpg_cities_';
    
    /**
     * Cache-Zeit in Sekunden (Standard: 1 Stunde)
     * @var int
     */
    private $cache_time;
    
    /**
     * GeoNames API Username für authentifizierte Anfragen
     * @var string
     */
    private $geonames_username;

    /**
     * Konstruktor - lädt Konfiguration aus WordPress-Optionen
     */
    public function __construct() {
        $this->cache_time = get_option('cpg_api_cache_time', 3600);        // Cache-Zeit aus Einstellungen
        $this->geonames_username = get_option('cpg_geonames_username', ''); // GeoNames Username aus Einstellungen
    }

    /**
     * Ermittelt Städte in der Nähe eines Ausgangsorts
     * @param string $location Ausgangsort
     * @param int $radius Radius in km
     * @param int $min_population Mindesteinwohner
     * @return array
     */
    public function getCitiesNearby($location, $radius = 50, $min_population = 50000) {
        $cache_key = $this->cache_prefix . md5($location . $radius . $min_population);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }

        $cities = [];
        
        try {
            // Erst Koordinaten des Ausgangsorts ermitteln
            $base_coords = $this->getCoordinates($location);
            
            if (!$base_coords) {
                throw new Exception(__('Ausgangsort nicht gefunden', 'city-pages-generator'));
            }

            // Städte in der Nähe suchen (nur GeoNames, kein Nominatim-Fallback mehr)
            if (empty($this->geonames_username)) {
                throw new Exception(__('GeoNames Username nicht konfiguriert', 'city-pages-generator'));
            }
            $cities = $this->getCitiesFromGeoNames($base_coords, $radius, $min_population);

            // Ergebnis cachen
            set_transient($cache_key, $cities, $this->cache_time);
            
        } catch (Exception $e) {
            error_log('CPG City API Error: ' . $e->getMessage());
            // Für Debug-Zwecke auch die Fehlermeldung zurückgeben
            return [
                'error' => $e->getMessage(),
                'debug_info' => [
                    'location' => $location,
                    'geonames_username' => !empty($this->geonames_username) ? 'set' : 'not_set',
                    'base_coords' => $base_coords ?? 'not_found'
                ]
            ];
        }

        return $cities;
    }

    /**
     * Ermittelt Koordinaten für einen Ort
     * @param string $location
     * @return array|false
     */
    private function getCoordinates($location) {
        $cache_key = 'cpg_coords_' . md5($location);
        $cached_coords = get_transient($cache_key);
        
        if ($cached_coords !== false) {
            return $cached_coords;
        }

        // Nominatim API für Geocoding verwenden
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = [
            'q' => $location,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'de', // Auf Deutschland beschränken
            'accept-language' => 'de'
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'WordPress City Pages Generator Plugin'
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return false;
        }

        $coords = [
            'lat' => floatval($data[0]['lat']),
            'lon' => floatval($data[0]['lon'])
        ];

        set_transient($cache_key, $coords, $this->cache_time);
        return $coords;
    }

    /**
     * Städte über GeoNames API ermitteln
     * @param array $base_coords
     * @param int $radius
     * @param int $min_population
     * @return array
     */
    private function getCitiesFromGeoNames($base_coords, $radius, $min_population) {
        if (empty($this->geonames_username)) {
            throw new Exception(__('GeoNames Username nicht konfiguriert', 'city-pages-generator'));
        }

		// Bevorzugt HTTPS. Falls lokale Umgebung keine aktuellen TLS‑Ciphers hat (cURL error 35),
		// fällt die Anfrage automatisch auf HTTP zurück.
		$https_url = 'https://secure.geonames.org/findNearbyPlaceNameJSON';
		$http_url  = 'http://api.geonames.org/findNearbyPlaceNameJSON';
		// GeoNames bietet nur grobe Stufen für Mindestbevölkerung: 1000/5000/15000
		$cities_param = 'cities1000';
		if ($min_population >= 15000) {
			$cities_param = 'cities15000';
		} elseif ($min_population >= 500) {
			$cities_param = 'cities5000';
		}

		$params = [
            'lat' => $base_coords['lat'],
            'lng' => $base_coords['lon'],
            'radius' => $radius,
			'maxRows' => 200,
            'username' => $this->geonames_username,
			'cities' => $cities_param, // Dynamisch: 1000 / 5000 / 15000
            'lang' => 'de'
        ];

		// 1) HTTPS‑Versuch
        $response = wp_remote_get($https_url . '?' . http_build_query($params), [
            'timeout' => 60,
			'headers' => [ 'User-Agent' => 'WordPress City Pages Generator Plugin' ]
		]);

		// Bei TLS‑Fehler (z. B. cURL error 35) oder nicht 200 -> HTTP‑Fallback
		if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) !== 200) {
			$https_error = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
            $response = wp_remote_get($http_url . '?' . http_build_query($params), [
                'timeout' => 60,
				'sslverify' => false,
				'headers' => [ 'User-Agent' => 'WordPress City Pages Generator Plugin' ]
			]);
			if (is_wp_error($response)) {
				throw new Exception(__('GeoNames API Fehler: ', 'city-pages-generator') . $https_error . ' | Fallback: ' . $response->get_error_message());
			}
			$response_code = (int)wp_remote_retrieve_response_code($response);
			if ($response_code !== 200) {
				throw new Exception(sprintf(__('GeoNames API HTTP Fehler: %d', 'city-pages-generator'), $response_code) . ' (Fallback)');
			}
		} else {
			$response_code = (int)wp_remote_retrieve_response_code($response);
			if ($response_code !== 200) {
				throw new Exception(sprintf(__('GeoNames API HTTP Fehler: %d', 'city-pages-generator'), $response_code));
			}
		}

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('GeoNames API: Ungültige JSON-Antwort', 'city-pages-generator'));
        }

        if (isset($data['status'])) {
            throw new Exception(__('GeoNames API Fehler: ', 'city-pages-generator') . ($data['status']['message'] ?? 'Unbekannter Fehler'));
        }

        $cities = [];
        if (isset($data['geonames'])) {
            foreach ($data['geonames'] as $city) {
                $population = intval($city['population'] ?? 0);
                
                if ($population >= $min_population) {
                    $cities[] = [
                        'name' => $city['name'],
                        'state' => $city['adminName1'] ?? '',
                        'country' => $city['countryName'] ?? 'Deutschland',
                        'population' => $population,
                        'lat' => floatval($city['lat']),
                        'lon' => floatval($city['lng']),
                        'distance' => floatval($city['distance'] ?? 0)
                    ];
                }
            }
        }

        // Nach Population sortieren
        usort($cities, function($a, $b) {
            return $b['population'] - $a['population'];
        });

        return $cities;
    }

    // Nominatim-Fallback und zugehörige Hilfsfunktionen wurden entfernt

    /**
     * Bereinigt Cache
     */
    public function clearCache() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", $this->cache_prefix . '%'));
    }
} 