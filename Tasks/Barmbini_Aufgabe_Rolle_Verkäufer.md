# Detaillierte Aufgabe: Benutzerrolle „Verkäufer" einführen

## Ziel

Eine neue WordPress-Benutzerrolle **„Verkäufer"** einführen, die ausschließlich die Verwaltung der **Sortiment-Produkte** (WooCommerce-Produkte) ermöglicht:

- neue Artikel anlegen
- Preise anpassen
- Artikel als **ausverkauft** markieren (nativer WooCommerce-Lagerstatus)
- Artikel löschen (in den Papierkorb)

Die Rolle wird als Modul im Plugin `barmbini-core` umgesetzt (Architekturregel: Fachlogik ins Plugin, nicht ins Theme). Die bestehende Rolle **Redakteur** bleibt unverändert parallel bestehen.

## Quellenbasis

- `Barmbini_Technisches_Konzept_v2.5.md` — §5 Benutzerrollen (muss erweitert werden)
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur, Modulzuschnitt
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand
- dem bestehenden Plugin `wp-content/plugins/barmbini-core/`:
  - `barmbini-core.php` (Plugin-Bootstrap, `require_once`-Liste, Version)
  - `includes/class-plugin.php` (Modulregistrierung)
  - `includes/class-activator.php` (Aktivierung)
  - `includes/class-deactivator.php` (Deaktivierung)
  - `uninstall.php` (bewusst **kein** automatisches Löschen produktiver Daten)
- dem Muster der Security-Module (z. B. `includes/security/class-login-limiter.php`): Klasse mit `register()`-Methode

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog, nicht als klassischer Shop mit Checkout.
- Neue Fachlogik gehört in das Plugin `barmbini-core`, nicht in Theme oder MU-Plugins.
- Das Projekt folgt dem Minimalprinzip bei Plugins — keine neuen Plugins, WP/WooCommerce-Bordmittel bevorzugen.
- Die Rolle „Redakteur" bleibt unverändert bestehen.
- Angesichts des dokumentierten Sicherheitsvorfalls gilt: **minimale Rechte**, keine System- oder Benutzerrechte für die neue Rolle.
- „Reversible where possible": Löschen bedeutet **Papierkorb**, nicht permanentes Löschen.

## Entscheidungen aus der Abstimmung (2026-08-19)

| # | Frage | Entscheidung |
|---|-------|--------------|
| 1 | Welche Produkte darf der Verkäufer verwalten? | **Alle Produkte** (auch fremde) → `edit_others_products`, `delete_others_products` |
| 2 | Wie wird „ausverkauft" markiert? | **(A) Nativer WooCommerce-Lagerstatus** (`_stock_status` = `outofstock`) — kein eigenes Meta-Feld |
| 3 | Darf die Rolle auch `barmbini_aktion` pflegen? | **Nein, ausschließlich Sortiment-Produkte** |
| 4 | Wird der Redakteur ersetzt? | **Nein, bleibt parallel bestehen** |

## Verifizierter Ist-Stand (2026-08-19)

| Komponente | Status |
|---|---|
| Bestehende Rollen | `administrator`, `editor`, `author`, `contributor`, `subscriber` (Standard) |
| Rolle „Redakteur" | kann bereits WooCommerce-Produkte erstellen/pflegen + Blog-Inhalte |
| Plugin-Version `barmbini-core` | `0.5.2` (Header + `BARMBINI_CORE_VERSION`) |
| `uninstall.php` | vorhanden, löscht bewusst **keine** produktiven Daten |
| `register_activation_hook` / `register_deactivation_hook` | vorhanden (`class-activator.php`, `class-deactivator.php`) |
| Lagerstatus im Plugin-Code | bisher **keine** `outofstock`/`stock_status`-Verarbeitung im Plugin (nur WooCommerce-Standard) |
| Modulregistrierung | über `Barmbini_Core_Plugin` (Methoden `register_*_module()`) + `require_once` in `barmbini-core.php` |

## Umzusetzender Funktionsumfang

