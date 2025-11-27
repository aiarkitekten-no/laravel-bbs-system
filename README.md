# PUNKTET BBS

> *Nostalgi møter fremtiden*

PUNKTET er et moderne BBS-system (Bulletin Board System) som gjenskaper opplevelsen fra 80- og 90-tallets BBSer, bygget med moderne teknologi.

## ✨ Funksjoner

### 🔐 Autentisering & Brukere
- Registrering med handle/brukernavn
- Sanctum token-autentisering
- Brukernivåer (GUEST → SYSOP)
- Tidsbegrensning per nivå
- IP-sporing og sikkerhet

### 💬 Meldingsområder
- Kategori-baserte forum
- Tråd-visning
- Quoting av meldinger
- Søk i meldinger
- Uleste meldinger

### 📨 Private Meldinger
- Innboks/utboks
- CC til flere mottakere
- Lesebekreftelse
- Meldings-mapper

### 📖 Stories
- Dagens historie
- Rating-system
- Kommentarer
- Arkiv

### 💻 Oneliners
- BBS-klassiker!
- Siste 10 oneliners

### 📁 Filområde
- Kategoriserte filer
- Upload/download
- Ratio-system
- Søk i filer
- NFO/DIZ support

### 🎮 Door Games
- Klassiske BBS-spill
- High score lister
- Daglige spillbegrensninger

### 🎨 ANSI Art
- ANSI art galleri
- SAUCE-støtte
- Kategorier

### 📊 Avstemninger
- Opprett avstemninger
- Flervalg støtte
- Resultater

### 🌐 Sosiale Funksjoner
- Tidsbank
- Brukerklubber
- Graffiti Wall
- Bursdagsliste
- Awards

### ⚙️ Admin/SysOp
- Dashboard med statistikk
- Brukeradministrasjon
- Systemkonfigurasjon
- Caller log
- Vedlikeholdsmodus

## 🛠 Teknologi

- **Backend**: Laravel 10.x
- **Database**: MariaDB/MySQL
- **Autentisering**: Laravel Sanctum
- **Cache**: File/Redis
- **API**: RESTful JSON

## 📋 Systemkrav

- PHP 8.2+
- MariaDB 10.6+ / MySQL 8.0+
- Composer 2.x
- Apache/Nginx med mod_rewrite

## 🚀 Installasjon

```bash
# Klon repo
git clone https://github.com/punktet/bbs.git
cd bbs

# Installer avhengigheter
composer install

# Kopier miljøfil
cp .env.example .env

# Generer app-nøkkel
php artisan key:generate

# Konfigurer database i .env
# VIKTIG: Bruk DB_CONNECTION=mysql (ikke mariadb)

# Kjør migrasjoner
php artisan migrate

# Seed testdata (valgfritt)
php artisan db:seed

# Start utviklingsserver
php artisan serve
```

## 📚 Dokumentasjon

- [API Dokumentasjon](docs/API.md)
- [Deployment Guide](docs/DEPLOYMENT.md)

## 🔌 API Endepunkter

Se [API.md](docs/API.md) for komplett dokumentasjon.

### Hurtigoversikt

| Metode | Endepunkt | Beskrivelse |
|--------|-----------|-------------|
| POST | `/api/auth/login` | Logg inn |
| GET | `/api/whos-online` | Hvem er online |
| GET | `/api/categories` | Meldingsområder |
| GET | `/api/oneliners` | Oneliners |
| GET | `/api/stories/today` | Dagens historie |
| GET | `/api/health/ping` | Health check |

## 🔒 Sikkerhet

- Rate limiting per brukernivå
- Input sanitering
- CSRF-beskyttelse
- Security headers
- IP-logging
- Brute force beskyttelse

## 📊 Brukernivåer

| Nivå | Kode | Tid/dag | API calls/min |
|------|------|---------|---------------|
| Gjest | GUEST | 15 min | 30 |
| Ny | NEW | 30 min | 45 |
| Medlem | MEMBER | 60 min | 60 |
| Verifisert | VERIFIED | 90 min | 80 |
| Elite | ELITE | 180 min | 100 |
| CoSysOp | COSYSOP | Ubegrenset | 150 |
| SysOp | SYSOP | Ubegrenset | 200 |

## 🌍 Språk

- 🇳🇴 Norsk (standard)
- 🇬🇧 English

## 📜 Lisens

MIT License

## 👤 Forfatter

**PUNKTET Team**
- SysOp: Terje
- E-post: sysop@punktet.no

---

```
╔══════════════════════════════════════════════════════════╗
║                                                          ║
║     ██████╗ ██╗   ██╗███╗   ██╗██╗  ██╗████████╗        ║
║     ██╔══██╗██║   ██║████╗  ██║██║ ██╔╝╚══██╔══╝        ║
║     ██████╔╝██║   ██║██╔██╗ ██║█████╔╝    ██║           ║
║     ██╔═══╝ ██║   ██║██║╚██╗██║██╔═██╗    ██║           ║
║     ██║     ╚██████╔╝██║ ╚████║██║  ██╗   ██║           ║
║     ╚═╝      ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝   ╚═╝           ║
║                                                          ║
║          «NOSTALGI MØTER FREMTIDEN»                      ║
║                                                          ║
╚══════════════════════════════════════════════════════════╝
```
