# Návrh obrazovek

Vizuální reference pro šest obrazovek. Data na obrázcích jsou ukázková (23 z 54 milníků ověřeno). Texty v návrhu jsou anglicky. V aplikaci je přelož podle kapitoly „Slovník UI“ v `docs/ZADANI.md`.

| Obrázek | Zdroj | Obrazovka |
| --- | --- | --- |
| `screens/01-hlavni-stranka.png` | `source/Main.dc.html` | Veřejná hlavní stránka: počítadla, mřížka 9 × 6, poslední objevy, žebříček |
| `screens/02-dashboard-hrace.png` | `source/Dashboard.dc.html` | Přehled hráče: vlastní žádosti, statistika, mřížka s vlastními objevy |
| `screens/03-nova-zadost.png` | `source/Submit.dc.html` | Nová žádost: druh, stat, screenshoty s typem, poznámka |
| `screens/04-admin-fronta-a-detail.png` | `source/Admin.dc.html` | Admin: fronta, detail, kontroly, schválení a zamítnutí |
| `screens/05-detail-milniku.png` | `source/Milestone.dc.html` | Detail ověřeného milníku s historií a blokem pro adminy |
| `screens/06-discord-zpravy.png` | `source/Discord.dc.html` | Obsah zpráv na Discordu |

## Zdrojové soubory

Soubory `.dc.html` jsou šablony z návrhového nástroje a samy o sobě nepoběží. Jsou užitečné kvůli přesným hodnotám v inline stylech (barvy, mezery, velikosti písma, zaoblení).

- `{{nazev}}` je hodnota z metody `renderVals()` ve skriptu `<script type="text/x-dc">` na konci souboru. Tam jsou i ukázková data.
- `<sc-for list="{{seznam}}" as="polozka">` je cyklus, `<sc-if value="{{podminka}}">` je podmínka.
- Implementuj to v Blade a Tailwindu, ne přenesením těchto šablon.

## Vizuální systém

| Token | Hodnota | Použití |
| --- | --- | --- |
| Pozadí | `#0E1214` | Stránka |
| Plocha | `#151B1E`, `#1B2327` | Karty, aktivní položky |
| Okraje | `#263036`, `#1F282C` | Rámečky a oddělovače |
| Text | `#E8EEF0`, `#A4B1B6`, `#7F8E94` | Hlavní, vedlejší, tlumený |
| Ověřeno, hlavní akce | `#45D19E`, text `#7EE3BC`, text na tlačítku `#062A1D` | Plný tónovaný box a fajfka |
| Čeká na schválení | `#F2B24C`, text `#F5C77A` | Čárkovaný rámeček a přesýpací hodiny |
| Zamítnuto | `#F08A7E`, text `#F6B1A8` | Plný tenký rámeček |
| Písmo | Space Grotesk (nadpisy), Inter (text), JetBrains Mono (čísla, kódy, ID) | Google Fonts nebo self-hosted |
| Zaoblení | 8 až 16 px | Tlačítka, karty |
| Dotykové cíle | aspoň 44 px | Všechny ovládací prvky |

Stav milníku se nikdy nerozlišuje jen barvou. Vždy má i ikonu a text, protože zelená a jantarová mají podobný jas.