1. Neue Rolle `barmbini_verkaeufer` (Anzeigename: **Verkäufer**) wird definiert.
2. Der Verkäufer kann: Produkte anlegen, veröffentlichen, Preise ändern, Lagerstatus auf „ausverkauft" setzen, Produkte in den Papierkorb verschieben.
3. Der Verkäufer sieht ausschließlich die Menüpunkte **Dashboard, Medien, Produkte** — keine Inhalte/Blog, keine Aktionen, keine Plugins/Theme/Einstellungen, keine Benutzerverwaltung.
4. Der Verkäufer kann **keine** Produktkategorien anlegen, umbenennen oder löschen (nur bestehende zuordnen).
5. Kein permanentes Löschen für diese Rolle (nur Papierkorb) — empfohlen, optional.
6. Die Rolle wird bei Plugin-Aktivierung angelegt und heilt sich bei Bedarf selbst nach (idempotent).
7. Rollen-Lebenszyklus respektiert die Projektentscheidung aus `uninstall.php`: **kein automatisches Löschen** produktiver Daten.

## Nicht Bestandteil dieser Aufgabe

- Änderung der Rolle **Redakteur** (bleibt unverändert)
- Eigene „Ausverkauft"-Anzeige/Badge im Frontend (Theme/Catalog) — der native Lagerstatus wird genutzt; ein Badge ist ein separates Folge-Ticket, falls gewünscht
- Zugriff auf `barmbini_aktion` oder andere Custom Post Types
- Benutzerverwaltung, Plugin-/Theme-Verwaltung, Systemeinstellungen für die Rolle
- Änderungen am Theme oder an `functions.php`
- Checkout-/Zahlungslogik

---

## Aufgabe

### 1. Neue Rolle-Datei anlegen

**Ziel:** Eine neue PHP-Klasse im neuen Modul `roles/`, die die Rolle definiert und idempotent anlegt.

**Neue Datei:** `wp-content/plugins/barmbini-core/includes/roles/class-seller-role.php`

**Klasse:** `Barmbini_Core_Seller_Role`

**Rollen-Slug:** `barmbini_verkaeufer` (Präfix `barmbini_` verhindert Kollisionen mit anderen Plugins)

**Anzeigename:** `Verkäufer`

#### 1a. Methodenübersicht

| Methode | Sichtbarkeit | Beschreibung |
|---------|-------------|--------------|
| `register()` | `public` | Hängt `maybe_create_role` an `admin_init` (nur für `manage_options`-Nutzer) |
| `maybe_create_role()` | `public` | Idempotent: prüft `get_role()`, legt Rolle nur an, wenn sie fehlt |
| `get_capabilities()` | `public static` | Liefert die Capability-Matrix (ein Ort der Wahrheit, auch testbar) |
| `get_role_slug()` | `public static` | Liefert `'barmbini_verkaeufer'` |

#### 1b. Capability-Matrix

```php
public static function get_capabilities() {
	return array(
		// Basis
		'read'                => true,
		'upload_files'        => true, // Produktfotos
		// Produkte verwalten (alle Produkte)
		'edit_products'           => true,
		'edit_published_products' => true, // Preise an gepublishten Artikeln
		'edit_others_products'    => true, // alle Produkte (Entscheidung #1)
		'publish_products'        => true,
		'delete_products'         => true,
		'delete_published_products' => true,
		'delete_others_products'  => true, // alle Produkte (Entscheidung #1)
		// Kategorien nur zuordnen, nicht verwalten
		'assign_product_terms'    => true,
	);
}
```

**Bewusst NICHT vergeben (und warum):**

| Capability | Grund |
|---|---|
| `manage_woocommerce` | Shop-Manager-Ebene, zu mächtig |
| `manage_product_terms`, `edit_product_terms`, `delete_product_terms` | Kein Anlegen/Umbenennen/Löschen von Kategorien |
| `edit_posts`, `edit_pages`, `publish_posts`, `delete_posts`, `edit_others_posts` | Kein Blog-/Inhalte-Zugriff |
| `edit_users`, `list_users`, `promote_users`, `remove_users` | Keine Benutzerverwaltung |
| `activate_plugins`, `update_plugins`, `install_plugins`, `update_themes`, `switch_themes`, `edit_plugins`, `edit_themes`, `update_core` | Keine Systemrechte |
| `manage_options` | Keine Einstellungen |
| `read_private_products` | Keine privaten Produkte (bewusste Entscheidung; ggf. später) |

**„Ausverkauft" markieren (Entscheidung #2 / Option A):**
- Kein eigener Code nötig. WooCommerce zeigt im Produkteditor unter **„Lagerbestand" (Inventory)** das Feld **„Lagerstatus"** mit `_stock_status` = `instock` / `outofstock`.
- Die vergebenen Produkt-Capabilities reichen dafür aus. → Einfach per WP-CLI-Verifikation testen (siehe §7).

