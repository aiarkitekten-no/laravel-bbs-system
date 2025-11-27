# PUNKTET BBS - API Dokumentasjon

## Oversikt

PUNKTET BBS er et moderne API-basert BBS-system bygget med Laravel 10. APIet følger RESTful-prinsipper og returnerer JSON-responser.

## Autentisering

Alle autentiserte endepunkter krever et Sanctum bearer token i Authorization-headeren:

```
Authorization: Bearer <token>
```

### Registrer bruker

```http
POST /api/auth/register
Content-Type: application/json

{
    "handle": "CoolUser",
    "username": "cooluser",
    "email": "user@example.com",
    "password": "minimum8chars",
    "password_confirmation": "minimum8chars",
    "real_name": "Ola Nordmann",
    "location": "Oslo, Norge"
}
```

### Logg inn

```http
POST /api/auth/login
Content-Type: application/json

{
    "login": "username_or_email",
    "password": "yourpassword"
}
```

Respons:
```json
{
    "success": true,
    "token": "1|abc123...",
    "user": {
        "id": 1,
        "handle": "CoolUser",
        "level": "MEMBER"
    }
}
```

### Gjest-innlogging

```http
POST /api/auth/guest
```

### Logg ut

```http
POST /api/auth/logout
Authorization: Bearer <token>
```

---

## Noder

### Vis alle noder

```http
GET /api/nodes
```

### Hvem er online

```http
GET /api/whos-online
```

### Siste innlogginger

```http
GET /api/last-callers/{count?}
```

### Koble til node

```http
POST /api/node/connect/{nodeNumber}
Authorization: Bearer <token>
```

### Koble fra node

```http
POST /api/node/disconnect
Authorization: Bearer <token>
```

### Send node-melding

```http
POST /api/node/message/{nodeNumber}
Authorization: Bearer <token>
Content-Type: application/json

{
    "message": "Hei fra node 1!"
}
```

---

## Meldingsområder

### Vis kategorier (fora)

```http
GET /api/categories
```

### Vis tråder i kategori

```http
GET /api/categories/{categoryId}/threads?page=1&per_page=20
```

### Vis meldinger i tråd

```http
GET /api/threads/{threadId}/messages
```

### Opprett ny tråd

```http
POST /api/messages/thread
Authorization: Bearer <token>
Content-Type: application/json

{
    "forum_id": 1,
    "subject": "Min nye tråd",
    "body": "Innholdet i meldingen"
}
```

### Svar på tråd

```http
POST /api/messages/reply/{threadId}
Authorization: Bearer <token>
Content-Type: application/json

{
    "body": "Mitt svar på tråden"
}
```

### Søk i meldinger

```http
GET /api/messages/search?q=søkeord&forum_id=1
Authorization: Bearer <token>
```

---

## Private Meldinger

### Vis innboks

```http
GET /api/pm/inbox
Authorization: Bearer <token>
```

### Vis sendte

```http
GET /api/pm/sent
Authorization: Bearer <token>
```

### Les melding

```http
GET /api/pm/{id}
Authorization: Bearer <token>
```

### Send privat melding

```http
POST /api/pm
Authorization: Bearer <token>
Content-Type: application/json

{
    "to_user_id": 2,
    "subject": "Hei!",
    "body": "Hvordan går det?"
}
```

### Slett melding

```http
DELETE /api/pm/{id}
Authorization: Bearer <token>
```

---

## Stories

### Dagens historie

```http
GET /api/stories/today
```

### Alle historier

```http
GET /api/stories?page=1
```

### Les historie

```http
GET /api/stories/{storyId}
```

### Send inn historie

```http
POST /api/stories
Authorization: Bearer <token>
Content-Type: application/json

{
    "title": "Min historie",
    "content": "Det var en gang...",
    "author_name": "Anonym"
}
```

### Ranger historie

```http
POST /api/stories/{storyId}/rate
Authorization: Bearer <token>
Content-Type: application/json

{
    "rating": 5
}
```

---

## Oneliners

### Vis oneliners

```http
GET /api/oneliners
```

### Legg til oneliner

```http
POST /api/oneliners
Authorization: Bearer <token>
Content-Type: application/json

{
    "text": "PUNKTET rules!"
}
```

---

## Filområde

### Vis filkategorier

```http
GET /api/files/categories
Authorization: Bearer <token>
```

### Vis filer i kategori

```http
GET /api/files/categories/{categoryId}/files
Authorization: Bearer <token>
```

### Søk filer

```http
GET /api/files/search?q=filename
Authorization: Bearer <token>
```

### Last ned fil

```http
GET /api/files/{fileId}/download
Authorization: Bearer <token>
```

### Last opp fil

```http
POST /api/files/upload
Authorization: Bearer <token>
Content-Type: multipart/form-data

file: (binary)
category_id: 1
description: "Fil beskrivelse"
```

### Nyeste filer

```http
GET /api/files/recent
Authorization: Bearer <token>
```

---

## Spill (Door Games)

### Vis tilgjengelige spill

```http
GET /api/games
Authorization: Bearer <token>
```

### Start spill

```http
POST /api/games/{gameId}/start
Authorization: Bearer <token>
```

### Utfør spillhandling

```http
POST /api/games/{gameId}/action
Authorization: Bearer <token>
Content-Type: application/json

{
    "action": "move",
    "data": {"direction": "north"}
}
```

### Highscores

```http
GET /api/games/{gameId}/scores
Authorization: Bearer <token>
```

### Spillhistorikk

```http
GET /api/games/history
Authorization: Bearer <token>
```

---

## ANSI Art

### Vis galleri

```http
GET /api/ansi?page=1
Authorization: Bearer <token>
```

### Vis kunst

