# Detaillierte Aufgabe: REST-API gibt Benutzernamen preis

## Ziel

Die WordPress-REST-API des Live-Servers `217.160.74.128` liefert über `GET /wp-json/wp/v2/users` den Benutzernamen des ersten Benutzers (`barmbini`, ID 1) an **nicht angemeldete** Besucher aus. Das erleichtert gezielte Brute-Force-Angriffe, weil der Benutzername als Angriffsfläche bekannt wird.

Ziel dieser Aufgabe ist es, die Benutzer-Endpoints der REST-API für nicht berechtigte Personen zu sperren – ausschließlich über das Projekt-Plugin `barmbini-core`, reversibel und ohne Server-Skripting.

## Quellenbasis

- Server-Analyse vom 2026-08-11 (Befund: `/wp-json/wp/v2/users` → `barmbini`, ID 1, HTTP 200)
- `Barmbini_Technisches_Konzept_v2.5.md` – §10 DSGVO & Rechtliches, §2 Technische Basis
- `Barmbini_Plugin_Architektur_barmbini-core.md` – Modulzuschnitt, spätere Erweiterung
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` – Einbauorte (Business-Logik ins Plugin)
- `.github/skills/deployment-safety-check/SKILL.md` – Deployment-Regeln

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- Neue Fachlogik gehört in das Plugin `barmbini-core`, nicht in Theme oder Server-Skripte.
- Die Änderung muss **reversibel** sein (einfach zu deaktivieren).
- Die REST-API selbst bleibt grundsätzlich aktiv (Yoast, Gutenberg u. a. nutzen sie) – nur die **Benutzer-Endpoints** werden für Unberechtigte gesperrt.
- Der öffentliche Seitenbetrieb (Frontend, Gutenberg-Editor für Redakteure) darf nicht beeinträchtigt werden.
- Eingeloggte Administratoren müssen weiterhin vollen REST-Zugriff haben.

## Verifizierter Ist-Stand (2026-08-11)

- `GET http://217.160.74.128/wp-json/wp/v2/users` → HTTP 200 mit `[{"id":1,"slug":"barmbini","name":"barmbini",...}]`
- Auch Einzelabfragen `/wp-json/wp/v2/users/1` sind prinzipiell möglich.
- Autor-Zuordnungen auf öffentlichen Beiträgen (Anzeigename) bleiben davon unberührt – das ist normales WordPress-Verhalten und gehört nicht zu dieser Aufgabe.

## Aufgabe

### 1. Neue Klasse im Plugin anlegen

**Neue Datei:** `wp-content/plugins/barmbini-core/includes/security/class-rest-api-hardening.php`

**Name der Klasse:** `Barmbini_Core_Rest_Api_Hardening`

**Struktur (orientiert am Muster der bestehenden Module):**

- Öffentliche Methode `register()` → hängt den Filter `rest_endpoints` an.
- Öffentliche Methode `disable_user_endpoints_for_public( $endpoints )` → entfernt die Benutzer-Routen für Unberechtigte.
- Geschützte Methode `is_allowed()` → prüft, ob der Aufrufer die Berechtigung `list_users` besitzt.

**Kernlogik:**

```php
public function disable_user_endpoints_for_public( $endpoints ) {
    if ( $this->is_allowed() ) {
        return $endpoints;
    }

    // Benutzer-Routen nur für Berechtigte offen lassen.
    unset( $endpoints['/wp/v2/users'] );
    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    unset( $endpoints['/wp/v2/users/me'] );

    return $endpoints;
}

protected function is_allowed() {
    return is_user_logged_in() && current_user_can( 'list_users' );
}
```

**Hinweise:**

- `rest_endpoints` wird serverseitig gefiltert; die Routen verschwinden für Unberechtigte vollständig aus dem Routing.
- `current_user_can( 'list_users' )` stellt sicher, dass Administratoren und Redakteure mit passender Rolle den Zugriff behalten.
- Alternativ (falls der Filter `rest_endpoints` auf dem Ziel-WordPress nicht greifen sollte) lässt sich dieselbe Logik über `rest_authentication_errors` oder `rest_pre_dispatch` umsetzen – im Projekt entscheiden und im Code dokumentieren.

