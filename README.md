## Stadtseiten Generator (WordPress‑Plugin)

Erstellt automatisiert lokalisierte Seiten für mehrere Städte basierend auf einer Quellseite (Elementor oder Bricks). Enthält GeoNames‑basierte Städtesuche, Platzhalter‑Ersetzungen und automatische ALT‑Texte für Bilder im Frontend.

### Installation

- Ordner `createSites11` nach `wp-content/plugins/` kopieren oder als ZIP hochladen
- In WordPress aktivieren: Menü „Seitengenerator“ erscheint

### Voraussetzungen

- WordPress 6.x, PHP ≥ 7.4
- Mindestens ein Page Builder: Elementor oder Bricks (in den Einstellungen wählen)
- GeoNames‑Account (Benutzername in den Einstellungen eintragen)

### Erste Schritte

1. Einstellungen öffnen: Builder auswählen, GeoNames‑Benutzername speichern
2. Quellseite (Vorlage) auswählen
3. Startort, Radius (km), Mindest‑Einwohner einstellen
4. Optional: Thema/Keyword, Slug‑ und Titel‑Pattern definieren
5. „Städte laden“ → Liste prüfen → „Seiten generieren“

### Platzhalter und Texte

- Platzhalter werden in Titel, Inhalt und Meta ersetzt (z. B. `{{stadt}}`, `{{bundesland}}`, `{{thema}}`)
- Thema/Keyword: `theme_keyword` (manuell) oder Fallback auf Website‑Titel (`auto_theme`)

### Bilder und ALT‑Texte

- Frontend‑Filter setzt im finalen HTML auf generierten Seiten das ALT aller `<img>` auf „Thema in [Stadt]“
- Wir überschreiben keine ALT‑Texte in der Mediathek (keine globalen Änderungen)
- Gilt auch in der Vorschau; ggf. Browser/Elementor‑Cache leeren

### SEO

- Unterstützt Yoast und Rank Math (setzt Titel/Description/Keywords, falls aktiv)
- Ohne SEO‑Plugin werden interne Meta‑Felder geschrieben

### Veröffentlichen/Verwalten

- Generierte Seiten sind zunächst Entwürfe; Veröffentlichungs‑/Entwurfs‑ und Lösch‑Aktionen im Generator‑UI
- Interne Tracking‑Tabelle merkt sich Seite ↔ Stadt/Quelle

### GeoNames & TLS‑Fallback

- API‑Aufruf versucht zuerst HTTPS
- Bei lokalen TLS‑Problemen (z. B. cURL error 35) automatischer Fallback auf HTTP

### Fehlerbehebung

- „GeoNames API Fehler“: Benutzername prüfen; bei TLS‑Fehler hilft der eingebaute HTTP‑Fallback
- „Keine Städte“: Startort präzisieren (z. B. „Köln, DE“), Radius erhöhen, Mindest‑Einwohner senken
- Falscher ALT‑Text: Seite neu generieren, Caches leeren

### Deinstallation

- Deaktivieren/Deinstallieren entfernt das Plugin; Inhalte bleiben bestehen

### Hinweise für Entwickler

- Hauptklasse: `city-pages-generator.php`
- Wichtige Klassen: `includes/class-page-generator.php`, `includes/class-city-api.php`, `includes/class-text-replacer.php`
- Stil/JS nur für Admin: `assets/`

### Lizenz

GPL v2 or later
