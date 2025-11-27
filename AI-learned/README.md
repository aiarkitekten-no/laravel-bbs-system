# AI-Learned System

## Formål
Denne mappen lar AI lære fra feil og suksesser gjennom prosjektet.
AI SKAL lese disse filene før HVER implementering.

## Filer

| Fil | Formål | Når oppdatere |
|-----|--------|---------------|
| `fungerer.json` | Bevist fungerende løsninger | Etter vellykket test |
| `feil.json` | Feil som ALDRI skal gjentas | Ved hver feil |
| `usikkert.json` | Mulige løsninger, ikke testet | Ved blokkering |
| `godekilder.json` | Pålitelige kilder | Ved nyttig funn |
| `beslutninger.json` | Arkitekturbeslutninger | Ved designvalg |
| `avhengigheter.json` | Godkjente pakker | Ved installasjon |
| `uferdig.json` | Placeholder/mock/demo kode | Ved ENHVER mangel |
| `plan.json` | Prosjektplan med faser | Ved faseendring |

## Regler

1. AI **SKAL** lese alle filer før implementering
2. AI **SKAL** oppdatere etter hver fase
3. AI **SKAL ALDRI** gjenta feil fra `feil.json`
4. AI **SKAL** prioritere løsninger fra `fungerer.json`
5. AI **SKAL** logge ALT ufullstendig til `uferdig.json`
6. AI **SKAL** være ærlig om hva som er ferdig vs ikke

## JSON-struktur

Alle JSON-filer følger samme grunnstruktur:

```json
{
  "_meta": {
    "description": "Beskrivelse av filen",
    "updated": "YYYY-MM-DD HH:MM",
    "version": 1
  },
  "items": []
}
```

## Selvlæring

Systemet blir bedre over tid ved at:
1. Feil aldri gjentas (feil.json)
2. Gode løsninger gjenbrukes (fungerer.json)
3. Beslutninger dokumenteres (beslutninger.json)
4. Ærlighet om mangler (uferdig.json)
