# Detaillierte Aufgabe: Aktionen-Benachrichtigung beim Startdatum (Variante A)

## Ziel

Benutzer, die im Konto „Aktionen abonnieren" aktiviert haben, sollen eine Benachrichtigung erhalten, **sobald das Startdatum einer Aktion erreicht ist** — nicht bereits beim Veröffentlichen der Aktion.

Redakteure können eine Aktion damit vorab anlegen und veröffentlichen, ohne dass sofort E-Mails versendet werden. Der Versand erfolgt automatisch am Startdatum.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Plugin_Architektur_barmbini-core.md` — Notifications-Modul, Event-Collector, Digest-Scheduler, Aktions-Trigger
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand, Einbauorte
- `Docs/Barmbini_Rechtliche_Seiten.md` — Datenschutzerklärung §6 (Benachrichtigungen)
- dem bestehenden Notifications-Modul `wp-content/plugins/barmbini-core/includes/notifications/`
- dem CPT `barmbini_aktion` (`class-promotion-post-type.php`, Meta-Keys `_barmbini_promotion_start_date` / `_barmbini_promotion_end_date`)
- dem bestehenden Abo-System (Abo-Typ `aktion`, `barmbini_actions_enabled` / `barmbini_actions_frequency`)

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog ohne Checkout.
- Neue Fachlogik gehört in `barmbini-core`, nicht ins Kadence-Theme.
- Die Benachrichtigung soll **einmalig** pro Aktion versendet werden (Duplikat-Schutz).
- Das Verhalten soll **reversibel** bleiben: Der bisherige Veröffentlichungs-Trigger darf nicht einfach gelöscht, sondern muss sauber ersetzt bzw. umgestellt werden (ideal: Code bleibt, aber deaktiviert; oder Ersatz über Cron).
- WP-Cron kann als Ausführungsmechanismus dienen (auf dem Server ist `DISABLE_WP_CRON` nicht gesetzt); bei sehr geringem Traffic kann ein externer Cron-Aufruf nötig sein.

## Aktueller Ist-Stand (2026-08-10)

- Abo-Typ `aktion` existiert: usermeta `barmbini_actions_enabled` / `barmbini_actions_frequency`, Formular-Section „Aktionen abonnieren", `has_any_subscription()` berücksichtigt Aktionen.
- Der **Event-Collector** (`class-event-collector.php`, Methode `handle_transition_post_status`) sendet aktuell bei **Veröffentlichung** einer `barmbini_aktion` (`transition_post_status` → `publish`) sofort an alle Aktionen-Abonnenten (Event-Typ `aktion`, Betreff „Neue Aktion bei Barmbini").
- Die **Delivery-Logik** (`class-delivery-service.php`) ist fertig für `aktion`: `resolve_frequency()` → `actions_frequency`, `build_subject()` → „Neue Aktion bei Barmbini". Queue- und Digest-Versand funktionieren generisch.
- Es existiert ein gutes **Referenzmuster** für geplante Ereignisse: `handle_scheduled_sales()` im Event-Collector (Cron-gesteuert über `woocommerce_scheduled_sales`) — genau dieses Muster soll für Aktionen übernommen werden.

## Gewünschtes Verhalten (Variante A)

| Situation | Verhalten |
|---|---|
| Aktion wird veröffentlicht (Startdatum in der Zukunft) | **Keine** Sofort-Benachrichtigung |
| Aktion läuft, Startdatum ist erreicht/heute | ✅ Benachrichtigung an alle Aktionen-Abonnenten (einmalig, gemäß Frequenz `sofort`/`täglich`/`wöchentlich`) |
| Aktion ist noch Entwurf | Keine Benachrichtigung |
| Gleiche Aktion an mehreren Tagen | Nur **ein** Mal benachrichtigen (Meta-Flag) |
| Nachträgliches Startdatum (Aktion wird zurückdatiert) | Kein rückwirkender Versand (nur Startdatum = heute, nicht kleiner als heute) |

## Technische Umsetzung

### 1. Veröffentlichungs-Trigger umstellen

In `class-event-collector.php`, Methode `handle_transition_post_status`:

- Den bestehenden Block für `barmbini_aktion` **entfernen** (oder hinter eine Konstante/Filter schalten, z. B. `apply_filters('barmbini_core_notify_on_action_publish', false)`), sodass das Veröffentlichen einer Aktion **keine** Sofort-Mail mehr auslöst.
- Die Event-Erzeugung (Intro, Titel, Excerpt, URL) soll dabei in eine **wiederverwendbare Methode** ausgelagert werden, z. B. `build_action_event( $post_id )`.

### 2. Cron-Job für Aktionen-Start

Analog zum Digest-Scheduler (`class-digest-scheduler.php`) und zu `handle_scheduled_sales()`:

- **Neue Methode** im Event-Collector: `handle_scheduled_action_starts()`
- **Cron-Ereignis**: `barmbini_core_action_start_notifier` (Intervall: `daily`, Zeitpunkt z. B. 08:00)
- **Registrierung** in `register_notifications_module()` (`class-plugin.php`):
  - `add_action( 'init', ... schedule_events )` bzw. `wp_next_scheduled()`-Prüfung
  - `add_action( 'barmbini_core_action_start_notifier', $event_collector, 'handle_scheduled_action_starts' )`
  - Cron-Ereignis beim Deaktivieren aufräumen (`class-deactivator.php`)

### 3. Abfrage der fälligen Aktionen

`handle_scheduled_action_starts()`:

1. Heutiges Datum bestimmen: `current_time('Y-m-d')`
2. Alle `barmbini_aktion` mit `post_status = publish` laden, die **noch nicht benachrichtigt** wurden:
   - Meta-Query: `_barmbini_action_start_notified` `NOT EXISTS` (Duplikat-Schutz)
3. Für jede Aktion das Startdatum lesen (`_barmbini_promotion_start_date`)
4. Nur wenn `start_date === heute` → Benachrichtigung auslösen
   - (Bewusst **kein** `<` — keine rückwirkenden Mails für zurückdatierte Aktionen)
5. Nach erfolgreichem Versand `update_post_meta( $post_id, '_barmbini_action_start_notified', '1' )` setzen
   - Wichtig: Das Flag **erst nach** dem Versandversuch setzen, damit bei `wp_mail()`-Fehlern (Versandlog `failed`) ein zweiter Lauf erneut versuchen kann. Alternativ: Flag beim ersten Versand setzen und Fehler über das Versandlog tracken — im Projekt entscheiden und dokumentieren.

### 4. Versand

Pro fälliger Aktion:

```php
$event = $this->build_action_event( $post_id );
foreach ( $this->get_enabled_users( Barmbini_Core_Subscription_Settings::ACTIONS_ENABLED ) as $user_id ) {
    $this->delivery_service->deliver( $user_id, 'aktion', $event );
}
```

Der `deliver()`-Aufruf übernimmt die Frequenz:
- `sofort` → sofortige E-Mail
- `täglich`/`wöchentlich` → Queue-Eintrag für den Digest

### 5. Wiederverwendbare Event-Methode

```php
protected function build_action_event( $post_id ) {
    $post = get_post( $post_id );
    return array(
        'event_type'  => 'aktion',
        'event_key'   => 'aktion-' . $post->ID,
        'object_id'   => $post->ID,
        'object_type' => 'barmbini_aktion',
        'intro'       => 'Es gibt eine neue Aktion bei Barmbini.',
        'title'       => get_the_title( $post ),
        'excerpt'     => has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
        'url'         => get_permalink( $post ),
    );
}
```

### 6. Testdaten-Management

Für den lokalen Test werden Aktionen mit unterschiedlichen Startdaten benötigt:
- Eine Aktion mit Startdatum **heute** → soll benachrichtigt werden
- Eine Aktion mit Startdatum **in der Zukunft** → soll NICHT benachrichtigt werden
- Eine Aktion mit Startdatum **gestern/vergangen** → soll NICHT benachrichtigt werden (kein rückwirkender Versand)
- Test-Aktionen nach dem Test wieder entfernen oder auf den Ausgangszustand zurücksetzen

## Abnahmekriterien

- [ ] Veröffentlichen einer Aktion mit zukünftigem Startdatum löst **keine** Sofort-Mail aus
- [ ] Der Cron-Lauf benachrichtigt Aktionen mit Startdatum **heute**
- [ ] Aktionen mit zukünftigem oder vergangenem Startdatum werden **nicht** benachrichtigt
- [ ] Pro Aktion wird **maximal einmal** benachrichtigt (Meta-Flag greift)
- [ ] Bei `sofort`-Frequenz kommt die E-Mail direkt (Betreff „Neue Aktion bei Barmbini")
- [ ] Bei `täglich`/`wöchentlich` landet das Ereignis in der Queue und wird im Digest zugestellt
- [ ] Der Cron-Lauf ist ohne `wp_mail`-Fehler ausführbar (per `wp cron event run` oder direktem Methodenaufruf testbar)
- [ ] Keine Regression: andere Abo-Typen (Neuigkeiten) und bestehende Trigger funktionieren weiterhin
- [ ] Die Doku (Architektur, Vorbereitung, Rechtliche_Seiten §6) ist aktualisiert

## Testhinweise

- Lokal per PHP testen: Aktionen anlegen (heute/zukunft/vergangen), `handle_scheduled_action_starts()` direkt aufrufen oder `do_action('barmbini_core_action_start_notifier')` auslösen, `pre_wp_mail`-Filter zum Abfangen der Mails verwenden.
- Cron-Intervall: `daily` mit festem Zeitpunkt (z. B. 08:00) via `wp_schedule_event( time(), 'daily', ... )`.
- Duplikat-Schutz verifizieren: Zweiter Lauf am selben Tag darf keine zweite Mail erzeugen.

## Rechtliche Hinweise

- Die Datenschutzerklärung (§6) beschreibt bereits Benachrichtigungen zu Neuigkeiten und Aktionen — kein neuer Verarbeitungsschritt, aber der Zeitpunkt des Versands (Startdatum) kann ergänzt werden: „Benachrichtigungen zu neuen Aktionen werden versendet, sobald das Startdatum der Aktion erreicht ist."
- Einwilligung und Abmeldung bleiben wie im bestehenden System (Abmeldelink, `barmbini_actions_enabled` deaktivieren).
- Die Abmeldung muss weiterhin Queue-Einträge für künftige Digests sperren (bestehende Logik `cancel_stale_for_user` / `cancel_pending_for_user`).

## Abgrenzung

- **NICHT Teil dieser Aufgabe**: Formular-Umbau oder neue Abo-Typen (Abo-Optionen sind auf Neuigkeiten + Aktionen reduziert)
- **NICHT Teil dieser Aufgabe**: Rabatt- oder Produktkategorien-Benachrichtigungen (ausgeblendet, Logik reversibel)
- **NICHT Teil dieser Aufgabe**: E-Mail-Design/Templates (Klartext wie bisher)

## Definition of Done

1. Der Veröffentlichungs-Trigger für `barmbini_aktion` ist deaktiviert; der neue Cron-basierte Start-Versand ist umgesetzt und lokal verifiziert.
2. `class-event-collector.php`, `class-plugin.php` und ggf. `class-deactivator.php` sind angepasst; Duplikat-Schutz-Meta-Flag ist dokumentiert.
3. Die Plugin-Architektur-Doku (`Barmbini_Plugin_Architektur_barmbini-core.md`) beschreibt den Aktions-Start-Trigger und den Cron.
4. Der verifizierte Ist-Stand (`Barmbini_Vorbereitung_Features_und_Bugfixes.md`) ist aktualisiert.
5. Die Datenschutzerklärung (§6) erwähnt den Versandzeitpunkt (Startdatum).
6. Alle Änderungen sind auf GitHub (`origin/main`) gepusht.
7. Für Live gilt Modus B (kein SQL-Import); der Cron plant sich beim nächsten Seitenaufruf selbst (`wp_next_scheduled()`-Prüfung auf `init`).
