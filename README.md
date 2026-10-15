# WltrAlsoBought – Andere Kunden kauften auch

Shopware 6.7 · WLTR – die digitalagentur

Zeigt auf der **Produktseite** und im **Warenkorb** Produkte, die andere Kunden zusammen mit diesem Produkt
(bzw. den Produkten im Warenkorb) gekauft haben, berechnet aus den echten Bestellungen des Shops.

## Berechnung

- Gezählt wird, in wie vielen Bestellungen zwei Produkte gemeinsam vorkamen. Stornierte Bestellungen zählen nicht.
- Varianten zählen für ihr Hauptprodukt („zusammen gekauft“ unabhängig von Größe/Farbe).
- Gespeichert werden pro Produkt die besten Kandidaten (Tabelle `wltr_also_bought`).
- Neu berechnet wird **beim Aktivieren**, **täglich** per Scheduled Task (`wltr.also_bought.rebuild`) und
  bei Bedarf per Konsole: `bin/console wltr:also-bought:rebuild`.
- Im Warenkorb werden die Werte aller Warenkorbprodukte addiert; Produkte im Warenkorb werden nicht empfohlen.
- Angezeigt werden nur Produkte, die im Verkaufskanal sichtbar und lieferbar sind.

Gibt es zu wenige gemeinsame Käufe (z. B. bei neuen Produkten), bleibt der Bereich weg. Das Plugin füllt bewusst
nicht mit anderen Produkten auf, damit „kauften auch“ stimmt.

## Konfiguration

**Erweiterungen → Meine Erweiterungen → Andere Kunden kauften auch → Konfigurieren**

| Einstellung | Standard |
|---|---|
| Auf der Produktseite / im Warenkorb anzeigen | an / an |
| Anzahl Produkte | 4 |
| Erst ab so vielen Produkten anzeigen | 2 |
| Bestellungen der letzten Monate (0 = alle) | 36 |
| Mindestens in … gemeinsamen Bestellungen | 1 |
| Gespeicherte Kandidaten pro Produkt | 20 |

Die Berechnungseinstellungen wirken ab der nächsten Neuberechnung.

## Darstellung

Die Produktkarten sind die normalen Listing-Karten des aktiven Themes. Überschriften sind Textbausteine
(`wltrAlsoBought.headline`, `wltrAlsoBought.cartHeadline`), das Template
`component/wltr-also-bought/product-list.html.twig` hat Blöcke `wltr_also_bought_*` zum Überschreiben.

## Installation

```bash
bin/console plugin:refresh
bin/console plugin:install --activate WltrAlsoBought
bin/console theme:compile
bin/console cache:clear
```
