# Detaillierte Aufgabe: Google-Maps-Block auf der Startseite – Bewertung, Entscheidung und Optionen

## Status & Entscheidung (2026-08-11)

**Entscheidung:** Der Kadence-Google-Maps-Block auf der Startseite **bleibt vorerst erhalten** (keine sofortige Umstellung). Eine spätere Ersetzung durch eine andere Komponente (z. B. statische Karte) bleibt möglich und wird in diesem Dokument vorbereitet.

**Wichtigste Erkenntnis (Korrektur zur ersten Analyse):** Der sichtbare API-Key `AIzaSyBAM2o7...` ist **KEIN eigener Key** des Projekts, sondern der **eingebaute Standard-Key des Kadence-Blocks-Plugins**. Er wird automatisch eingefügt, wenn in den Kadence-Einstellungen kein eigener Key hinterlegt ist. **Es gibt daher kein eigenes Google-Cloud-Konto, keinen eigenen Key und kein Abrechnungs-/Rotations-Risiko für das Projekt.**

## Ziel

Dokumentiert den Ist-Stand des Google-Maps-Blocks auf der Startseite, die korrigierte Bewertung des API-Keys sowie die **Optionen**, falls der Block künftig ersetzt oder mit eigenem Key betrieben werden soll.

## Quellenbasis

