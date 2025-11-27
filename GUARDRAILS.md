# 🛡️ PUNKTET BBS - GUARDRAILS
## ABSOLUTTE REGLER - INGEN UNNTAK

**SIST OPPDATERT:** 2025-11-27
**VERSJON:** 1.0

---

## TECHSTACK - LÅST

| Komponent | Versjon/Valg | ALDRI bruk |
|-----------|--------------|------------|
| Backend | Laravel 10.x | Laravel 11, Lumen, Symfony |
| Database | MariaDB | MySQL, PostgreSQL, SQLite |
| Frontend | Vanilla JavaScript | React, Vue, Angular, jQuery |
| PHP | 8.1+ | PHP 7.x eller eldre |
| Server | Apache + mod_rewrite | nginx konfig |
| Path | /var/www/vhosts/punktet.no/httpdocs | Aldri endre |
| Document Root | /var/www/vhosts/punktet.no/httpdocs/public | Aldri endre |

---

## DATABASE - LÅST

```
Host:     localhost
Database: admin_punkteT
Bruker:   admin_punkteT
Passord:  Klokken!12!?!
```

**ALDRI** endre credentials uten eksplisitt bruker-godkjenning.

---

## SUPERBRUKER - LÅST

```
E-post:   terje@smartesider.no
Passord:  KlokkenTerje2025
Rolle:    SYSOP (høyeste nivå)
```

---

## FORBUDTE HANDLINGER

| # | Regel | Konsekvens ved brudd |
|---|-------|---------------------|
| 1 | ❌ ALDRI installer npm/composer pakker uten godkjenning | Stopp, spør bruker |
| 2 | ❌ ALDRI endre mappestruktur fra Laravel standard | Reverser umiddelbart |
| 3 | ❌ ALDRI bruk inline credentials i kode (kun .env) | Sikkerhetsbrist |
| 4 | ❌ ALDRI slett filer uten eksplisitt godkjenning | Stopp, spør bruker |
| 5 | ❌ ALDRI anta at noe fungerer - TEST ALLTID | Dokumenter i feil.json |
| 6 | ❌ ALDRI hopp over migrasjoner | Database-inkonsistens |
| 7 | ❌ ALDRI hardkod språkstrenger (bruk lang-filer) | Bryter i18n |
| 8 | ❌ ALDRI ignorer feil - logg til AI-learned/feil.json | Gjentar feil |
| 9 | ❌ ALDRI gjenbruk kode dokumentert i feil.json | Gjentar feil |
| 10 | ❌ ALDRI avvik fra godkjent funksjonsliste | Scope creep |
| 11 | ❌ ALDRI lag placeholder/mock/demo kode | Logg til uferdig.json |
| 12 | ❌ ALDRI lag statisk testdata (unntatt brukere) | Falsk funksjonalitet |
| 13 | ❌ ALDRI merk noe som ferdig før det er FULLSTENDIG | Falsk fremgang |

---

## PÅBUDTE HANDLINGER

| # | Regel | Når |
|---|-------|-----|
| 1 | ✅ LES GUARDRAILS.md | Ved start av HVER fase |
| 2 | ✅ LES AI-learned/*.json | Før HVER implementering |
| 3 | ✅ OPPDATER AI-learned | Etter HVER fase |
| 4 | ✅ TEST hver komponent | Før neste steg |
| 5 | ✅ BEKREFT med bruker | Ved ENHVER usikkerhet |
| 6 | ✅ BRUK Laravel conventions | Alltid (PSR-4, etc.) |
| 7 | ✅ SKRIV NO og EN språkfiler parallelt | Ved all tekst |
| 8 | ✅ DOKUMENTER alle API-endepunkter | Ved opprettelse |
| 9 | ✅ VALIDER input frontend OG backend | Alltid |
| 10 | ✅ LOG alle kritiske operasjoner | Alltid |
| 11 | ✅ LOGG uferdige ting til uferdig.json | Ved enhver mangel |

---

## VED FEIL - PROTOKOLL

```
1. STOPP umiddelbart
2. Dokumenter feilen i AI-learned/feil.json med:
   - Tidspunkt
   - Hva som ble forsøkt
   - Feilmelding
   - Hvorfor det feilet
3. ALDRI prøv samme løsning igjen
4. Søk alternativ i AI-learned/fungerer.json
5. Hvis ingen alternativ: Spør bruker
6. Vent på godkjenning før fortsettelse
```

---

## FILSTRUKTUR - LÅST

```
/var/www/vhosts/punktet.no/httpdocs/
├── AI-learned/              # AI læringsfiler (PÅKREVD)
│   ├── README.md
│   ├── fungerer.json
│   ├── feil.json
│   ├── usikkert.json
│   ├── godekilder.json
│   ├── beslutninger.json
│   ├── avhengigheter.json
│   ├── uferdig.json         # Placeholder/mock/demo = HER
│   └── plan.json
├── GUARDRAILS.md            # Dette dokumentet
├── app/                     # Laravel app
│   ├── Console/
│   ├── Exceptions/
│   ├── Http/
│   ├── Models/
│   ├── Providers/
│   └── Services/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/                  # Document root
│   └── index.php
├── resources/
│   ├── lang/
│   │   ├── en/
│   │   └── no/
│   ├── views/
│   └── js/
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── tests/
├── .env
├── composer.json
└── artisan
```

---

## GODKJENT FUNKSJONSLISTE

**KUN disse funksjonene skal implementeres:**

### Grunnleggende (1-35)
1-8, 9-15, 16-23, 24-30, 31-35

### Spill & Underholdning
36, 37, 38, 39, 40

### Statistikk & Admin  
41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 57, 59, 60, 61, 62

### Integrasjoner & Sikkerhet
65, 66, 67, 68, 69

### AI Bot System
71, 72, 73, 74, 75, 76, 77, 78

### Klassisk BBS
79, 80, 81, 82, 83, 84, 85, 87, 88, 89, 90, 91, 92

### Kommunikasjon
93, 95, 96

### Door Games
100, 101, 102, 103, 104, 105, 106, 107

### Statistikk
108, 109, 110, 112, 113

### Nostalgi
116, 118, 119

### Sosiale
121, 123, 124, 125

### Sikkerhet
130

### Teknisk
132, 134 (inkl. Sci-Fi Speed)

**TOTAL: 111 funksjoner**

---

## SPRÅK - PÅKREVD

- Default: English (en)
- Valgbar: Norsk (no)
- ALLE brukervendte strenger SKAL være i lang-filer
- ALDRI hardkod tekst i views eller controllers

---

## CHECKSUM

Ved start av hver fase, bekreft:

- [ ] Har jeg lest GUARDRAILS.md?
- [ ] Har jeg lest AI-learned/feil.json?
- [ ] Har jeg lest AI-learned/fungerer.json?
- [ ] Har jeg lest AI-learned/plan.json?
- [ ] Vet jeg hvilken fase jeg er i?
- [ ] Er forrige fase FULLSTENDIG (ikke placeholder)?

---

**DETTE DOKUMENTET ER UFRAVIKELIG.**