### 2. Plugin-Bootstrap erweitern

**Datei:** `wp-content/plugins/barmbini-core/barmbini-core.php`

1. `require_once` für die neue Datei ergänzen:
   ```php
   require_once BARMBINI_CORE_PATH . 'includes/roles/class-seller-role.php';
   ```
2. Version `0.5.2` → `0.6.0` erhöhen:
   - Plugin-Header: `Version: 0.6.0`
   - `define( 'BARMBINI_CORE_VERSION', '0.6.0' );`

### 3. Modulregistrierung

**Datei:** `wp-content/plugins/barmbini-core/includes/class-plugin.php`

1. Im Konstruktor neue Methode aufrufen:
   ```php
   $this->register_seller_role_module();
   ```
2. Neue Methode:
   ```php
   protected function register_seller_role_module() {
       $seller_role = new Barmbini_Core_Seller_Role();
       $seller_role->register();
   }
   ```

### 4. Rollen-Lebenszyklus (Aktivierung / Deaktivierung / Uninstall)

- **Aktivierung** (`class-activator.php::activate()`): `Barmbini_Core_Seller_Role::maybe_create_role();` aufrufen.
- **Deaktivierung** (`class-deactivator.php`): **nichts** an der Rolle ändern — Deaktivierung kann temporär sein und darf Zuordnungen nicht zerstören.
- **Uninstall** (`uninstall.php`): **keine** Rolle entfernen — konsistent zur bestehenden Projektentscheidung „Absichtlich kein automatisches Löschen produktiver Daten". Falls später doch nötig: nur per dokumentiertem WP-CLI-Befehl manuell.
- **Selbstheilung**: Da das Plugin auf dem Server bereits aktiv ist, feuert der Aktivierungs-Hook bei einem reinen Code-Deploy nicht. Deshalb prüft `register()` zusätzlich auf `admin_init` (nur für Nutzer mit `manage_options`), ob die Rolle existiert, und legt sie bei Bedarf an. So greift die Rolle nach dem Deploy automatisch beim ersten Admin-Besuch.

### 5. Permanentes Löschen verhindern (empfohlen, optional)

Damit „Artikel löschen" **reversibel** bleibt (Leitplanke):

- In `Barmbini_Core_Seller_Role` einen Filter `map_meta_cap` registrieren:
  - Wenn der Nutzer die Rolle `barmbini_verkaeufer` hat und `delete_post` auf ein **bereits gelöschtes (Trash)** Produkt angewendet wird → Ergebnis auf `'do_not_allow'` setzen.
  - Ergebnis: Der Verkäufer kann Produkte **in den Papierkorb** verschieben, aber nicht **endgültig** löschen.
- Hinweis: Das „Papierkorb leeren"-Menü wird dadurch für diese Rolle ebenfalls blockiert.

### 6. Admin-Oberfläche des Verkäufers (Verifikation)

Mit der Capability-Matrix erwartet der Verkäufer folgende Ansicht:

| Menüpunkt | Sichtbar? | Begründung |
|---|---|---|
| Dashboard | ✅ | `read` |
| Medien | ✅ | `upload_files` |
| Produkte (Alle, Neu hinzufügen) | ✅ | Produkt-Caps |
| Produkte → Kategorien / Attribute | ❌ | `manage_product_terms` fehlt |
| Beiträge / Seiten | ❌ | `edit_posts`/`edit_pages` fehlt |
| Aktionen (`barmbini_aktion`) | ❌ | CPT nutzt Inhalte-Caps (verifizieren) |
| WooCommerce (Settings) | ❌ | `manage_woocommerce` fehlt |
| Plugins / Design / Einstellungen | ❌ | fehlende System-Caps |
| Benutzer | ❌ | `list_users` fehlt |

### 7. Tests

#### 7a. Unit-Test (PHPUnit, analog `tests/AddressShortcodeTest.php`)

**Neue Datei:** `tests/SellerRoleTest.php`

- `test_capabilities_include_product_management`: `get_capabilities()` enthält `edit_products`, `edit_others_products`, `publish_products`, `delete_products`, `delete_others_products`, `assign_product_terms`, `upload_files`, `read`.
- `test_capabilities_exclude_admin_rights`: enthält **nicht** `manage_options`, `edit_users`, `activate_plugins`, `edit_posts`, `manage_woocommerce`, `manage_product_terms`.
- `test_maybe_create_role_is_idempotent`: `maybe_create_role()` zweimal aufrufen → Rolle existiert genau einmal (kein Fehler).
- `test_role_slug_is_barmbini_verkaeufer`.

