=== Vem vare? ===
Contributors: vemvare
Tags: visitors, tracking, reverse dns, geolocation, analytics
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Identifiera dina webbplatsbesökare med IP-spårning, Reverse DNS, geolokalisering och kommentarer.

== Description ==

**Vem vare?** är ett kraftfullt WordPress-plugin som hjälper dig att identifiera vem som besöker din webbplats. Inspirerat av tjänster som Leadinfo, men som ett enkelt WordPress-plugin.

= Funktioner =

* **IP-spårning** — Loggar varje unik besökares IP-adress
* **Reverse DNS** — Automatisk reverseDNS-uppslag på varje IP
* **Geolokalisering** — Stad, region, land och ISP via ip-api.com
* **Landsfiltrering** — Klickbara landschips med besökarantal och procent, dropdown med antal per land
* **Kommentarer** — Skriv anteckningar om varje besökare direkt i admin
* **Flerspråksstöd** — Svenska (standard) och engelska (en_US, en_GB)
* **Snyggt admin-gränssnitt** — Modernt, responsivt dashboard med sök, filter och sortering
* **CSV-export** — Exportera alla besökare till CSV
* **Bot-filtrering** — Filtrerar automatiskt bort kända botar och crawlers
* **Proxy-stöd** — Hanterar Cloudflare, X-Forwarded-For med flera

= Tekniskt =

* Skapar en egen databastabell (`wp_vem_vare_visitors`)
* Använder WordPress AJAX-api för all kommunikation
* Nonce-verifiering på alla AJAX-anrop
* Geolokalisering cachas i 24h via WordPress transients
* Full i18n med `__()`, `esc_html__()`, `esc_attr__()` och `esc_html_e()`
* JS-strängar passas via `wp_localize_script` för översättning
* Ren avinstallation — tar bort all data vid avinstallation

== Installation ==

1. Ladda upp mappen `vem-vare` till `/wp-content/plugins/`
2. Aktivera pluginet via "Tillägg"-menyn i WordPress
3. Klicka på "Vem vare?" i vänstermenyn i admin

= Språk =

Pluginet levereras med svenska som standardspråk och engelska översättningar.
Byt WordPress-språk till English under Inställningar → Allmänt för att aktivera engelska.

Du kan också lägga till egna översättningar via .po/.mo-filer i `languages/`-mappen.
Filnamnsformat: `vem-vare-{locale}.po` (t.ex. `vem-vare-de_DE.po` för tyska).

== Changelog ==

= 1.2.0 — 2026-03-28 =
* Nytt: Klickbara organisationsnamn för svenska besökare — öppnar sökning på Allabolag.se
* Nytt: Länken söker automatiskt på organisationsnamn + stad
* Nytt: Extern länk-ikon visas bredvid svenska organisationer
* Förbättrad: Org-kolumnen har hover-effekt med rosa highlight för svenska besökare
* Version bump till 1.2.0

= 1.1.0 — 2026-03-28 =
* Nytt: Klickbar landsfiltrering med chips — visar antal besökare och procent per land
* Nytt: "Besökare per land"-panel med expanderbar vy
* Nytt: Fullt engelskt språkstöd (en_US och en_GB .po/.mo-filer)
* Nytt: .pot-mallfil för nya översättningar
* Nytt: Alla PHP-strängar wrappade med __(), esc_html__(), esc_attr__(), esc_html_e()
* Nytt: Alla JS-strängar passade via wp_localize_script i18n-objekt
* Nytt: Landsfilter-dropdown visar antal besökare per land
* Nytt: AJAX-endpoint vv_get_country_stats för landsstatistik
* Nytt: load_textdomain registrerad på init-hook
* Förbättrad: CSV-export headers översättningsbara
* Förbättrad: AJAX-felmeddelanden översättningsbara
* Version bump till 1.1.0

= 1.0.0 — 2026-03-22 =
* Första release
* IP-spårning med unik besökaridentifiering
* Reverse DNS-uppslag (gethostbyaddr)
* Geolokalisering via ip-api.com (stad, land, ISP, organisation)
* Admin-dashboard med statistikkort
* Sökfunktion (IP, DNS, stad, land, organisation, kommentar)
* Filter: land, tidsperiod, kommentar
* Sortering: senaste besök, första besök, flest besök, IP-adress
* Kommentarssystem per besökare
* CSV-export
* Bot-filtrering
* Paginering
* Responsiv design
* Ren avinstallation

== Upgrade Notice ==

= 1.2.0 =
Svenska organisationer länkas nu till Allabolag.se för snabb företagsinfo.

= 1.1.0 =
Ny landsfiltrering med visuella chips och engelskt språkstöd.

= 1.0.0 =
Första versionen — installera och börja identifiera dina besökare!