```http
GET /api/ansi/{id}
Authorization: Bearer <token>
```

### Last opp ANSI

```http
POST /api/ansi
Authorization: Bearer <token>
Content-Type: application/json

{
    "title": "Min kunst",
    "artist": "CoolArtist",
    "content": "... ANSI data ...",
    "category": "logo"
}
```

### Tilfeldig kunst

```http
GET /api/ansi/random
Authorization: Bearer <token>
```

---

## Avstemninger

### Aktive avstemninger

```http
GET /api/polls
Authorization: Bearer <token>
```

### Vis avstemning

```http
GET /api/polls/{id}
Authorization: Bearer <token>
```

### Opprett avstemning

```http
POST /api/polls
Authorization: Bearer <token>
Content-Type: application/json

{
    "question": "Beste BBS?",
    "options": ["PUNKTET", "Andre", "Vet ikke"],
    "multiple_choice": false,
    "expires_at": "2025-12-31"
}
```

### Stem

```http
POST /api/polls/{id}/vote
Authorization: Bearer <token>
Content-Type: application/json

{
    "option_id": 1
}
```

---

## Sosiale Funksjoner

### Tidsbank - Se saldo

```http
GET /api/social/time-bank
Authorization: Bearer <token>
```

### Tidsbank - Sett inn tid

```http
POST /api/social/time-bank/deposit
Authorization: Bearer <token>
Content-Type: application/json

{
    "minutes": 30
}
```

### Klubber

```http
GET /api/social/clubs
Authorization: Bearer <token>
```

### Opprett klubb

```http
POST /api/social/clubs
Authorization: Bearer <token>
Content-Type: application/json

{
    "name": "Elite Coders",
    "description": "For de beste"
}
```

### Graffiti Wall

```http
GET /api/social/graffiti
Authorization: Bearer <token>
```

### Legg til graffiti

```http
POST /api/social/graffiti
Authorization: Bearer <token>
Content-Type: application/json

{
    "content": "PUNKTET var her!"
}
```

### Bursdager

```http
GET /api/social/birthdays
Authorization: Bearer <token>
```

---

## Bulletins

### Vis bulletins

```http
GET /api/bulletin
Authorization: Bearer <token>
```

### BBS-lenker

```http
GET /api/bulletin/bbs-links
Authorization: Bearer <token>
```

### Logoff-sitat

```http
GET /api/bulletin/logoff-quote
Authorization: Bearer <token>
```

### System-info

```http
GET /api/bulletin/system-info
Authorization: Bearer <token>
```

---

## Admin/SysOp (Krever SYSOP-nivå)

### Dashboard

```http
GET /api/admin/dashboard
Authorization: Bearer <token>
```

### Caller Log

```http
GET /api/admin/caller-log?days=7
Authorization: Bearer <token>
```

### Top Users

```http
GET /api/admin/top-users?limit=10
Authorization: Bearer <token>
```

### System Stats

```http
GET /api/admin/system-stats
Authorization: Bearer <token>
```

### Brukeradministrasjon

```http
GET /api/admin/users?page=1&search=query
Authorization: Bearer <token>

PUT /api/admin/users/{userId}
Authorization: Bearer <token>
Content-Type: application/json

{
    "level": "VERIFIED",
    "time_limit": 90
}

DELETE /api/admin/users/{userId}
Authorization: Bearer <token>
```

### Vedlikeholdsmodus

```http
POST /api/admin/maintenance
Authorization: Bearer <token>
Content-Type: application/json

{
    "enabled": true,
    "message": "System under vedlikehold"
}
```

### Tøm cache

```http
POST /api/admin/clear-cache
Authorization: Bearer <token>
```

### System Diagnostics

```http
GET /api/admin/diagnostics
Authorization: Bearer <token>
```

---

## Health Check

### Ping (offentlig)

```http
GET /api/health/ping
```

Respons:
```json
{
    "status": "OK",
    "message": "PUNKTET BBS er online",
    "timestamp": "2025-07-23T12:00:00+00:00"
}
```

### Status (offentlig)

```http
GET /api/health/status
```

Respons:
```json
{
    "status": "healthy",
    "checks": {
        "database": {"healthy": true, "latency_ms": 2.5},
        "cache": {"healthy": true, "latency_ms": 0.8},
        "storage": {"healthy": true, "latency_ms": 1.2}
    }
}
```

---

## Feilhåndtering

Alle feil returneres i følgende format:

```json
{
    "success": false,
    "error": {
        "code": 422,
        "message": "Valideringsfeil - sjekk input-data",
        "fields": {
            "email": ["e-post er påkrevd"]
        },
        "timestamp": "2025-07-23T12:00:00+00:00"
    }
}
```

### HTTP Statuskoder

| Kode | Betydning |
|------|-----------|
| 200 | OK |
| 201 | Opprettet |
| 400 | Dårlig forespørsel |
| 401 | Ikke autorisert |
| 403 | Tilgang nektet |
| 404 | Ikke funnet |
| 422 | Valideringsfeil |
| 429 | For mange forespørsler |
| 500 | Serverfeil |

---

## Rate Limiting

API-et har rate limiting basert på brukernivå:

| Nivå | Requests/minutt |
|------|-----------------|
| GUEST | 30 |
| NEW | 45 |
| MEMBER | 60 |
| VERIFIED | 80 |
| ELITE | 100 |
| COSYSOP | 150 |
| SYSOP | 200 |

Rate limit info returneres i response headers:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

---

## Språk

API-et støtter norsk (no) og engelsk (en). Send ønsket språk i `Accept-Language` header:

```
Accept-Language: no
```

Eller som query parameter:

```
GET /api/stories?lang=en
```

---

## Versjon

Nåværende API-versjon: **v1**

Base URL: `https://punktet.no/api`