### 2. Registrierung im Plugin-Bootstrap

1. In `barmbini-core.php` die neue Datei per `require_once` laden (analog zu den anderen Klassen).
2. In `includes/class-plugin.php` eine geschützte Methode `register_security_module()` anlegen.
3. Darin die Klasse instanziieren und `register()` aufrufen.
4. Die neue Methode im Konstruktor von `Barmbini_Core_Plugin` aufrufen.

**Registrierung in `register()`:**

```php
public function register() {
    add_filter( 'rest_endpoints', array( $this, 'disable_user_endpoints_for_public' ) );
}
```

### 3. Lokale Validierung

1. Plugin in der lokalen Installation (`D:\Local Sites\barmbini\app\public`) aktivieren.
2. Nicht angemeldet: `curl -k https://barmbini.local/wp-json/wp/v2/users` aufrufen → erwartet: 401 oder leeres Ergebnis, **kein** Benutzername.
3. Als Administrator eingeloggt: Aufruf liefert weiterhin die Benutzerliste.
4. Frontend-Regression: Startseite, Sortiment, Gutenberg-Editor (Redakteur) normal nutzbar.

### 4. Abnahme

- Nicht angemeldeter Zugriff auf `/wp-json/wp/v2/users` liefert **keine** Benutzernamen.
- Angemeldete Administratoren behalten vollen Zugriff.
- Kein Rohtext, keine PHP-Fehler, keine Warnungen.

## Abnahmekriterien

- [ ] `GET /wp-json/wp/v2/users` (nicht angemeldet) → keine Benutzerdaten
- [ ] `GET /wp-json/wp/v2/users/1` (nicht angemeldet) → keine Benutzerdaten
- [ ] Angemeldeter Admin kann Benutzer weiterhin über die REST-API abfragen
- [ ] Gutenberg-Editor und Yoast funktionieren unverändert
- [ ] Der Code liegt im Plugin `barmbini-core`, nicht im Theme

## Deployment

- **Modus B** (nur Code, kein SQL-Import) – Live-Daten bleiben erhalten.
- Via `deploy.ps1` (Standard, ohne `-Full`) auf den Server `217.160.74.128`.
- Sanity-Check nach dem Deploy: `curl -s http://217.160.74.128/wp-json/wp/v2/users` → darf keinen Benutzernamen mehr enthalten.
- WP Fastest Cache nach dem Deploy leeren (erfolgt im Standard-Deploy-Prozess).

## Rollback

- Deaktivierung des Filters ist ausreichend: Die Änderung ist ausschließlich Code-basiert.
- Einfachste Rücknahme: `register_security_module()`-Aufruf im Konstruktor von `class-plugin.php` auskommentieren und erneut deployen (Modus B).

## Risiken und offene Punkte

- **Offene Frage:** Sollen auch `/wp/v2/users/(?P<id>...)/posts` oder Kommentar-Endpoints mit Autor-Infos gesperrt werden? (Empfehlung: vorerst nicht – das ist normales WordPress-Verhalten und würde Suchmaschinen-Kontext ändern.)
- Der Benutzername kann weiterhin über Autor-Archive (`/?author=1` → Redirect) teilweise ermittelt werden. Eine vollständige Abdichtung wäre nur server-seitig (nginx) möglich und ist **nicht** Teil dieser Aufgabe – ggf. als Folge-Aufgabe dokumentieren.

## Dokumentation

- Nach Umsetzung `Barmbini_Plugin_Architektur_barmbini-core.md` um ein Security-Modul ergänzen.
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` Ist-Stand aktualisieren.
- Vor Erstellung des Umsetzungs-Tickets: Doku-Stand prüfen und aktualisieren.