#### 7b. Lokale Integrationstests

1. Plugin in Local reaktivieren oder `maybe_create_role()` ausführen.
2. Testnutzer mit Rolle `barmbini_verkaeufer` anlegen (WP-CLI).
3. Als Testnutzer einloggen und prüfen:
   - ✅ Neues Produkt anlegen & veröffentlichen
   - ✅ Preis eines fremden, veröffentlichten Produkts ändern
   - ✅ Lagerstatus auf `outofstock` setzen („Ausverkauft")
   - ✅ Produkt in den Papierkorb verschieben
   - (falls §5 umgesetzt) ❌ endgültiges Löschen aus dem Papierkorb
   - ❌ Produktkategorie anlegen
   - ❌ Blogbeitrag anlegen/bearbeiten
   - ❌ Zugriff auf Plugins/Theme/Einstellungen/Benutzer

#### 7c. Live-Verifikation (nach Deploy, Modus B)

- WP-CLI: Rolle vorhanden prüfen (`wp role list`)
- WP-CLI: Testnutzer mit Rolle anlegen
- Frontend-/Admin-Check wie 7b

### 8. Dokumentation aktualisieren

1. **`Barmbini_Technisches_Konzept_v2.5.md`** — §5 Benutzerrollen: Tabelle um Zeile „Verkäufer" ergänzen (Sortiment-Produkte verwalten, keine System-/Inhaltsrechte, kein Kategorien-Management).
2. **`Barmbini_Plugin_Architektur_barmbini-core.md`** — Zielstruktur um Modul `roles/` + `class-seller-role.php` erweitern, Modulzuschnitt ergänzen.
3. **`Barmbini_Vorbereitung_Features_und_Bugfixes.md`** — Ist-Stand/Status der neuen Rolle festhalten.
4. **`Docs/Barmbini_Anleitung_Aktionen_Admin.md`** (falls dort Rollen beschrieben) — kurzen Abschnitt „Verkäufer: Artikel verwalten" ergänzen (Bedienung des Produkteditors, Ausverkauft-Status, Papierkorb).
5. Nach Abschluss: Ergebnis/Entscheidungen in diesem Task-Dokument nachtragen (Outcome-Sektion).

## Deployment / Rollout

- **Modus B** (Code only, keine SQL-Importe) über `deploy.ps1` — Live-Daten bleiben unberührt.
- Nach dem Deploy läuft die Rollen-Selbstheilung (`admin_init`) beim ersten Admin-Besuch; alternativ sofort:
  ```bash
  wp eval 'Barmbini_Core_Seller_Role::maybe_create_role();'
  ```
- Danach `wp role list` → `barmbini_verkaeufer` muss erscheinen.
- Testnutzer anlegen, Rechte-Check durchführen, danach Testnutzer entfernen.

## Risiken und offene Fragen

| Thema | Risiko / Frage | Empfehlung |
|---|---|---|
| Capability-Mapping | Wenn `barmbini_aktion` eigene Caps nutzt, kann Sichtbarkeit abweichen | In 7b/7c explizit prüfen |
| Dauerhaftes Löschen | Verkäufer könnte Produkte endgültig löschen | §5 umsetzen (Papierkorb-only) |
| Rollen-Selbstheilung | `admin_init`-Check nur für `manage_options`-Nutzer | Absicherung gegen Missbrauch, dokumentiert |
| Bestehende Zuordnungen | Falls Rolle später umbenannt/entfernt wird | Nur manuell per WP-CLI, nie automatisch |
| Datenschutz | Interne Mitarbeiterrolle, keine Kundendaten-Verarbeitung | Minimaler DSGVO-Aufwand; in Doku Auditierbarkeit festhalten |

## Akzeptanzkriterien

- [ ] Rolle `barmbini_verkaeufer` existiert lokal und live, Anzeigename „Verkäufer"
- [ ] Verkäufer kann Produkt anlegen, Preis ändern, als ausverkauft markieren, in den Papierkorb verschieben
- [ ] Verkäufer hat **keinen** Zugriff auf Inhalte, Kategorien-Verwaltung, Plugins, Theme, Einstellungen, Benutzer
- [ ] (optional) Verkäufer kann nichts endgültig löschen
- [ ] Redakteur bleibt unverändert funktionsfähig
- [ ] Version auf `0.6.0`, Code gepusht, Deploy Modus B, Doku aktualisiert
