<?php
/**
 * Anleitung Handler Klasse
 *
 * Diese Klasse verwaltet die Anleitung-Funktionalität des Plugins:
 * - Rendert die komplette Benutzeranleitung im Admin-Interface
 * - Stellt strukturierte Hilfe und Dokumentation bereit
 * - Verwaltet Platzhalter-Beispiele und Troubleshooting
 * - Bietet responsive Design für alle Bildschirmgrößen
 *
 * Die Klasse ist modular aufgebaut und kann einfach erweitert werden.
 */

// WordPress Sicherheitsmaßnahme - verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class CPG_Anleitung_Handler {

    /**
     * Konstruktor - keine spezielle Initialisierung erforderlich
     */
    public function __construct() {
        // Keine WordPress Hooks erforderlich - wird direkt vom Admin Handler aufgerufen
    }

    /**
     * Rendert die komplette Anleitung
     * 
     * Diese Methode erstellt die vollständige Benutzeranleitung mit:
     * - Installation & Erste Schritte
     * - Seiten generieren
     * - Wichtige Einstellungen
     * - KI-Funktionen
     * - Platzhalter-System
     * - Häufige Probleme & Lösungen
     * - Performance-Tipps
     * - Support-Informationen
     */
    public function render() {
        ?>
        <div class="cpg-anleitung-container">
            <h2>🏙️ Stadtseiten Generator - Schnellstart-Anleitung</h2>
            
            <?php $this->renderInstallationSection(); ?>
            <?php $this->renderGeneratorSection(); ?>
            <?php $this->renderSettingsSection(); ?>
            <?php $this->renderKISection(); ?>
            <?php $this->renderPlaceholdersSection(); ?>
            <?php $this->renderTroubleshootingSection(); ?>
            <?php $this->renderPerformanceSection(); ?>
            <?php $this->renderSuccessSection(); ?>
            <?php $this->renderSupportSection(); ?>
        </div>
        
        <?php $this->renderStyles(); ?>
        <?php
    }

    /**
     * Rendert den Installation & Erste Schritte Bereich
     */
    private function renderInstallationSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🚀 Installation & Erste Schritte</h3>
            <div class="cpg-step">
                <h4>1. Plugin aktivieren</h4>
                <p><strong>WordPress Admin → Plugins → Aktivieren</strong></p>
                <p>Nach Aktivierung automatische Weiterleitung zur Konfiguration</p>
            </div>
            
            <div class="cpg-step">
                <h4>2. Page Builder auswählen</h4>
                <p><strong>Seitengenerator → Einstellungen</strong></p>
                <p>Wählen Sie: <strong>Elementor</strong> ✓ oder <strong>Bricks Builder</strong> ✓</p>
                <p>Status: ✓ = installiert, ✗ = nicht installiert</p>
            </div>
            
            <div class="cpg-step">
                <h4>3. APIs konfigurieren (optional, aber empfohlen)</h4>
                <p><strong>GeoNames API</strong> (kostenlos): <a href="http://www.geonames.org/login" target="_blank">geonames.org</a> → Username eingeben</p>
                <p><strong>Gemini KI</strong> (optional): <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> → API-Key eingeben</p>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Seiten generieren Bereich
     */
    private function renderGeneratorSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🎨 Seiten generieren</h3>
            <div class="cpg-step">
                <h4>1. Vorlage erstellen</h4>
                <p><strong>Wichtig</strong>: Erstellen Sie zuerst eine Vorlage-Seite!</p>
                
                <div class="cpg-builder-option">
                    <h5>Elementor:</h5>
                    <ol>
                        <li>Neue Seite → <strong>"Mit Elementor bearbeiten"</strong></li>
                        <li>Gestalten Sie die Seite</li>
                        <li>Verwenden Sie Platzhalter: <code>{{stadt}}</code>, <code>{{thema}}</code>, <code>{{bundesland}}</code></li>
                        <li>Speichern</li>
                    </ol>
                </div>
                
                <div class="cpg-builder-option">
                    <h5>Bricks Builder:</h5>
                    <ol>
                        <li>Neue Seite → <strong>"Mit Bricks bearbeiten"</strong></li>
                        <li>Gestalten Sie die Seite</li>
                        <li>Platzhalter in Texte einfügen</li>
                        <li>Speichern</li>
                    </ol>
                </div>
            </div>
            
            <div class="cpg-step">
                <h4>2. Generator starten</h4>
                <ol>
                    <li><strong>Seitengenerator → Generator</strong></li>
                    <li><strong>Quellseite auswählen</strong>: Ihre Vorlage-Seite</li>
                    <li><strong>Standort</strong>: z.B. "Krefeld"</li>
                    <li><strong>Radius</strong>: z.B. 50 km</li>
                    <li><strong>Mindesteinwohner</strong>: z.B. 50.000</li>
                    <li><strong>"Städte laden"</strong> klicken</li>
                </ol>
            </div>
            
            <div class="cpg-step">
                <h4>3. Städte auswählen</h4>
                <ul>
                    <li><strong>A-Z Filter</strong>: Buchstaben für schnelle Navigation</li>
                    <li><strong>Alle auswählen/abwählen</strong>: Schnelle Auswahl</li>
                    <li><strong>Gewünschte Städte aktivieren</strong></li>
                </ul>
            </div>
            
            <div class="cpg-step">
                <h4>4. Textanpassung</h4>
                <ul>
                    <li><strong>Seitentitel</strong>: <code>{{thema}} in {{stadt}}</code></li>
                    <li><strong>URL-Slug</strong>: <code>{{thema}}-{{stadt}}</code></li>
                </ul>
            </div>
            
            <div class="cpg-step">
                <h4>5. Generieren</h4>
                <ul>
                    <li><strong>"Seiten generieren"</strong> klicken</li>
                    <li><strong>&lt; 20 Städte</strong>: Direkte Verarbeitung</li>
                    <li><strong>≥ 20 Städte</strong>: Automatische Batch-Verarbeitung</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Wichtige Einstellungen Bereich
     */
    private function renderSettingsSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🔧 Wichtige Einstellungen</h3>
            <div class="cpg-settings-grid">
                <div class="cpg-setting-card">
                    <h4>Page Builder</h4>
                    <ul>
                        <li><strong>Elementor</strong>: Für Elementor-Seiten</li>
                        <li><strong>Bricks</strong>: Für Bricks-Seiten</li>
                    </ul>
                </div>
                
                <div class="cpg-setting-card">
                    <h4>APIs</h4>
                    <ul>
                        <li><strong>GeoNames Username</strong>: Für bessere Städte-Daten</li>
                        <li><strong>Gemini KI</strong>: Für SEO-optimierte Texte</li>
                        <li><strong>Verbindung prüfen</strong>: Testet API-Verbindung</li>
                    </ul>
                </div>
                
                <div class="cpg-setting-card">
                    <h4>Cache</h4>
                    <ul>
                        <li><strong>API Cache</strong>: 1 Stunde (Standard)</li>
                        <li><strong>KI Cache</strong>: 1 Stunde (Standard)</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den KI-Funktionen Bereich
     */
    private function renderKISection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🤖 KI-Funktionen</h3>
            <div class="cpg-step">
                <h4>Gemini KI aktivieren</h4>
                <ol>
                    <li><strong>Einstellungen → "Gemini KI aktivieren"</strong></li>
                    <li><strong>API-Key eingeben</strong></li>
                    <li><strong>"Verbindung prüfen"</strong></li>
                </ol>
            </div>
            
            <div class="cpg-step">
                <h4>KI-Platzhalter</h4>
                <ol>
                    <li><strong>KI-Tab</strong></li>
                    <li><strong>Keyword</strong>: Hauptkeyword (z.B. "Photovoltaik")</li>
                    <li><strong>Leistung</strong>: Dienstleistungsbeschreibung</li>
                    <li><strong>Weitere Keywords</strong>: Zusätzliche Keywords</li>
                </ol>
            </div>
            
            <div class="cpg-step">
                <h4>KI-Platzhalter erstellen</h4>
                <ul>
                    <li><strong>Schlüssel</strong>: Name (z.B. "angebot")</li>
                    <li><strong>Beschreibung</strong>: Text für KI-Adaptierung</li>
                    <li><strong>Verwendung</strong>: <code>{{angebot}}</code> in Seiten</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Platzhalter-System Bereich
     */
    private function renderPlaceholdersSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>💡 Platzhalter-System</h3>
            <div class="cpg-placeholders-grid">
                <div class="cpg-placeholder-card">
                    <h4>Standard-Platzhalter</h4>
                    <div class="cpg-placeholder-list">
                        <div class="cpg-placeholder-item">
                            <code>{{stadt}}</code> → "Krefeld"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{Stadt}}</code> → "Krefeld" (großgeschrieben)
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{STADT}}</code> → "KREFELD" (alles groß)
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{thema}}</code> → "Photovoltaik"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{bundesland}}</code> → "Nordrhein-Westfalen"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{einwohner}}</code> → "227.020"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{entfernung}}</code> → "0.0 km"
                        </div>
                    </div>
                </div>
                
                <div class="cpg-placeholder-card">
                    <h4>Spezielle Platzhalter</h4>
                    <div class="cpg-placeholder-list">
                        <div class="cpg-placeholder-item">
                            <code>{{stadt_genitiv}}</code> → "Krefelds"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{in_stadt}}</code> → "in Krefeld"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{aus_stadt}}</code> → "aus Krefeld"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{nach_stadt}}</code> → "nach Krefeld"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{für_stadt}}</code> → "für Krefeld"
                        </div>
                    </div>
                </div>
                
                <div class="cpg-placeholder-card">
                    <h4>Regionale Anpassungen</h4>
                    <div class="cpg-placeholder-list">
                        <div class="cpg-placeholder-item">
                            <code>{{region_begriff}}</code> → "rheinisch" (in NRW)
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{region_adjektiv}}</code> → "rheinische"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{wahrzeichen}}</code> → "Krefelder Zoo"
                        </div>
                        <div class="cpg-placeholder-item">
                            <code>{{branchen}}</code> → "Textilindustrie"
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Häufige Probleme & Lösungen Bereich
     */
    private function renderTroubleshootingSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🔍 Häufige Probleme & Lösungen</h3>
            <div class="cpg-problem-solution">
                <?php $this->renderProblemCard(
                    '❌ "Keine Städte gefunden"',
                    [
                        'GeoNames konfigurieren: Username in Einstellungen',
                        'Radius erhöhen: 100 km statt 50 km',
                        'Mindesteinwohner senken: 20.000 statt 50.000',
                        'Anderen Ausgangsort: Größere Stadt wählen'
                    ]
                ); ?>
                
                <?php $this->renderProblemCard(
                    '❌ "Seite ist keine Elementor/Bricks-Seite"',
                    [
                        'Page Builder prüfen: Installiert?',
                        'Einstellungen: Richtigen Builder auswählen',
                        'Seite bearbeiten: Einmal mit Builder öffnen'
                    ]
                ); ?>
                
                <?php $this->renderProblemCard(
                    '❌ "Timeout bei vielen Städten"',
                    [
                        'Batch-Verarbeitung: Startet automatisch',
                        'Kleinere Mengen: Aufteilen',
                        'Server-Ressourcen: Hosting-Provider kontaktieren'
                    ]
                ); ?>
                
                <?php $this->renderProblemCard(
                    '❌ "KI-Texte werden nicht generiert"',
                    [
                        'API-Key prüfen: Korrekt eingegeben?',
                        'Verbindung testen: "Verbindung prüfen"',
                        'KI aktiviert: "Gemini KI aktivieren" aktiv?',
                        'Cache leeren: In Einstellungen zurücksetzen'
                    ]
                ); ?>
                
                <?php $this->renderProblemCard(
                    '❌ "Platzhalter werden nicht ersetzt"',
                    [
                        'Syntax prüfen: {{stadt}} (doppelte Klammern)',
                        'Groß-/Kleinschreibung: {{stadt}} nicht {{Stadt}}',
                        'Cache leeren: Browser und WordPress',
                        'Seite neu generieren: Löschen und neu erstellen'
                    ]
                ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Performance-Tipps Bereich
     */
    private function renderPerformanceSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>⚡ Performance-Tipps</h3>
            <div class="cpg-tips-grid">
                <div class="cpg-tip-card">
                    <h4>Optimale Einstellungen</h4>
                    <ul>
                        <li><strong>Batch-Größe</strong>: 15 Seiten pro Batch</li>
                        <li><strong>Batch-Pause</strong>: 2 Sekunden zwischen Batches</li>
                        <li><strong>Memory Limit</strong>: 256MB oder höher</li>
                        <li><strong>Kleine Batches</strong>: 10-20 Städte pro Durchgang</li>
                    </ul>
                </div>
                
                <div class="cpg-tip-card">
                    <h4>SEO-Optimierung</h4>
                    <ul>
                        <li><strong>Einzigartige Titel</strong>: Verschiedene Titel-Schemata</li>
                        <li><strong>Meta-Beschreibungen</strong>: KI generiert automatisch</li>
                        <li><strong>Lokale Keywords</strong>: Stadt-spezifische Begriffe</li>
                        <li><strong>Interne Verlinkung</strong>: Zwischen Stadtseiten</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Erfolgreiche Nutzung Bereich
     */
    private function renderSuccessSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>🎯 Erfolgreiche Nutzung</h3>
            <div class="cpg-success-grid">
                <div class="cpg-success-card">
                    <h4>Was Sie erreichen können:</h4>
                    <ul>
                        <li>✅ <strong>Hunderte von Seiten</strong> automatisch erstellen</li>
                        <li>✅ <strong>Lokale SEO</strong> für verschiedene Städte optimieren</li>
                        <li>✅ <strong>Zeit sparen</strong> bei der Seiten-Erstellung</li>
                        <li>✅ <strong>Konsistente Qualität</strong> bei allen Seiten</li>
                        <li>✅ <strong>Flexible Anpassungen</strong> durch Platzhalter</li>
                    </ul>
                </div>
                
                <div class="cpg-success-card">
                    <h4>Technische Voraussetzungen:</h4>
                    <ul>
                        <li><strong>WordPress</strong>: 5.0+</li>
                        <li><strong>PHP</strong>: 7.4+</li>
                        <li><strong>Page Builder</strong>: Elementor oder Bricks</li>
                        <li><strong>Speicher</strong>: 256MB+ für große Generierungen</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert den Support Bereich
     */
    private function renderSupportSection() {
        ?>
        <div class="cpg-anleitung-section">
            <h3>📞 Support</h3>
            <div class="cpg-support-info">
                <div class="cpg-support-card">
                    <h4>Hilfe im Plugin</h4>
                    <ul>
                        <li><strong>Hilfe-Tab</strong>: Im Plugin-Interface</li>
                        <li><strong>Platzhalter-Liste</strong>: Alle verfügbaren Platzhalter</li>
                        <li><strong>Beispiele</strong>: Praktische Anwendungsbeispiele</li>
                    </ul>
                </div>
                
                <div class="cpg-support-card">
                    <h4>Empfohlene Einstellungen</h4>
                    <ul>
                        <li><strong>Batch-Größe</strong>: 15 Seiten</li>
                        <li><strong>Batch-Pause</strong>: 2 Sekunden</li>
                        <li><strong>Cache-Zeit</strong>: 1 Stunde</li>
                        <li><strong>Memory Limit</strong>: 256MB+</li>
                    </ul>
                </div>
            </div>
            
            <div class="cpg-success-message">
                <h4>🚀 Viel Erfolg mit Ihrem Stadtseiten Generator!</h4>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert eine Problem-Lösung-Karte
     * 
     * @param string $problem Das Problem
     * @param array $solutions Array mit Lösungsvorschlägen
     */
    private function renderProblemCard($problem, $solutions) {
        ?>
        <div class="cpg-problem">
            <h4><?php echo esc_html($problem); ?></h4>
            <div class="cpg-solutions">
                <p><strong>Lösungen:</strong></p>
                <ul>
                    <?php foreach ($solutions as $solution): ?>
                        <li><strong><?php echo esc_html($solution); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert alle CSS-Styles für die Anleitung
     */
    private function renderStyles() {
        ?>
        <style>
        .cpg-anleitung-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .cpg-anleitung-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .cpg-anleitung-section h3 {
            color: #0073aa;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .cpg-step {
            margin-bottom: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            border-radius: 4px;
        }
        
        .cpg-step h4 {
            color: #0073aa;
            margin-top: 0;
        }
        
        .cpg-builder-option {
            margin: 15px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cpg-builder-option h5 {
            color: #0073aa;
            margin-top: 0;
        }
        
        .cpg-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cpg-setting-card {
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cpg-setting-card h4 {
            color: #0073aa;
            margin-top: 0;
        }
        
        .cpg-placeholders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cpg-placeholder-card {
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cpg-placeholder-card h4 {
            color: #0073aa;
            margin-top: 0;
        }
        
        .cpg-placeholder-list {
            margin-top: 10px;
        }
        
        .cpg-placeholder-item {
            margin: 8px 0;
            padding: 5px;
            background: #fff;
            border-radius: 3px;
        }
        
        .cpg-placeholder-item code {
            background: #e1e1e1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .cpg-problem-solution {
            margin-top: 20px;
        }
        
        .cpg-problem {
            margin-bottom: 20px;
            padding: 15px;
            background: #fff5f5;
            border: 1px solid #f56565;
            border-radius: 4px;
        }
        
        .cpg-problem h4 {
            color: #e53e3e;
            margin-top: 0;
        }
        
        .cpg-solutions {
            margin-top: 10px;
        }
        
        .cpg-solutions ul {
            margin: 10px 0;
        }
        
        .cpg-tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cpg-tip-card {
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
        }
        
        .cpg-tip-card h4 {
            color: #0073aa;
            margin-top: 0;
        }
        
        .cpg-success-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cpg-success-card {
            padding: 15px;
            background: #f0fff0;
            border: 1px solid #38a169;
            border-radius: 4px;
        }
        
        .cpg-success-card h4 {
            color: #38a169;
            margin-top: 0;
        }
        
        .cpg-support-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cpg-support-card {
            padding: 15px;
            background: #fff8e1;
            border: 1px solid #f6ad55;
            border-radius: 4px;
        }
        
        .cpg-support-card h4 {
            color: #f6ad55;
            margin-top: 0;
        }
        
        .cpg-success-message {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        
        .cpg-success-message h4 {
            color: white;
            margin: 0;
            font-size: 1.2em;
        }
        
        @media (max-width: 768px) {
            .cpg-settings-grid,
            .cpg-placeholders-grid,
            .cpg-tips-grid,
            .cpg-success-grid,
            .cpg-support-info {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
}
