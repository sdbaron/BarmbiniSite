# Internes Technisches Konzept (Version 2.5)
## Website – Sozialkaufhaus Barmbini
Stand: April 2026 | Intern

---

## 1. Projektübersicht

Ziel ist die Erstellung einer schlanken, barrierearmen und DSGVO-konformen Informationswebsite auf WordPress-Basis.

Kein Online-Shop. Fokus: Information, Vertrauen, Spenden & Besuch vor Ort.

Der Sortiment-Bereich nutzt WooCommerce als reinen Produktkatalog ohne Kaufabwicklung.

| | |
|---|---|
| Entwicklung | Lokal mit Local (Nginx) |
| Hosting | IONOS VPS Linux S+ |
| Datenbank | MariaDB |
| PHP | 8.1+ |

---

## 2. Technische Basis

| | |
|---|---|
| CMS | WordPress (aktuelle stabile Version, getestet mit 6.9.1) |
| PHP | 8.1+ |
| Webserver (lokal) | Nginx |
| Datenbank | MariaDB |
| Hosting | IONOS VPS Linux S+ |
| SSL | ein kostenloses SSL-Zertifikat (z. B. Let's Encrypt) |
| Speicher | 80 GB NVMe SSD |
| Serverstandort | Deutschland |
| Permalink-Struktur | Beitragsname (`/%postname%/`) |
| Migration | All-in-One WP Migration |

---

## 3. Architektur-Grundsätze

* Minimalprinzip bei Plugins
* Kein Page Builder (nur Gutenberg + Kadence Blocks)
* Keine externen Drittanbieter-Services
* Keine eingebetteten Google Maps (nur statische Karte)
* Keine extern geladenen Google Fonts
* Mobile-first Design
* Performance-orientierte Umsetzung
* WooCommerce als reiner Katalog — kein Checkout, keine Zahlung

---

## 4. Sprache

Die Website ist einsprachig auf Deutsch. Keine Mehrsprachigkeit geplant.

| | |
|---|---|
| Sprache | Deutsch |
| URL-Struktur | `example.de/` (kein Sprachpräfix) |

---

## 5. Benutzerrollen

### Administrator

* Volle Systemrechte
* Plugin- und Theme-Verwaltung
* Systemeinstellungen & Benutzerverwaltung
* WooCommerce-Kategorien und Unterkategorien erstellen und pflegen

### Redakteur (Mitarbeiter)

* Inhalte erstellen und bearbeiten
* Blogbeiträge pflegen
* WooCommerce-Produkte erstellen und pflegen
* Kein Zugriff auf Plugins, Theme, Einstellungen

---

## 6. Seitenstruktur

### Hauptnavigation

* Startseite
* Über uns
* Sortiment
* Helfen & Spenden
* Kontakt & Anfahrt
* Neuigkeiten (Direktlink auf `/category/neuigkeiten/`)
* FAQ

### Footer

* Impressum
* Datenschutzerklärung
* Barrierefreiheitserklärung

---

## 7. Detailkonzept der Seiten

### 7.1 Startseite

* Hero-Bereich (Bild + Slogan)
* Kurzvorstellung
* 3 Teaser-Blöcke
* Bildergalerie
* Adresse + Öffnungszeiten
* Statische Karte (Bild) + Button „In Google Maps öffnen"
* Letzte 3 Blogbeiträge

### 7.2 Über uns

* Geschichte, Mission, Team
* Zahlen & Fakten, Bilder

### 7.3 Sortiment

Der Sortiment-Bereich basiert auf WooCommerce als reinem Produktkatalog ohne Warenkorb und Bezahlfunktion.

**Technische Umsetzung:**

* Plugin: WooCommerce (Katalog-Modus)
* Warenkorb und Kasse deaktiviert via Hide Cart Functions
* Produkte mit Foto, Name, Beschreibung, Preis
* Kategorien und Unterkategorien hierarchisch (WooCommerce-Standard)
* Kadence Theme integriert sich nativ mit WooCommerce
* WooCommerce Shop-Seite = Sortiment (für korrekte Breadcrumb-Basis)

**Kategorien:**

* Damenbekleidung & Schuhe (z.B. Pullover, Hosen, Jacken, Schuhe)
* Kindersachen & Spielzeug (z.B. Oberbekleidung, Spielzeug)
* Babybedarf (z.B. Kleidung, Zubehör)
* Kinder- & Erwachsenenbücher (z.B. Kinderbücher, Erwachsenenbücher)

**Seite `/sortiment/` — Anzeigelogik:**

* Hauptkategorien als Abschnitte mit Überschrift
* Unter jeder Hauptkategorie: Unterkategorien via WooCommerce-Shortcode
* Shortcode-Beispiel: `[product_categories parent="61" columns="4"]`
* Klick auf Unterkategorie → WooCommerce Archivseite `/produkt-kategorie/{slug}/`
* Hover über Unterkategorie zeigt Beschreibung (via functions.php + CSS)

**Breadcrumb-Struktur:**

* Produktseite: `Start / Sortiment / Kategorie / Unterkategorie / Produkt`
* Kategorieseite: `Start / Sortiment / Kategorie / Unterkategorie`
* Sortiment-Seite: `Start / Sortiment`

**Produktkarte enthält:**

* Foto, Name, Beschreibung, Preis, Kategorie/Unterkategorie
* Kein „In den Warenkorb" Button
* Anzahl Produkte: 50–200 gesamt

**Datenpflege:**

* Kategorien & Unterkategorien: Administrator
* Produkte: Redakteur oder Administrator

### 7.4 Helfen & Spenden

* Sachspenden (angenommene Waren, Zustand, Abgabezeiten)
* Ehrenamtliches Engagement (Laden, Werkstatt, Büro)
* Kontaktformular (Contact Form 7)
* Optional: Geldspenden-Information

### 7.5 Kontakt & Anfahrt

* Adresse: Alter Teichweg 11, 22081 Hamburg
* Telefon: 040 4294 5339
* E-Mail: agh.kunstkram@verbandshaus-hamburg.de
* Öffnungszeiten (Tabelle)
* Statische Karte + Google Maps Link
* Kontaktformular
* ÖPNV-Hinweise (U3 Haltestelle Barmbek)

### 7.6 Neuigkeiten

* Direktlink im Menü auf WordPress-Kategorie `/category/neuigkeiten/`
* Kategorien: Neuigkeiten, Aktionen, Rückblick
* Technische Blog-Seite erstellt als Anker für WordPress (nicht im Menü sichtbar)

### 7.7 FAQ

* Accordion-Struktur
* Inhalte erstellt auf Basis verfügbarer Projektinformationen

### 7.8 Impressum

* Pflichtangaben gemäß §5 TMG

### 7.9 Datenschutzerklärung

* Individuell angepasst gemäß DSGVO
* Enthält: Verantwortlicher, Hosting (IONOS), Kontaktformular, Cookies, Betroffenenrechte
* Zuständige Aufsichtsbehörde: Hamburgischer Beauftragter für Datenschutz und Informationsfreiheit

### 7.10 Barrierefreiheitserklärung

* Freiwillige Erklärung (keine gesetzliche Pflicht für private Träger)
* Orientierung an WCAG 2.1 Level AA
* Enthält: Umgesetzte Maßnahmen, bekannte Einschränkungen, Kontakt für Feedback

---

## 8. Plugin-Liste

| Plugin | Zweck | Phase |
|---|---|---|
| WooCommerce | Produktkatalog (ohne Warenkorb/Zahlung) | Phase 1 |
| Hide Cart Functions | Warenkorb und Kasse deaktivieren | Phase 1 |
| Contact Form 7 | Kontaktformulare | Phase 1 |
| Yoast SEO | SEO + Breadcrumbs | Phase 1 |
| Kadence Blocks | Erweiterte Gutenberg-Blöcke | Phase 1 |
| All-in-One WP Migration | Migration auf IONOS | Phase 1 |
| WP Super Cache | Caching (lokal) | Phase 1 |
| FooGallery | Galerie mit Lightbox | Phase 1 |
| Simple Local Avatars | Lokale Benutzer-Avatare | Phase 1 |
| Brevo (Newsletter) | E-Mail-Rausendung / Newsletter | Phase 2 |

---

## 9. WooCommerce-Konfiguration

### Einstellungen

* WooCommerce → Einstellungen → Produkte → Shop-Seite: **Sortiment**

### Deaktivierte Funktionen

* Warenkorb (Hide Cart Functions)
* Kasse / Checkout (Hide Cart Functions)
* Menü-Punkte: Shop, Warenkorb, Kasse (manuell entfernt)
* Ergebniszähler via CSS
* Sortierung-Dropdown via CSS
* Anzahl Artikel in Kategorien via Filter

### Anpassungen in functions.php

**Anzahl Artikel ausblenden:**
```php
add_filter( 'woocommerce_subcategory_count_html', '__return_null' );
```

**Ergebniszähler entfernen:**
```php
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
```

**Breadcrumbs auf Produktseiten:**
```php
add_action( 'woocommerce_before_main_content', function() {
    woocommerce_breadcrumb();
}, 5 );
```

**Kategoriebeschreibung bei Hover:**
```php
add_action( 'woocommerce_after_subcategory_title', function( $category ) {
    if ( $category->description ) {
        echo '<div class="category-description">'
            . wp_kses_post( $category->description ) . '</div>';
    }
}, 10, 1 );
```

**Breadcrumb mit Sortiment als Basis:**
```php
add_filter( 'woocommerce_get_breadcrumb', function( $crumbs ) {
    if ( is_page( 'sortiment' ) || is_shop() ) return $crumbs;

    $sortiment_page = get_page_by_path( 'sortiment' );
    if ( ! $sortiment_page ) return $crumbs;

    // Prüfen ob Sortiment bereits im Breadcrumb vorhanden
    foreach ( $crumbs as $crumb ) {
        if ( $crumb[1] === get_permalink( $sortiment_page ) ) return $crumbs;
    }

    array_splice( $crumbs, 1, 0, array( array( 'Sortiment', get_permalink( $sortiment_page ) ) ) );
    return $crumbs;
} );
```

### CSS-Anpassungen (Customizer)

**Kategorienamen zentrieren:**
```css
.product-category .woocommerce-loop-category__title {
    text-align: center;
}
```

**Hover-Beschreibung:**
```css
.product-category .category-description {
    display: none;
    text-align: center;
    font-size: 0.85em;
    padding: 5px;
}

.product-category:hover .category-description {
    display: block;
}
```

**Ergebniszähler und Sortierung ausblenden:**
```css
.woocommerce-result-count { display: none; }
.woocommerce-ordering { display: none; }
```

**Kadence Breadcrumbs auf WooCommerce-Seiten ausblenden:**
```css
.kadence-breadcrumbs { display: none; }
```

---

## 10. DSGVO & Rechtliches

* SSL aktiv
* Keine externen Dienste, Fonts oder Tracking-Tools
* Keine eingebetteten Videos
* Statische Karte statt Google Maps iframe
* Datenschutzerklärung individuell angepasst (erstellt)
* Barrierefreiheitserklärung erstellt (freiwillig)
* Impressum gemäß §5 TMG
* Serverstandort Deutschland
* Cookie-Banner nur falls technisch erforderlich
* Newsletter (Phase 2): Double Opt-in, Einwilligung, Recht auf Löschung
* Newsletter-Daten: Vorname, Nachname, E-Mail

---

## 11. Performance

* Bilder vor Upload komprimieren, max. Bildbreite 1600px
* Lazy Loading aktiv
* Caching über Hosting
* WooCommerce-Skripte nur auf Shop-Seiten laden (Phase 2)

Ziel: PageSpeed > 85

---

## 12. Administrationskonzept

* Schulung der Mitarbeiter (ca. 1 Stunde)
* Dokumentation für: Inhalte bearbeiten, Blogbeiträge erstellen, Produkte anlegen
* Keine technischen Eingriffe durch Redakteure

---

## 13. Projektphasen

### Phase 1 — Launch (4 Wochen)

| Woche | Inhalt |
|---|---|
| Woche 1 | Struktur + Grunddesign |
| Woche 2 | Inhalte einpflegen |
| Woche 3 | Testing (Formulare, SEO, WooCommerce) |
| Woche 4 | Migration + Go-Live |

### Phase 2 — Nach Go-Live

* 2FA + Limit Login Attempts + Security Headers
* Offsite Backup (wöchentlich full, täglich DB)
* Gzip/Brotli, WebP-Bilder, Cache-Control
* Uptime Monitoring + Error Logging
* Staging-Umgebung + Git-Versionsverwaltung
* XML-Sitemap + Schema.org
* Newsletter (Brevo)
* Optional: Spendenfunktion, Terminbuchung, WCAG AA

---

## 14. Risiken

* Plugin-Updates müssen regelmäßig geprüft werden
* Redakteure benötigen klare Anleitung (ca. 1 Stunde Schulung geplant)
* WooCommerce ist schwergewichtiger als minimale Lösung — Performance beobachten
* WooCommerce-Updates können Darstellung beeinflussen — regelmäßig testen

---

Stand: April 2026 | Version 2.5 | Intern
