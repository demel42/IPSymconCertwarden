[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

[CertWarden](https://www.certwarden.com) ist Server, das ein ACME-basiertes Zertifikathandling mit einem Benutzerinterface verbindet. Der Server läuft vorzugsweise in einem Docker-Container.
Mittels ACME-DNS kann ein gültiges Zereifikat von LetsEncrypt geholt werden ohne das ein HTTP-Zugriff erforderloch ist. Die GUI von Vertwarden ist recht verständlich, eine gewisse Vorstellung von Zertifikaten sollte vorhanden sein, aber nicht von LetsEncrypt, ACME o.ä.. Wildcard-Zertifikate (_*.\<meine Domain>_)
Zertifikate werden automatisch frühzeitig erneuert (wohl 1/3 der Laufzeit vor Ablauf der Gültigkeit)

Die von Certwaren erstellten Zertifikate können von anderen Server auch mittels einer einfachen HTTP-API geholt und integriert werden; Beispiel-Scripte sind für einige Systeme vorhanden.

Das Modul holt ein Zertifikat von dem lokalen CertWarden-Server und für das in einem ausgewählten Symcon-Webserver ein. Der Neustart des Symcon muss manuell erfolgen.
Da aber die Erneuerung der Zertifikate im Certwarden frühzeitig erfolgt passiert das sowieso oder kann gut eingeplant werden.

Nachdem ein Zertifikat erneuert wurde, eird ein ScriptAaufgerufen, in der eine Benachrichtigung erfolgen kann.


## 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- ein CertWarden-Server mit eine einstrechenden Zertifikat

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Certwarden* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconCertwarden.git` installiert werden.

### b. Einrichtung in IPS

## 4. Funktionsreferenz

es gibt keine Funktionen

## 5. Konfiguration

### Certwarden

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Server                    | string   |              | Hostname/IP des Certwarden-Servers |
| Port                      | integer  | 4055         | Portnummer von Certwarden |
|                           |          |              | |
| Zertifikat                |          |              | |
| - Name                    | string   |              | Name des Zertifikats im Certwarden |
| - API-Key                 | string   |              | API-Key des Zertifikats |
| Privater Schlüssel        |          |              | |
| - Name                    | string   |              | Name des privaten Schlüssels des Zertifikats im Certwarden |
| - API-Key                 | string   |              | API-Key des privaten Schlüssels des Zertifikat |
|                           |          |              | |
| Webserver-Instanz         | integer  |              | ID des betroffenen Webservers |
| Skript                    | string   |              | Skript, das nach jedem Durchlauf ausgeführt wird \[_1_\]|
| Uhrzeit                   |          | 00:00:00     | Uhrzeit der Aktualisierung |

\[_1_\]:<br>


Dem Script werden zusätzliche Variablen in der üblichen Struktur *_IPS* übergeben.


| Variable                  | Typ      | Beschreibung |
| :------------------------ | :------  | :----------- |
| webServer_instID          | integer  | Instanz-ID des betroffenen Webservers |
| webServer_status          | integer  | Status des betroffenen Webservers |
| webServer_statusText      | string   | Status des betroffenen Webservers als Text |
| instanceID                | integer  | Instanz-ID der Certwarden-Instanz |
| validFrom                 | integer  | Zertifikat-Gültigkeit ab |
| validTo                   | integer  | Zertifikat-Gültigkeit bis |
| certificateChanged        | boolean  | Zertifikat wurde geändert, sonѕt ist das nur ein Durchlauf ohne Änderung |



| Bezeichnung               | Beschreibung |
| :------------------------ | :----------- |
| Aktualisieren             | Zertifikat holen und ggfs. aktualisieren |

#### Væriablen

| Bezeichnung               | Beschreibung |
| :------------------------ | :----------- |
| Zertifikat gültig bis     | Ende der Gültigkeit des Zertifikats |
| Webserver-Status          | Instanz-Status des Weⅺservers. Hieran kann man gut erkennen, ob der Webserver einen Neustart erfordert (Wert *201*) |


### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Integer<br>
Certwarden.WebserverStatus


## 6. Anhang

### GUIDs
- Modul: `{6A72438D-330C-4996-CCE2-E75BC9FFE8DA}`
- Instanzen:
  - Certwarden: `{B54A8E65-922F-86DF-62F1-396B63B44F43}`
- Nachrichten:

### Quellen

## 7. Versions-Historie

- 1.0 @ 05.11.2025 17:34
  - Initiale Version