- Server-Analyse vom 2026-08-11 (zunächst fälschlich als 🔴 „öffentlicher API-Key" eingestuft)
- **Korrektur (verifiziert am 2026-08-11):** Key-Quelle im Kadence-Blocks-Quellcode nachgewiesen (siehe unten)
- `Barmbini_Technisches_Konzept_v2.5.md` – §3 Architektur-Grundsätze („Keine eingebetteten Google Maps (nur statische Karte)"), §7.5
- `Barmbini_Aufgabe_Sicherheit_HTTP_Header.md` – CSP-Entscheidung (enge CSP erst nach Entfernen des Google-Embeds möglich)
- Die Kontaktseite `/kontakt/` als korrektes Referenz-Muster (statische `map.png` + „In Google Maps öffnen"-Link)

## Verifizierter Ist-Stand (2026-08-11)

**Live-Startseite** – Block „Hier finden Sie uns":

- Google-Maps-iframe im Kadence-Block:
  ```
  <div class="kb-google-maps-container kb-google-maps-container13_b84d5f-6e wp-block-kadence-googlemaps">
    <iframe width="100%" height="100%" style="border:0" loading="lazy"
      src="https://www.google.com/maps/embed/v1/place?key=AIzaSyBAM2o7PiQqwk15LC1XRH2e_KJ-jUa7KYk&zoom=13&maptype=roadmap&q=Alter+Teichweg+11+Hamburg"
      title="Google map of Alter Teichweg 11 Hamburg"></iframe>
  </div>
  ```
- Block-ID: `13_b84d5f-6e` (Teil des Startseiten-Posts, Post-ID 13).

## Korrigierte Bewertung des API-Keys (verifiziert)

**Quelle des Keys – nachgewiesen im Plugin-Quellcode:**

`includes/blocks/class-kadence-blocks-googlemaps-block.php` (Zeile 229–235):
```php
// Replace API key with default or users set key.
$user_google_maps_key = get_option( 'kadence_blocks_google_maps_api', '' );
if ( empty( $user_google_maps_key ) ) {
    // Kein eigener Key gesetzt → Kadence-Standard-Key verwenden
    $content = str_replace( 'KADENCE_GOOGLE_MAPS_KEY', 'AIzaSyBAM2o7PiQqwk15LC1XRH2e_KJ-jUa7KYk', $content );
} else {
    $content = str_replace( 'KADENCE_GOOGLE_MAPS_KEY', $user_google_maps_key, $content );
}
```

**Verifikationsergebnisse:**
- ✅ Key **nicht** in den Block-Attributen der Startseite (post_content)
- ✅ Key **nicht** in den `wp_options` des Projekts
- ✅ Option `kadence_blocks_google_maps_api` ist **nicht gesetzt** (leer) → Kadence-Fallback greift
- ✅ Key im Plugin-Quellcode hinterlegt (`dist/blocks-googlemaps.js` + `class-kadence-blocks-googlemaps-block.php`)

**Folgerung (Korrektur der ursprünglichen 🔴-Einstufung):**

| Frühere Annahme | Korrektur |
|---|---|
| 🔴 „Eigener API-Key öffentlich im HTML" | 🟢 **Kein eigener Key** – es ist Kadences geteilter Standard-Key (in vielen Installationen). **Kein Abrechnungs-/Rotations-Risiko für das Projekt.** |
| 🔴 „API-Key in Google Cloud rotieren" | 🟢 **Nicht erforderlich** – es existiert kein eigenes Google-Cloud-Projekt/-Key. |

**Was real bleibt (gültige Punkte):**
- 🟡 **Architektur-Konflikt:** Der Embed widerspricht der Projektregel „nur statische Karte" (Konzept v2.5 §3). Rein konzeptionell, nicht sicherheitskritisch.
- 🟡 **Datenschutz:** Der iframe überträgt Besucherdaten an Google; die Datenschutzerklärung behauptet aktuell „lädt keine externen Dienste", was mit diesem Block **unzutreffend** ist. Bei Beibehaltung des Blocks sollte der Text angepasst werden.
- 🟡 **Stabilitätsrisiko:** Kadences geteilter Standard-Key kann von Google rate-limitiert oder gesperrt werden → die Karte könnte ausfallen. Kein Sicherheits-, aber ein Verfügbarkeitsrisiko.
- 🟠 **CSP-Blockade:** Solange der Embed existiert, ist eine enge Content-Security-Policy nicht möglich (siehe `Barmbini_Aufgabe_Sicherheit_HTTP_Header.md`, Option A).

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- **Entscheidung:** Block bleibt vorerst bestehen; ein eigener Key ist nur nötig, wenn der Block dauerhaft behalten und stabil betrieben werden soll.
- Es gilt **Modus B**: Live-Daten bleiben erhalten, kein SQL-Vollimport.
- Keine sofortige Inhaltsänderung erforderlich.

## Optionen (je nach künftiger Entscheidung)

### Option 1: Block behalten (aktueller Zustand)

- **Nichts tun** – Block bleibt mit Kadence-Standard-Key.
- **Empfehlung (nur falls dauerhaft behalten):** In den Kadence-Blocks-Einstellungen einen **eigenen Google-Maps-API-Key** hinterlegen (Option `kadence_blocks_google_maps_api`, Admin → Kadence Blocks → Einstellungen → Google Maps API). Damit ist die Karte unabhängig vom geteilten Kadence-Key.
- **Pflicht bei Beibehaltung:** Datenschutzerklärung anpassen (Google-Maps-Embed + Datenübertragung an Google dokumentieren, Rechtsgrundlage Art. 6 Abs. 1 lit. a DSGVO bzw. Einwilligungslösung prüfen).

### Option 2: Block durch statische Karte ersetzen (später möglich)

Falls die Ersetzung später gewünscht ist – Schritte (wie ursprünglich geplant, ohne Key-Rotation):

1. Lokal (Quelle): Startseite im Gutenberg-Editor öffnen, Google-Maps-Block löschen, **Bild (statische Karte)** einfügen (möglichst `map.png` wie auf `/kontakt/` oder neuer Ausschnitt), dazu Link-Button **„In Google Maps öffnen"** → `https://www.google.com/maps/search/Alter+Teichweg+11,+22081+Hamburg`.
2. Live nachziehen (Modus B): identische Änderung im Live-Editor, veröffentlichen, WP Fastest Cache leeren.
3. Verifizieren: `curl -s http://217.160.74.128/ | grep -c "google.com/maps/embed"` → `0`.
4. Danach: Datenschutzerklärung bereinigen („keine externen Dienste" stimmt wieder) und **enge CSP** einführen (Folge-Aufgabe).

### Option 3: Andere Komponente

- Eigene Karten-Komponente ohne Google (z. B. OpenStreetMap-Embed oder reines Bild) – **nur wenn sie die Architekturregeln einhält** (kein externes Tracking) und DSGVO-konform ist. Vorab als Feature-Aufgabe planen.

## Abnahmekriterien (nur bei Umsetzung von Option 2)

- [ ] Startseite zeigt eine **statische Karte** statt Google-Maps-iframe
- [ ] Im HTML der Startseite ist **kein** `iframe` mit `google.com/maps/embed` mehr vorhanden
- [ ] Ein Link **„In Google Maps öffnen"** führt zur Karten-Suche
- [ ] Lokale Quelle (Post 13) ist identisch zur Live-Korrektur
- [ ] WP Fastest Cache live geleert

## Deployment

- **Inhaltsänderung** (Post 13 Startseite), kein Code-Deploy – **nur bei Option 2/3**.
- Lokale Quelle zuerst, Live-Nachzug manuell im Editor (Modus B).
- Kein `deploy.ps1`-Einsatz erforderlich.

## Rollback

- **Option 1 (behalten):** Kein Rollback nötig.
- **Option 2:** Falls die statische Karte nicht gefällt, im Editor den Google-Maps-Block wieder einfügen – problemlos möglich, da kein Key-Rotationsschritt mehr nötig ist.

## Risiken und offene Punkte

- **Offene Frage:** Soll der Block dauerhaft behalten werden? Falls ja → eigener Key + Datenschutzerklärung anpassen. Falls nein → Option 2/3 zu einem späteren Zeitpunkt.
- Der Google-Maps-Embed war im Kadence-Block `13_b84d5f-6e`; dieser Block-Container wird bei Ersetzung mit dem Block entfernt.
- **Nicht Teil dieser Aufgabe:** Verschlüsselte HTTPS-Umstellung (separate Aufgabe), übrige Server-Sicherheits-Header (separates Runbook), enge CSP (erst nach Entfernen des Embeds).

## Dokumentation

- **Entscheidung dokumentiert:** Block bleibt vorerst (Stand 2026-08-11).
- Falls eigener Key gesetzt wird: Key-Restriktion (HTTP-Referrer) in `Server_Aenderungsdokumentation_*.md` vermerken.
- Bei Beibehaltung: `Docs/Barmbini_Rechtliche_Seiten.md` (Datenschutzerklärung) um Google-Maps-Embed ergänzen.
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` Ist-Stand aktualisieren.
