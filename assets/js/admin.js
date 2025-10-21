/**
 * City Pages Generator Admin JavaScript
 * 
 * Dieses JavaScript verwaltet das komplette Frontend-Interface des Plugins:
 * - AJAX-Kommunikation mit dem Backend
 * - Dynamische Benutzeroberfläche (Tabs, Formulare, Tabellen)
 * - Batch-Verarbeitung für große Seitenmengen
 * - Formular-Persistierung (Auto-Save)
 * - Platzhalter-Management
 * - Fortschrittsanzeigen und Benachrichtigungen
 * 
 * Das Script ist modular aufgebaut und verwendet jQuery für DOM-Manipulation.
 */

(function($) {
    'use strict';

    // Hauptobjekt des Plugins - enthält alle Funktionen und Daten
    var CPG = {
        cities: [],              // Array der geladenen Städte
        selectedCities: [],      // Array der ausgewählten Städte
        isGenerating: false,     // Flag ob gerade Seiten generiert werden
        keywords: [],            // Array der benutzerdefinierten Keywords
        currentLetter: 'ALL',    // Aktueller Buchstabenfilter für Städte

        /**
         * Initialisierung - startet alle Plugin-Funktionen
         * Wird beim Laden der Admin-Seite aufgerufen
         */
        init: function() {
            this.bindEvents();                    // Event-Handler registrieren
            this.initPlaceholderHelp();          // Platzhalter-Hilfe initialisieren
            
            this.initAutoSave();                 // Auto-Save für Formulare aktivieren
            this.loadFormData();                 // Gespeicherte Formulardaten laden
            // Willkommensbanner deaktiviert (entfernt störende Elemente)
            try { $('.cpg-welcome-banner').remove(); } catch(e) {}

            // Keywords laden und Tabelle rendern (falls KI-Tab offen)
            this.loadKeywords();                 // Keywords aus localStorage laden
            this.renderKeywordsTable();          // Keywords-Tabelle anzeigen
            
            // Batch-Verarbeitung fortsetzen falls vorhanden (nach Seitenreload)
            this.resumeBatchProcessing();        // Unterbrochene Batch-Verarbeitung wiederaufnehmen
        },

        /**
         * Batch-Verarbeitung fortsetzen - stellt unterbrochene Verarbeitung wieder her
         * Wird beim Laden der Seite aufgerufen um Batch-Verarbeitung nach Seitenreload fortzusetzen
         */
        resumeBatchProcessing: function() {
            // Prüfen ob eine Batch-Verarbeitung im localStorage gespeichert ist
            var savedBatchData = localStorage.getItem('cpg_batch_data');
            if (savedBatchData) {
                try {
                    // Batch-Daten aus localStorage wiederherstellen
                    var batchData = JSON.parse(savedBatchData);
                    var currentBatch = parseInt(localStorage.getItem('cpg_current_batch') || '0');
                    var allResults = JSON.parse(localStorage.getItem('cpg_all_results') || '[]');
                    
                    // Batch-Daten wiederherstellen
                    CPG.batchData = batchData;
                    CPG.currentBatch = currentBatch;
                    CPG.allResults = allResults;
                    CPG.isGenerating = true;
                    
                    // Batch-UI anzeigen
                    CPG.showBatchProgress();      // Fortschrittsanzeige anzeigen
                    CPG.updateBatchProgress();    // Aktuellen Fortschritt anzeigen
                    
                    // Fortsetzen der Verarbeitung
                    if (CPG.currentBatch < CPG.batchData.total_batches) {
                        CPG.processNextBatch();   // Nächsten Batch verarbeiten
                    } else {
                        // Alle Batches abgeschlossen - aber NICHT aufräumen
                        CPG.showBatchComplete();   // Abschlussmeldung anzeigen
                        // CPG.cleanupBatchData(); // NICHT automatisch aufräumen
                    }
                } catch (e) {
                    console.error('Fehler beim Wiederherstellen der Batch-Verarbeitung:', e);
                    CPG.cleanupBatchData();       // Bei Fehlern aufräumen
                }
            }
        },

        /**
         * Batch-Daten aufräumen - entfernt alle gespeicherten Batch-Daten
         * Wird aufgerufen wenn Batch-Verarbeitung abgeschlossen oder abgebrochen wird
         */
        cleanupBatchData: function() {
            // Batch-Daten aus localStorage entfernen
            localStorage.removeItem('cpg_batch_data');      // Batch-Konfiguration
            localStorage.removeItem('cpg_current_batch');  // Aktueller Batch
            localStorage.removeItem('cpg_all_results');    // Alle Ergebnisse
        },

        /**
         * Event-Handler registrieren - verbindet alle Benutzerinteraktionen mit Funktionen
         * Verwendet jQuery Event-Delegation für dynamisch erstellte Elemente
         */
        bindEvents: function() {
            $('#cpg-load-cities').on('click', this.loadCities);
            $('#cpg-generator-form').on('submit', this.generatePages);
            $(document).on('change', '.cpg-city-checkbox', this.toggleCity);
            $(document).on('click', '#cpg-select-all-cities', this.selectAllCities);
            $(document).on('click', '#cpg-deselect-all-cities', this.deselectAllCities);
            $(document).on('click', '.cpg-publish-page', this.publishPage);
            $(document).on('click', '.cpg-unpublish-page', this.unpublishPage);
            $(document).on('click', '.cpg-delete-page', this.deletePage);

            // A–Z Filter für Städte
            $(document).on('click', '.cpg-letter-filter button', function(e){
                e.preventDefault();
                var letter = $(this).data('letter');
                CPG.setLetterFilter(letter);
            });

            // Keyword-Events (KI-Tab)
            // Explizit preventDefault und direkter Aufruf, falls this-binding kollidiert
            $(document).on('click', '#cpg-add-keyword', function(e){ e.preventDefault(); CPG.addKeyword(); });
            $(document).on('keypress', '#theme_keyword_add', function(e){ if(e.which===13){ e.preventDefault(); CPG.addKeyword(); }});
            // Entfernen-Handler ohne bind, damit this = Button-Element bleibt
            $(document).on('click', '.cpg-remove-keyword', this.removeKeyword);
            
            // Gemini KI: Verbindung prüfen
            $(document).on('click', '#cpg-check-gemini', this.checkGeminiConnection);

            // Einfache KI-Platzhalter: Zeile hinzufügen/entfernen
            $(document).on('click', '.cpg-add-simple-ph', function() {
                console.log('KI Platzhalter + Button clicked');
                CPG.addSimplePlaceholderRow();
            });
            $(document).on('click', '.cpg-remove-simple-ph', function(e) {
                console.log('KI Platzhalter - Button clicked');
                CPG.removeSimplePlaceholderRow(e.currentTarget);
            });

            // Einfache Platzhalter ohne KI: Zeile hinzufügen/entfernen
            $(document).on('click', '.cpg-add-simple-ph-no-ki', function() {
                CPG.addSimplePlaceholderNoKi();
            });
            $(document).on('click', '.cpg-remove-simple-ph-no-ki', function() {
                CPG.removeSimplePlaceholderNoKi(this);
            });

            // Alle Editoren ein-/ausklappen
            $(document).on('click', '.cpg-expand-editors', function(){
                var target = $(this).data('target');
                CPG.toggleEditors(target, true);
            });
            $(document).on('click', '.cpg-collapse-editors', function(){
                var target = $(this).data('target');
                CPG.toggleEditors(target, false);
            });

        // Settings Page Events
        if ($('body').hasClass('settings_page_city-pages-generator')) {
            this.initSettingsPage();
        }
        },

        initPlaceholderHelp: function() {
            var placeholders = [
                { code: '{{stadt}}', desc: 'Stadtname (z.B. Krefeld)' },
                { code: '{{Stadt}}', desc: 'Stadtname großgeschrieben' },
                { code: '{{bundesland}}', desc: 'Bundesland (z.B. Nordrhein-Westfalen)' },
                { code: '{{thema}}', desc: 'Thema/Keyword aus Einstellungen' },
                { code: '{{in_stadt}}', desc: 'in + Stadtname (z.B. in Krefeld)' },
                { code: '{{stadt_genitiv}}', desc: 'Stadtname im Genitiv (z.B. Krefelds)' }
            ];

            if ($('.cpg-placeholders-help').length) {
                var helpHtml = '<h4>Verfügbare Platzhalter:</h4><div class="cpg-placeholder-grid">';
                placeholders.forEach(function(item) {
                    helpHtml += '<div class="cpg-placeholder-item">';
                    helpHtml += '<div class="cpg-placeholder-code">' + item.code + '</div>';
                    helpHtml += '<div class="cpg-placeholder-desc">' + item.desc + '</div>';
                    helpHtml += '</div>';
                });
                helpHtml += '</div>';
                $('.cpg-placeholders-help').html(helpHtml);
            }
        },

        loadCities: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var location = $('#base_location').val().trim();
            var radius = parseInt($('#radius').val()) || 50;
            var minPopulation = parseInt($('#min_population').val()) || 50000;
            
            if (!location) {
                CPG.showNotice('Bitte geben Sie einen Ausgangsort ein.', 'error');
                return;
            }
            
            $btn.prop('disabled', true).text('Lädt...');
            
            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                timeout: 60000, // 60 Sekunden Timeout
                data: {
                    action: 'cpg_get_cities',
                    nonce: cpgAjax.nonce,
                    location: location,
                    radius: radius,
                    min_population: minPopulation
                },
                success: function(response) {
                    if (response.success) {
                        CPG.cities = response.data;
                        CPG.renderCities();
                        $('#cpg-cities-section').show();
                        
                        // Batch-Einstellungen anzeigen wenn viele Städte
                        if (CPG.cities.length >= 20) {
                            $('#cpg-batch-settings').show();
                        }
                        
                        CPG.showNotice(CPG.cities.length + ' Städte gefunden.', 'success');
                    } else {
                        CPG.showNotice('Fehler beim Laden der Städte: ' + (response.data || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = 'Ajax-Fehler beim Laden der Städte.';
                    if (status === 'timeout') {
                        errorMsg = 'Timeout: Die Anfrage dauerte zu lang. Bitte versuchen Sie es erneut.';
                    } else if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data) {
                                errorMsg = 'Fehler: ' + response.data;
                            }
                        } catch (e) {
                            errorMsg = 'Server-Fehler: ' + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    CPG.showNotice(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Städte laden');
                }
            });
        },

        renderCities: function() {
            var $container = $('#cpg-cities-list');
            var html = '';
            
            if (CPG.cities.length === 0) {
                html = '<p>Keine Städte gefunden.</p>';
            } else {
                // A–Z Filterleiste
                var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
                html += '<div class="cpg-letter-filter" style="margin-bottom:8px; display:flex; flex-wrap:wrap; gap:4px;">';
                html += '<button type="button" class="button '+ (CPG.currentLetter==='ALL'?'button-primary':'button-secondary') +'" data-letter="ALL">Alle</button>';
                letters.forEach(function(L){
                    var active = (CPG.currentLetter===L) ? 'button-primary' : 'button-secondary';
                    html += '<button type="button" class="button '+ active +'" data-letter="'+L+'">'+L+'</button>';
                });
                html += '</div>';

                html += '<div style="margin-bottom: 10px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">';
                html += '<button type="button" id="cpg-select-all-cities" class="button button-small">Alle auswählen</button> ';
                html += '<button type="button" id="cpg-deselect-all-cities" class="button button-small">Alle abwählen</button>';
                html += '<span id="cpg-selected-count" style="color:#374151; font-size:12px; margin-left:6px;">Ausgewählt: '+ (CPG.selectedCities.length || 0) +'</span>';
                html += '</div>';
                
                var filtered = CPG.getFilteredCities();
                html += '<div class="cpg-cities-list">';
                filtered.forEach(function(city, index) {
                    // Index neu mappen: finde originalen Index für Checkbox-Value
                    var originalIndex = CPG.cities.indexOf(city);
                    html += '<div class="cpg-city-item">';
                    var isChecked = (CPG.selectedCities.indexOf(originalIndex) !== -1);
                    html += '<input type="checkbox" class="cpg-city-checkbox" value="' + originalIndex + '" id="city_' + originalIndex + '"' + (isChecked ? ' checked' : '') + '>';
                    html += '<label for="city_' + originalIndex + '">';
                    html += '<span class="cpg-city-name">' + city.name + '</span>';
                    html += '<span class="cpg-city-info">';
                    if (city.state) html += city.state + ' | ';
                    html += '<span class="cpg-city-population">' + CPG.formatNumber(city.population) + ' Einw.</span>';
                    if (city.distance) html += '<span class="cpg-city-distance">' + city.distance + ' km</span>';
                    html += '</span>';
                    html += '</label>';
                    html += '</div>';
                });
                html += '</div>';
                if (filtered.length === 0) {
                    html += '<p>Keine Städte für den gewählten Buchstaben.</p>';
                }
            }
            
            $container.html(html);
            CPG.updateSelectedCount();
        },

        selectAllCities: function(e) {
            e.preventDefault();
            $('.cpg-city-checkbox').prop('checked', true).trigger('change');
        },

        deselectAllCities: function(e) {
            e.preventDefault();
            $('.cpg-city-checkbox').prop('checked', false).trigger('change');
        },

        toggleCity: function() {
            var index = parseInt($(this).val());
            var isChecked = $(this).is(':checked');
            
            if (isChecked) {
                if (CPG.selectedCities.indexOf(index) === -1) {
                    CPG.selectedCities.push(index);
                }
            } else {
                var pos = CPG.selectedCities.indexOf(index);
                if (pos !== -1) {
                    CPG.selectedCities.splice(pos, 1);
                }
            }
            
            $('#cpg-generate').prop('disabled', CPG.selectedCities.length === 0);
            CPG.updateSelectedCount();
        },

        generatePages: function(e) {
            e.preventDefault();
            
            if (CPG.isGenerating) return;
            
            var sourcePageId = parseInt($('#source_page').val());
            var pageTitlePattern = $('#page_title_pattern').val().trim();
            var slugPattern = '{{thema}}-{{stadt}}'; // Standard-Slug-Pattern
            // Keyword kann im KI-Tab stehen; dort ist das Feld auf dem Generator-Tab nicht vorhanden
            var themeKeywordEl = $('#theme_keyword');
            var themeKeyword = themeKeywordEl.length ? (themeKeywordEl.val() || '').trim() : '';
            if (!themeKeyword) {
                // Fallback: aus localStorage lesen
                try {
                    var saved = localStorage.getItem('cpg_form_data');
                    if (saved) {
                        var savedData = JSON.parse(saved);
                        themeKeyword = (savedData && savedData.theme_keyword ? savedData.theme_keyword : '').trim();
                    }
                } catch (e) {}
            }
            // Keywords-Array laden
            var savedKeywords = CPG.getSavedKeywords();
            var seoNoindex = false; // Feld entfernt
            
            if (!sourcePageId) {
                CPG.showNotice('Bitte wählen Sie eine Quellseite aus.', 'error');
                return;
            }
            
            if (!pageTitlePattern) {
                CPG.showNotice('Bitte geben Sie ein Seitentitel-Schema ein.', 'error');
                return;
            }
            
            
            if (CPG.selectedCities.length === 0) {
                CPG.showNotice('Bitte wählen Sie mindestens eine Stadt aus.', 'error');
                return;
            }

            // Server-Überlastungsschutz-Warnung bei vielen Städten
            if (CPG.selectedCities.length >= 20) {
                $('#cpg-server-protection').show();
            } else {
                $('#cpg-server-protection').hide();
            }

            
            var selectedCityData = [];
            CPG.selectedCities.forEach(function(index) {
                if (CPG.cities[index]) {
                    selectedCityData.push(CPG.cities[index]);
                }
            });

            // Keyword ist optional; wenn leer, übernimmt der Server das Thema als Fallback

            // Replacements zusammenstellen
            var replacements = {
                theme_keyword: themeKeyword,
                page_title_pattern: pageTitlePattern,
                // seo_noindex entfernt,
                keywords: savedKeywords,
                service_template: (function(){
                    var val = '';
                    var el = $('#service_template');
                    if (el.length) { val = (el.val() || '').trim(); }
                    if (!val) {
                        try {
                            var saved2 = localStorage.getItem('cpg_form_data');
                            if (saved2) {
                                var sd2 = JSON.parse(saved2);
                                val = (sd2 && sd2.service_template ? sd2.service_template : '').trim();
                            }
                        } catch(e){}
                    }
                    return val;
                })()
            };
            
            CPG.isGenerating = true;
            
            var progressText = 'Starte Generierung...';
            CPG.showProgress(0, progressText);
            
            // Geschätzte Zeit berechnen
            var estimatedTime = selectedCityData.length;
            
            // Timeout dynamisch anpassen
            var dynamicTimeout = Math.max(300000, estimatedTime * 1000 * 2); // Mindestens 5 Min, sonst 2x geschätzte Zeit
            
            if (estimatedTime > 60) {
                CPG.showNotice('⏱️ Geschätzte Dauer: ca. ' + Math.ceil(estimatedTime / 60) + ' Minute(n). Bei vielen Städten empfehlen wir kleinere Batches.', 'warning');
            } else if (estimatedTime > 30) {
                CPG.showNotice('⏱️ Geschätzte Dauer: ca. ' + Math.ceil(estimatedTime / 60) + ' Minute(n). Bitte haben Sie Geduld.', 'info');
            }
            
            CPG.performGenerationWithRetry(sourcePageId, selectedCityData, slugPattern, replacements, dynamicTimeout, 0);
        },

        showProgress: function(percent, text) {
            $('#cpg-progress').show();
            $('.cpg-progress-fill').css('width', percent + '%');
            $('.cpg-progress-text').text(text);
        },

        renderResults: function(results) {
            var $container = $('#cpg-results');
            var html = '<h3>Generierungsergebnisse</h3>';
            
            // Prüfen ob results ein Array ist oder ein Objekt mit results-Array
            var resultsArray = Array.isArray(results) ? results : (results.results || []);
            
            if (resultsArray.length === 0) {
                html += '<p>Keine Ergebnisse verfügbar.</p>';
                $container.html(html).show();
                return;
            }
            
            html += '<div class="cpg-results-list">';
            resultsArray.forEach(function(result) {
                var cssClass = result.success ? 'success' : 'error';
                html += '<div class="cpg-result-item ' + cssClass + '">';
                html += '<div class="cpg-result-info">';
                
                if (result.success) {
                    html += '<div class="cpg-result-title">' + result.title + '</div>';
                    html += '<div class="cpg-result-meta">Stadt: ' + result.city + ' | Slug: <code>' + result.slug + '</code></div>';
                } else {
                    html += '<div class="cpg-result-title">Fehler für ' + result.city + '</div>';
                    html += '<div class="cpg-result-meta">' + result.error + '</div>';
                }
                
                html += '</div>';
                
                if (result.success) {
                    html += '<div class="cpg-result-actions">';
                    html += '<a href="' + result.edit_url + '" class="button button-small">Bearbeiten</a>';
                    html += '<a href="' + result.preview_url + '" class="button button-small" target="_blank">Vorschau</a>';
                    html += '</div>';
                }
                
                html += '</div>';
            });
            html += '</div>';
            
            $container.html(html).show();
        },

        publishPage: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var pageId = $btn.data('page-id');
            
            if (!confirm('Seite wirklich veröffentlichen?')) return;
            
            $btn.prop('disabled', true).text('Veröffentliche...');
            
            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpg_publish_page',
                    nonce: cpgAjax.nonce,
                    page_id: pageId
                },
                success: function(response) {
                    if (response.success) {
                        CPG.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CPG.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Veröffentlichen');
                }
            });
        },

        deletePage: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var pageId = $btn.data('page-id');
            
            if (!confirm('Seite wirklich löschen?')) return;
            
            $btn.prop('disabled', true).text('Lösche...');
            
            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpg_delete_page',
                    nonce: cpgAjax.nonce,
                    page_id: pageId
                },
                success: function(response) {
                    if (response.success) {
                        CPG.showNotice(response.data.message, 'success');
                        $btn.closest('tr').fadeOut();
                    } else {
                        CPG.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Löschen');
                }
            });
        },

        unpublishPage: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var pageId = $btn.data('page-id');
            
            if (!confirm('Seite wirklich auf Entwurf setzen?')) return;
            
            $btn.prop('disabled', true).text('Setze Entwurf...');
            
            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpg_unpublish_page',
                    nonce: cpgAjax.nonce,
                    page_id: pageId
                },
                success: function(response) {
                    if (response.success) {
                        CPG.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CPG.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Entwurf');
                }
            });
        },

        showNotice: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="cpg-notice ' + type + '">' + message + '</div>');
            $('.cpg-notice').remove();
            $('.cpg-generator-container, .cpg-overview-container, .cpg-settings-container').first().prepend($notice);
            
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
        },

        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        },


        toggleContentType: function() {
            var contentType = $('#content_type').val();
            
            $('#manual_content_row').hide();
            
            if (contentType === 'manual') {
                $('#manual_content_row').show();
            }
        },







        initSettingsPage: function() {
            // Settings-Page-Initialisierung
        },

        showAllPages: function() {
            // Alle Seiten anzeigen (ohne Zeitlimit)
            window.location.href = window.location.href + '&show_all=1';
        },


        // Form Persistence System
        saveFormData: function() {
            // Vorhandene Daten laden, damit Felder von anderen Tabs nicht überschrieben werden
            var existingRaw = localStorage.getItem('cpg_form_data');
            var existing = {};
            try { existing = existingRaw ? JSON.parse(existingRaw) : {}; } catch (e) { existing = {}; }

            // Nur Felder schreiben, die auf der aktuellen Seite existieren
            if ($('#source_page').length) {
                existing.source_page = $('#source_page').val();
            }
            if ($('#base_location').length) {
                existing.base_location = $('#base_location').val();
            }
            if ($('#radius').length) {
                existing.radius = $('#radius').val();
            }
            if ($('#min_population').length) {
                existing.min_population = $('#min_population').val();
            }
            if ($('#theme_keyword').length) {
                existing.theme_keyword = $('#theme_keyword').val();
            }
            if ($('#service_template').length) {
                existing.service_template = $('#service_template').val();
            }
            if ($('#page_title_pattern').length) {
                existing.page_title_pattern = $('#page_title_pattern').val();
            }
            // seo_noindex entfernt

            existing.timestamp = Date.now();

            localStorage.setItem('cpg_form_data', JSON.stringify(existing));
        },

        loadFormData: function() {
            var saved = localStorage.getItem('cpg_form_data');
            if (!saved) return;
            
            try {
                var formData = JSON.parse(saved);
                
                // Nur laden wenn nicht älter als 7 Tage
                if (Date.now() - formData.timestamp > 7 * 24 * 60 * 60 * 1000) {
                    localStorage.removeItem('cpg_form_data');
                    return;
                }
                
                // Felder wiederherstellen (nur wenn Feld existiert)
                if ($('#source_page').length) {
                    $('#source_page').val(formData.source_page || '');
                }
                if ($('#base_location').length) {
                    $('#base_location').val(formData.base_location || '');
                }
                if ($('#radius').length) {
                    $('#radius').val(formData.radius || $('#radius').attr('value') || '');
                }
                if ($('#min_population').length) {
                    $('#min_population').val(formData.min_population || $('#min_population').attr('value') || '');
                }
                if ($('#theme_keyword').length) {
                    $('#theme_keyword').val(formData.theme_keyword || '');
                }
                if ($('#service_template').length) {
                    $('#service_template').val(formData.service_template || '');
                }
                if ($('#page_title_pattern').length) {
                    $('#page_title_pattern').val(formData.page_title_pattern || '');
                }
                // seo_noindex entfernt
                
                
                
            } catch (e) {
                localStorage.removeItem('cpg_form_data');
            }
        },

        // Keywords Management
        addKeyword: function() {
            try { console.debug('[CPG] addKeyword: click'); } catch(e) {}
            var input = $('#theme_keyword_add');
            if (!input.length) return;
            var kw = (input.val() || '').trim();
            if (!kw) { try { console.debug('[CPG] addKeyword: empty input'); } catch(e) {} return; }
            // Duplikate vermeiden (case-insensitive)
            var exists = CPG.keywords.some(function(k){ return (k.value || '').toLowerCase() === kw.toLowerCase(); });
            if (exists) {
                CPG.showNotice('Keyword existiert bereits.', 'warning');
                input.val('').focus();
                return;
            }
            var item = { value: kw, created_at: new Date().toISOString() };
            CPG.keywords.push(item);
            try { console.debug('[CPG] addKeyword: added', item); } catch(e) {}
            CPG.persistKeywords();
            CPG.renderKeywordsTable();
            input.val('').focus();
        },

        removeKeyword: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var idx = parseInt($btn.data('index'));
            if (isNaN(idx)) return;
            CPG.keywords.splice(idx, 1);
            CPG.persistKeywords();
            CPG.renderKeywordsTable();
        },

        renderKeywordsTable: function() {
            var $tbody = $('#cpg-keywords-tbody');
            if (!$tbody.length) return;
            if (!CPG.keywords || CPG.keywords.length === 0) {
                $tbody.html('<tr id="cpg-keywords-empty"><td colspan="3">Keine Keywords hinzugefügt.</td></tr>');
                return;
            }
            var rows = '';
            CPG.keywords.forEach(function(item, i){
                var dateStr = new Date(item.created_at).toLocaleString();
                rows += '<tr>'+
                    '<td><code>'+ CPG.escapeHtml(item.value) +'</code></td>'+
                    '<td>'+ CPG.escapeHtml(dateStr) +'</td>'+
                    '<td><button type="button" class="button button-small cpg-remove-keyword" data-index="'+i+'">Entfernen</button></td>'+
                '</tr>';
            });
            $tbody.html(rows);
        },

        escapeHtml: function(s){
            return String(s).replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
        },

        persistKeywords: function(){
            try {
                localStorage.setItem('cpg_keywords', JSON.stringify(CPG.keywords));
            } catch (e) {}
        },

        loadKeywords: function(){
            try {
                var raw = localStorage.getItem('cpg_keywords');
                CPG.keywords = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(CPG.keywords)) CPG.keywords = [];
            } catch (e) { CPG.keywords = []; }
        },

        getSavedKeywords: function(){
            try {
                var raw = localStorage.getItem('cpg_keywords');
                var arr = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(arr)) return [];
                return arr.map(function(i){ return i.value; }).filter(function(v){ return !!v; });
            } catch (e) { return []; }
        },


        initAutoSave: function() {
            var self = this;
            
            // Auto-save bei Änderungen
            $(document).on('change input', 'input, select, textarea', function() {
                setTimeout(function() {
                    self.saveFormData();
                }, 500);
            });
        },

        initWelcomeExperience: function() {
            // vollständig deaktiviert
            try { localStorage.removeItem('cpg_welcomed'); } catch(e) {}
        },

        showWelcomeMessage: function() {
            // vollständig deaktiviert – keine Ausgabe
            return;
        },

        performGenerationWithRetry: function(sourcePageId, selectedCityData, slugPattern, replacements, timeout, retryCount) {
            var maxRetries = 2;
            
            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                timeout: timeout,
                data: {
                    action: 'cpg_generate_pages',
                    nonce: cpgAjax.nonce,
                    source_page_id: sourcePageId,
                    cities: JSON.stringify(selectedCityData),
                    slug_pattern: slugPattern,
                    replacements: JSON.stringify(replacements)
                },
                success: function(response) {
                    if (response.success) {
                        // Prüfen ob Batch-Verarbeitung gestartet wurde
                        if (response.data.batch_processing) {
                            CPG.startBatchProcessing(response.data);
                        } else {
                            // Normale Verarbeitung
                            CPG.showProgress(100, 'Generierung abgeschlossen!');
                            CPG.renderResults(response.data);
                            CPG.showNotice('✅ Seiten erfolgreich generiert!', 'success');
                            
                            // Ergebnisse dauerhaft anzeigen - keine automatische Aktualisierung
                            // Der Benutzer kann manuell die Seite aktualisieren oder eine neue Generierung starten
                        }
                    } else {
                        CPG.showNotice('❌ Fehler bei der Generierung: ' + (response.data || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    
                    if (status === 'timeout' && retryCount < maxRetries) {
                        // Retry bei Timeout
                        CPG.showNotice('⏱️ Timeout erreicht - Versuche erneut (' + (retryCount + 1) + '/' + maxRetries + ')...', 'warning');
                        CPG.showProgress(50, 'Wiederhole Generierung...');
                        
                        setTimeout(function() {
                            CPG.performGenerationWithRetry(sourcePageId, selectedCityData, slugPattern, replacements, timeout * 1.5, retryCount + 1);
                        }, 5000);
                        
                    } else if (xhr.status === 504) {
                        // Gateway Timeout - spezielle Behandlung
                        CPG.showNotice('🚫 Server-Timeout (504). Bei ' + selectedCityData.length + ' Städten empfehlen wir kleinere Batches (20-30 Städte).', 'error');
                        
                        if (selectedCityData.length > 30) {
                            CPG.showNotice('💡 Tipp: Teilen Sie Ihre Städte in kleinere Gruppen auf und generieren Sie diese nacheinander.', 'info');
                        }
                        
                        // Hinweis auf Übersicht prüfen
                        CPG.showNotice('ℹ️ Die Seiten wurden möglicherweise trotzdem erstellt. Prüfen Sie den "Übersicht"-Tab oder aktualisieren Sie die Seite.', 'info');
                        
                        // Übersicht-Tab Button hervorheben
                        $('#cpg-tab-overview').css('background-color', '#ff9800').css('color', 'white');
                        setTimeout(function() {
                            $('#cpg-tab-overview').css('background-color', '').css('color', '');
                        }, 5000);
                        
                    } else if (status === 'timeout') {
                        CPG.showNotice('⏱️ Maximale Wiederholungen erreicht. Bitte versuchen Sie kleinere Batches.', 'error');
                    } else {
                        CPG.showNotice('❌ Ajax-Fehler: ' + error + ' (Status: ' + xhr.status + ')', 'error');
                    }
                },
                complete: function() {
                    CPG.isGenerating = false;
                    setTimeout(function() {
                        $('#cpg-progress').hide();
                    }, 3000);
                }
            });
        },

        // Batch-Verarbeitung für große Seitenmengen
        startBatchProcessing: function(batchData) {
            // Benutzerdefinierte Batch-Einstellungen abrufen
            var batchSize = parseInt($('#batch_size').val()) || 15;
            var batchDelay = parseInt($('#batch_delay').val()) || 2;
            
            // Batch-Daten mit benutzerdefinierten Einstellungen erweitern
            batchData.batch_size = batchSize;
            batchData.batch_delay = batchDelay;
            batchData.total_batches = Math.ceil(batchData.total_cities / batchSize);
            
            CPG.batchData = batchData;
            CPG.currentBatch = 0;
            CPG.allResults = [];
            
            // Batch-Daten im localStorage speichern
            localStorage.setItem('cpg_batch_data', JSON.stringify(batchData));
            localStorage.setItem('cpg_current_batch', '0');
            localStorage.setItem('cpg_all_results', JSON.stringify([]));
            
            // Schöne Batch-Verarbeitungsanzeige erstellen
            CPG.showBatchProgress();
            
            // Ersten Batch starten
            CPG.processNextBatch();
        },

        // Zeigt eine schöne Batch-Verarbeitungsanzeige
        showBatchProgress: function() {
            var html = '<div class="cpg-batch-info">';
            html += '<h4><span class="batch-icon">⚡</span>Batch-Verarbeitung gestartet</h4>';
            html += '<p>Ihre ' + CPG.batchData.total_cities + ' Seiten werden in ' + CPG.batchData.total_batches + ' kleinen Gruppen erstellt, um den Server zu schonen.</p>';
            html += '<p><strong>Einstellungen:</strong> ' + CPG.batchData.batch_size + ' Seiten pro Batch, ' + CPG.batchData.batch_delay + ' Sekunden Pause</p>';
            html += '</div>';
            
            html += '<div class="cpg-batch-progress">';
            html += '<div class="cpg-batch-status">';
            html += '<div class="spinner"></div>';
            html += '<div class="cpg-batch-details">';
            html += '<strong>Status:</strong> <span id="cpg-batch-status-text">Vorbereitung...</span><br>';
            html += '<strong>Fortschritt:</strong> <span id="cpg-batch-progress-text">0%</span>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="cpg-progress-enhanced">';
            html += '<div class="cpg-progress-fill-enhanced" id="cpg-batch-progress-bar" style="width: 0%"></div>';
            html += '</div>';
            
            html += '<div class="cpg-batch-stats">';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" id="cpg-batch-current">0</span>';
            html += '<span class="cpg-batch-stat-label">Aktueller Batch</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" id="cpg-batch-total">' + CPG.batchData.total_batches + '</span>';
            html += '<span class="cpg-batch-stat-label">Gesamt Batches</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" id="cpg-batch-pages">0</span>';
            html += '<span class="cpg-batch-stat-label">Erstellte Seiten</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" id="cpg-batch-remaining">' + CPG.batchData.total_cities + '</span>';
            html += '<span class="cpg-batch-stat-label">Verbleibend</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Neue Generierung Button auch während der Verarbeitung
            html += '<div style="margin-top: 20px; text-align: center; padding: 15px; background: #fff3cd; border-radius: 6px; border: 1px solid #ffeaa7;">';
            html += '<p style="margin: 0 0 10px 0; color: #856404; font-size: 14px;"><strong>💡 Tipp:</strong> Sie können zu anderen Tabs wechseln - die Verarbeitung läuft weiter!</p>';
            html += '<button type="button" id="cpg-new-generation-running" class="button button-secondary">Neue Generierung starten</button>';
            html += '</div>';
            
            // Alte Fortschrittsanzeige verstecken
            $('#cpg-progress').hide();
            
            // Neue Anzeige einfügen
            $('#cpg-results').html(html).show();
            
            // Event-Handler für den Button während der Verarbeitung
            $('#cpg-new-generation-running').on('click', function() {
                if (confirm('Möchten Sie wirklich eine neue Generierung starten? Die aktuelle Verarbeitung wird im Hintergrund fortgesetzt.')) {
                    // Alte Batch-Daten aufräumen vor dem Neustart
                    CPG.cleanupBatchData();
                    window.location.reload();
                }
            });
        },

        // Aktualisiert die Batch-Fortschrittsanzeige
        updateBatchProgress: function() {
            if (!CPG.batchData) return;
            
            var currentBatch = CPG.currentBatch + 1;
            var totalBatches = CPG.batchData.total_batches;
            var progress = Math.round((CPG.currentBatch / totalBatches) * 100);
            var createdPages = CPG.allResults.length;
            var remaining = CPG.batchData.total_cities - createdPages;
            
            // Fortschrittsbalken aktualisieren
            $('#cpg-batch-progress-bar').css('width', progress + '%');
            
            // Status-Text aktualisieren
            if (CPG.currentBatch < totalBatches) {
                $('#cpg-batch-status-text').text('Batch ' + currentBatch + ' von ' + totalBatches + ' wird verarbeitet...');
            } else {
                $('#cpg-batch-status-text').text('Alle Batches abgeschlossen!');
            }
            
            // Statistiken aktualisieren
            $('#cpg-batch-progress-text').text(progress + '%');
            $('#cpg-batch-current').text(currentBatch);
            $('#cpg-batch-pages').text(createdPages);
            $('#cpg-batch-remaining').text(remaining);
        },

        // Zeigt die Abschlussmeldung für die Batch-Verarbeitung
        showBatchComplete: function() {
            var html = '<div class="cpg-batch-info" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">';
            html += '<h4><span class="batch-icon">✅</span>Batch-Verarbeitung abgeschlossen!</h4>';
            html += '<p>Alle ' + CPG.batchData.total_cities + ' Seiten wurden erfolgreich erstellt.</p>';
            html += '</div>';
            
            html += '<div class="cpg-batch-progress">';
            html += '<div class="cpg-batch-status">';
            html += '<div class="cpg-batch-details">';
            html += '<strong>Status:</strong> <span style="color: #10b981; font-weight: 600;">Abgeschlossen</span><br>';
            html += '<strong>Ergebnis:</strong> <span style="color: #10b981; font-weight: 600;">' + CPG.allResults.length + ' Seiten erstellt</span>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="cpg-progress-enhanced">';
            html += '<div class="cpg-progress-fill-enhanced" style="width: 100%; background: linear-gradient(90deg, #10b981, #059669);"></div>';
            html += '</div>';
            
            html += '<div class="cpg-batch-stats">';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" style="color: #10b981;">' + CPG.batchData.total_batches + '</span>';
            html += '<span class="cpg-batch-stat-label">Abgeschlossene Batches</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" style="color: #10b981;">' + CPG.allResults.length + '</span>';
            html += '<span class="cpg-batch-stat-label">Erstellte Seiten</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" style="color: #10b981;">0</span>';
            html += '<span class="cpg-batch-stat-label">Verbleibend</span>';
            html += '</div>';
            html += '<div class="cpg-batch-stat">';
            html += '<span class="cpg-batch-stat-number" style="color: #10b981;">100%</span>';
            html += '<span class="cpg-batch-stat-label">Fortschritt</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Ergebnisse anzeigen
            html += '<div style="margin-top: 20px;">';
            html += '<h3>Generierungsergebnisse</h3>';
            html += '<div class="cpg-results-list">';
            
            CPG.allResults.forEach(function(result) {
                var cssClass = result.success ? 'success' : 'error';
                html += '<div class="cpg-result-item ' + cssClass + '">';
                html += '<div class="cpg-result-info">';
                
                if (result.success) {
                    html += '<div class="cpg-result-title">' + result.title + '</div>';
                    html += '<div class="cpg-result-meta">Stadt: ' + result.city + ' | Slug: <code>' + result.slug + '</code></div>';
                } else {
                    html += '<div class="cpg-result-title">Fehler für ' + result.city + '</div>';
                    html += '<div class="cpg-result-meta">' + result.error + '</div>';
                }
                
                html += '</div>';
                
                if (result.success) {
                    html += '<div class="cpg-result-actions">';
                    html += '<a href="' + result.edit_url + '" class="button button-small">Bearbeiten</a>';
                    html += '<a href="' + result.preview_url + '" class="button button-small" target="_blank">Vorschau</a>';
                    html += '</div>';
                }
                
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
            
            // Neue Generierung Button hinzufügen
            html += '<div style="margin-top: 20px; text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e5e7eb;">';
            html += '<h4 style="margin: 0 0 10px 0; color: #374151;">Neue Generierung starten</h4>';
            html += '<p style="margin: 0 0 15px 0; color: #6b7280;">Möchten Sie eine neue Seiten-Generierung starten?</p>';
            html += '<button type="button" id="cpg-new-generation" class="button button-primary">Neue Generierung</button>';
            html += '<p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 12px;">🔄 Die aktuelle Batch-Verarbeitung läuft dauerhaft im Hintergrund weiter</p>';
            html += '</div>';
            
            $('#cpg-results').html(html);
            
            // Event-Handler für den neuen Button
            $('#cpg-new-generation').on('click', function() {
                // Alte Batch-Daten aufräumen vor dem Neustart
                CPG.cleanupBatchData();
                window.location.reload();
            });
            
            // Batch-Daten NICHT automatisch aufräumen - läuft dauerhaft im Hintergrund
            // bis User explizit "Neue Generierung" klickt
            
            // Ergebnisse dauerhaft anzeigen - keine automatische Aktualisierung
            // Der Benutzer kann manuell die Seite aktualisieren oder eine neue Generierung starten
        },

        processNextBatch: function() {
            if (!CPG.batchData || CPG.currentBatch >= CPG.batchData.total_batches) {
                // Alle Batches abgeschlossen - aber NICHT automatisch aufräumen
                CPG.updateBatchProgress();
                CPG.showBatchComplete();
                return;
            }

            // Fortschritt aktualisieren
            CPG.updateBatchProgress();

            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                timeout: 120000, // 2 Minuten pro Batch
                data: {
                    action: 'cpg_generate_pages_batch',
                    nonce: cpgAjax.nonce,
                    batch_id: CPG.batchData.batch_id,
                    batch_number: CPG.currentBatch
                },
                success: function(response) {
                    if (response.success) {
                        var batchResults = response.data.results || [];
                        CPG.allResults = CPG.allResults.concat(batchResults);
                        CPG.currentBatch++;
                        
                        // Batch-Daten im localStorage aktualisieren
                        localStorage.setItem('cpg_current_batch', CPG.currentBatch.toString());
                        localStorage.setItem('cpg_all_results', JSON.stringify(CPG.allResults));
                        
                        // Pause zwischen Batches (benutzerdefiniert)
                        var delay = (CPG.batchData.batch_delay || 2) * 1000;
                        setTimeout(function() {
                            CPG.processNextBatch();
                        }, delay);
                        
                    } else {
                        CPG.showNotice('❌ Fehler in Batch ' + (CPG.currentBatch + 1) + ': ' + (response.data.message || 'Unbekannter Fehler'), 'error');
                        CPG.isGenerating = false;
                        // Bei Fehlern NICHT automatisch aufräumen - User kann manuell "Neue Generierung" klicken
                        // CPG.cleanupBatchData();
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'timeout') {
                        CPG.showNotice('⏱️ Timeout in Batch ' + (CPG.currentBatch + 1) + '. Versuche erneut...', 'warning');
                        setTimeout(function() {
                            CPG.processNextBatch();
                        }, 5000);
                    } else {
                        CPG.showNotice('❌ Fehler in Batch ' + (CPG.currentBatch + 1) + ': ' + error, 'error');
                        CPG.isGenerating = false;
                        // Bei Fehlern NICHT automatisch aufräumen - User kann manuell "Neue Generierung" klicken
                        // CPG.cleanupBatchData();
                    }
                }
            });
        },

        // Gemini KI: Verbindung prüfen
        checkGeminiConnection: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $text = $('#cpg-gemini-status-text');
            $btn.prop('disabled', true).text('Prüfe...');
            $text.text('Wird geprüft...').css('color', '#6b7280');

            var tempKey = ($('#cpg_gemini_api_key').val() || '').trim();

            $.ajax({
                url: cpgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpg_test_gemini',
                    nonce: cpgAjax.nonce,
                    temp_api_key: tempKey
                },
                success: function(response) {
                    if (response && response.success) {
                        $text.text('Verbunden').css('color', '#16a34a');
                    } else {
                        var msg = (response && response.data && response.data.message) ? response.data.message : 'Verbindung fehlgeschlagen';
                        $text.text('Nicht verbunden: ' + msg).css('color', '#dc2626');
                    }
                },
                error: function() {
                    $text.text('Nicht verbunden: AJAX-Fehler').css('color', '#dc2626');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Verbindung prüfen');
                }
            });
        },

        // Fügt eine neue Zeile für einfache KI-Platzhalter hinzu
        addSimplePlaceholderRow: function(){
            console.log('addSimplePlaceholderRow called');
            var $tbody = $('#cpg-simple-ph-tbody');
            console.log('tbody found:', $tbody.length);
            if (!$tbody.length) return;
            var nextIndex = 0;
            $tbody.find('textarea[name^="cpg_simple_placeholders["]').each(function(){
                var match = (this.name || '').match(/cpg_simple_placeholders\[(\d+)\]\[text\]/);
                if (match) {
                    var idx = parseInt(match[1], 10);
                    if (!isNaN(idx)) nextIndex = Math.max(nextIndex, idx + 1);
                }
            });
            var rowHtml = ''+
                '<tr>'+
                    '<td><input type="text" name="cpg_simple_placeholders['+ nextIndex +'][name]" class="regular-text" placeholder="z.B. angebot"></td>'+
                    '<td><textarea id="cpg_simple_placeholders_'+ nextIndex +'_text" name="cpg_simple_placeholders['+ nextIndex +'][text]" rows="3" class="large-text wp-editor-area" placeholder="Eigener Text..."></textarea></td>'+
                    '<td><button type="button" class="button cpg-add-simple-ph">+</button> <button type="button" class="button cpg-remove-simple-ph">−</button></td>'+
                '</tr>';
            $tbody.append(rowHtml);
            console.log('Row added, new count:', $tbody.find('tr').length);
            try { CPG.initWpEditor('cpg_simple_placeholders_'+ nextIndex +'_text'); } catch(e) { console.warn(e); }
        },

        // Entfernt eine Zeile der einfachen KI-Platzhalter
        removeSimplePlaceholderRow: function(button){
            var $tbody = $('#cpg-simple-ph-tbody');
            var rowCount = $tbody.find('tr').length;
            if (!$tbody.length) return;

            // Mindestens eine Zeile behalten
            if (rowCount <= 1) {
                return;
            }

            // Geklickte Zeile entfernen
            if (button) {
                var $tr = $(button).closest('tr');
                var $area = $tr.find('.wp-editor-area');
                if ($area.length && window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function') {
                    try { window.wp.editor.remove($area.attr('id')); } catch(e) {}
                }
                $tr.remove();
            } else {
                var $last = $tbody.find('tr').last();
                var $areaLast = $last.find('.wp-editor-area');
                if ($areaLast.length && window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function') {
                    try { window.wp.editor.remove($areaLast.attr('id')); } catch(e) {}
                }
                $last.remove();
            }
            // Kein Reindizieren, IDs/Namen bleiben stabil
        },

        // Einfache Platzhalter ohne KI hinzufügen
        addSimplePlaceholderNoKi: function() {
            var tbody = $('#cpg-simple-ph-no-ki-tbody');
            var nextIndex = 0;
            tbody.find('textarea[name^="cpg_simple_placeholders_no_ki["]').each(function(){
                var match = (this.name || '').match(/cpg_simple_placeholders_no_ki\[(\d+)\]\[text\]/);
                if (match) {
                    var idx = parseInt(match[1], 10);
                    if (!isNaN(idx)) nextIndex = Math.max(nextIndex, idx + 1);
                }
            });
            var newRow = '<tr>' +
                '<td><input type="text" name="cpg_simple_placeholders_no_ki['+nextIndex+'][name]" class="regular-text" placeholder="z.B. angebot"></td>' +
                '<td><textarea id="cpg_simple_placeholders_no_ki_'+ nextIndex +'_text" name="cpg_simple_placeholders_no_ki['+nextIndex+'][text]" rows="3" class="large-text wp-editor-area" placeholder="Text der ersetzt werden soll..."></textarea></td>' +
                '<td><button type="button" class="button cpg-add-simple-ph-no-ki">+</button> <button type="button" class="button cpg-remove-simple-ph-no-ki">−</button></td>' +
                '</tr>';
            tbody.append(newRow);
            try { CPG.initWpEditor('cpg_simple_placeholders_no_ki_'+ nextIndex +'_text'); } catch(e) { console.warn(e); }
        },

        // Einfache Platzhalter ohne KI entfernen
        removeSimplePlaceholderNoKi: function(button) {
            var tbody = $('#cpg-simple-ph-no-ki-tbody');
            var rowCount = tbody.find('tr').length;

            // Mindestens eine Zeile behalten
            if (rowCount <= 1) {
                return;
            }

            var $tr = $(button).closest('tr');
            var $area = $tr.find('.wp-editor-area');
            if ($area.length && window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function') {
                try { window.wp.editor.remove($area.attr('id')); } catch(e) {}
            }
            $tr.remove();
            // Kein Reindizieren, IDs/Namen bleiben stabil
        },

        // WP-Editor dynamisch initialisieren
        initWpEditor: function(editorId) {
            if (!editorId) return;
            if (window.wp && window.wp.editor && typeof window.wp.editor.initialize === 'function') {
                try {
                    window.wp.editor.initialize(editorId, {
                        tinymce: {
                            toolbar1: 'formatselect bold italic bullist numlist link unlink blockquote removeformat undo redo',
                            block_formats: 'Absatz=p;Überschrift 2=h2;Überschrift 3=h3;Überschrift 4=h4',
                        },
                        quicktags: true,
                        mediaButtons: true
                    });
                    return;
                } catch(e) {}
            }
            if (window.tinymce && typeof window.tinymce.init === 'function') {
                try {
                    window.tinymce.init({
                        selector: '#'+editorId,
                        menubar: false,
                        toolbar: 'formatselect | bold italic | bullist numlist | link unlink | blockquote removeformat | undo redo',
                        plugins: 'lists link'
                    });
                } catch(e) {}
            }
        },

        // Ein-/Ausklappen aller Editoren in einem Bereich
        toggleEditors: function(targetSelector, expand) {
            var $scope = $(targetSelector);
            if (!$scope.length) return;
            // Für Gutenberg/TinyMCE-Editoren: Toggle der Toolbar/iframe-Wrapper
            $scope.find('.wp-editor-wrap').each(function(){
                var $wrap = $(this);
                if (expand) {
                    $wrap.removeClass('html-active').addClass('tmce-active');
                } else {
                    $wrap.removeClass('tmce-active').addClass('html-active');
                }
            });
        },

        setLetterFilter: function(letter){
            CPG.currentLetter = letter || 'ALL';
            // Button-Active-State aktualisieren
            $('.cpg-letter-filter button').removeClass('button-primary').addClass('button-secondary');
            $('.cpg-letter-filter button[data-letter="'+ CPG.currentLetter +'"]').removeClass('button-secondary').addClass('button-primary');
            CPG.renderCities();
        },

        getFilteredCities: function(){
            if (!Array.isArray(CPG.cities) || CPG.cities.length === 0) return [];
            var list = CPG.cities.slice();
            // Alphabetisch sortieren (name, locale-de)
            try { list.sort(function(a,b){ return (a.name||'').localeCompare((b.name||''), 'de', {sensitivity:'base'}); }); } catch(e) {
                list.sort(function(a,b){ return (a.name||'').toLowerCase() < (b.name||'').toLowerCase() ? -1 : 1; });
            }
            if (CPG.currentLetter && CPG.currentLetter !== 'ALL') {
                var L = String(CPG.currentLetter).toUpperCase();
                list = list.filter(function(c){
                    var n = (c.name||'').trim();
                    if (!n) return false;
                    var first = n.charAt(0).toUpperCase();
                    return first === L;
                });
            }
            return list;
        },
        
        updateSelectedCount: function(){
            var count = Array.isArray(CPG.selectedCities) ? CPG.selectedCities.length : 0;
            $('#cpg-selected-count').text('Ausgewählt: ' + count);
        },
        
    };

    $(document).ready(function() {
        CPG.init();
    });

    window.CPG = CPG;

})(jQuery); 