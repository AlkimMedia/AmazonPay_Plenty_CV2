##Über dieses Plugin
_Amazon Pay Checkout v2_ ist die neue Conversion optimierte Checkout-Lösung von Amazon Pay mit nahtloser Integration in
den plentyShop LTS.  
Ermöglichen Sie es über 300 Millionen von Amazon-Kunden, sich auf Ihrer Website einzuloggen und zu bezahlen. Jeder
Amazon Kunde kann diese Zahlungsart sofort in Ihrem Shop nutzen.

## Voraussetzungen

### Systemvorraussetzungen

* [x] plentyShop LTS Version 5+
* [x] IO Plugin Version 5+

## Amazon Händler-Konto
!!! info
    Wenn Sie bereits ein aktives Amazon Pay Konto besitzen, das Sie z.B. mit anderen Plugins genutzt haben, können Sie diesen Schritt überspringen. 
### Amazon Händler-Konto einrichten

Um Amazon Pay nutzen zu können, benötigen Sie ein Amazon Pay Händlerkonto, auch wenn Sie bereits ein anderes Amazon
Verkäufer-Konto haben. Um den Registrierungsprozess zu beginnen, gehen Sie auf die Amazon
Pay-Website (https://pay.amazon.de) und klicken Sie auf die Schaltfläche 'Registrieren' in der oberen rechten Ecke der
Webseite.

!!! danger "Wichtig"
    Sie können bei der Einrichtung eines Amazon Pay-Händlerkontos kein bestehendes Konto verwenden, dass Sie bereits für den
    Verkauf bei Amazon nutzen.

Die mit Ihrem neuen Amazon Pay Händlerkonto verknüpfte E-Mail-Adresse muss sich von der E-Mail-Adresse unterscheiden,
die Sie für jedes andere Amazon-Konto verwenden.   
Außerdem stellen Sie bitte sicher, dass Sie sich von allen Amazon-Konten abgemeldet haben, bevor Sie mit dem Amazon
Pay-Registrierungsprozess beginnen.

### Benötigte Informationen

Sie müssen Ihre Geschäftsinformationen angeben, um sich für ein Amazon Payments Händlerkonto zu registrieren. Bitte
achten Sie darauf, die Informationen so einzugeben, dass sie genau mit den Angaben in Ihren offiziellen
Nachweisdokumenten übereinstimmen.
Die Informationen auf diesen Registrierungsseiten werden von Amazon überprüft, und falsche oder abweichende Angaben
können zu Verzögerungen führen.
Weitere Informationen finden Sie unter https://pay.amazon.de/help/202153180

##Installation

!!! tip "Test-Plugin-Set"
    Wie bei jedem Eingriff in Ihr System, sollten Sie alles vorab in einem Test-Plugin-Set ausführen und erst nach geprüfter
    Funktionalität live verwenden.

### Plenty Marketplace

Sie finden die aktuelle Version im [plentyMarketplace](https://marketplace.plentymarkets.com/) und können sie von dort
wie gewohnt installieren und updaten.

### GitHub
Der Amazon Pay Button ist ausgegraut. Was kann ich tun?
Alternativ und für schnellere Updates können Sie gern auch
direkt [unser GitHub Repository](https://github.com/AlkimMedia/AmazonPay_Plenty_CV2) zum Einbinden nutzen. Im "main"
Branch werden alle Updates kontinuierlich veröffentlicht. Für bestimmte Versionen gibt es eigenständige Branches.

### Logos einbinden

Wenn Sie in Ihrem Theme Logos der unterstützten Zahlarten anzeigen, finden Sie hier eine Auswahl an Amazon Pay Logos in
verschiedenen Farben und Formaten:  
[:fontawesome-solid-download: Logos herunterladen](images/amazon_pay_logo_pack.zip){:.md-button.md-button--primary.block.center.mt5}

## Konfiguration

### Plugin-Einrichtung

!!! info
    Alle Einstellungsmöglichkeiten sind wie gewohnt in der _Plugin-Set-Übersicht_ zu finden.

### Sellercentral-Daten übernehmen

Damit das Plugin mit Amazon Pay kommunizieren kann, müssen im ersten Schritt der Konfiguration die Amazon Pay
Zugangsdaten hinterlegt werden. Sie finden diese in
Ihrer [Sellercentral](https://sellercentral-europe.amazon.com/gp/pyop/seller/integrationcentral/). Sie die Daten zu
übernehmen sind, sehen Sie in diesem Video:
!!! warning "TODO"
    Dieses Video muss nach Update in der Sellercentral neu aufgenommen werden
<video controls width="100%">
<source src="video/sellercentral.mp4" type="video/mp4">
Sorry, your browser doesn't support embedded videos.
</video>

### IPN einrichten
__🎞️ Alle Infos zur IPN in diesem Video__
<video controls width="100%">
<source src="video/ipn.mp4" type="video/mp4">
Sorry, your browser doesn't support embedded videos.
</video>

!!! danger "Sandbox / Produktion"
    Die Konfiguration der IPN muss für Sandbox und die Produktivumgebung jeweils separat vorgenommen werden.

Die IPN (_Instant Payment Notification_) sorgt dafür, dass Ihr plentymarkets System in Echtzeit über den aktuellen Stand
der Zahlungen informiert wird.
Hinterlegen Sie dafür in Ihrer Sellercentral unter [_Einstellungen_ »
_Integrationseinstellungen_](https://sellercentral-europe.amazon.com/gp/pyop/seller/account/settings/user-settings-view.html) `https://www.domain.com/payment/amazon-pay-ipn/` (`www.domain.com`
bitte durch deine Shop-Domain ersetzen) als _Händler-URL_.

### Container-Verknüpfungen
Folgende Container-Verknüpfungen sollten gesetzt werden. Bitte beachten Sie, dass die Standard-Verknüpfungen nur teilweise ausreichen, weil diese lediglich eine 1:1 Zuordnung erlauben.

!!! success "Amazon Pay Checkout - Button"
    Dieser Content erzeugt den Button, der den Kunden zum Amazon Checkout weiterleitet. Grundsätzlich kannst du ihn überall platzieren, wo er dir sinnvoll erscheint. Die folgende Liste gibt Vorschläge, aber insbesondere, ob du ihn bei "Shopping cart" und "Shopping cart preview" eher vor oder nach dem normalen Checkout-Button anzeigen möchtest, ist natürlich dir überlassen
    
    ✓ Shopping cart: After "Checkout" button  
    ✓ Shopping cart overlay: Extend buttons  
    ✓ Shopping cart preview: After "Checkout" button  

!!! success "Amazon Pay Checkout - Button auf Artikelseite (Schnellkauf)"
    ✓ Single item: After "Add to shopping cart" button 

!!! success "Amazon Pay Login - Button"
    ✓ Login overlay: Container in a row with the buttons
    ✓ Registration overlay: Container in a row with the buttons

### ShopBuilder
Um die Amazon Buttons auf Seiten einzubinden, die mit dem ShopBuilder gestaltet wurden, kann das Code-Element verwendet werden.
!!! note "Login-Button"
    Verwenden Sie diesen Code für einen Login-Button, der ein Kundenkonto anlegt, aber den Kunden nicht notwendigerweise zur Kasse führt:
    
    `<div class="amazon-login-button"></div>`

!!! note "Checkout-Button"
    Verwenden Sie diesen Code für einen Checkout-Button, der den Amazon Pay Checkout einleitet:

    `<div class="amazon-pay-button"></div>`

### Sonstige Plugin-Einstellungen
__🎞️ Alle Infos zu den Einstellungen in diesem Video__
<video controls width="100%">
<source src="video/settings.mp4" type="video/mp4">
Sorry, your browser doesn't support embedded videos.
</video>
<dl>
<dt>Sandbox aktivieren</dt>
<dd>Schaltet das Plugin in den Sandbox-Modus. Dabei werden keine echten Zahlungen ausgelöst. Bitte beachte, dass es für den Sandbox-Modus in Sellercentral eigene Konfigurationsmöglichkeiten gibt.</dd>
<dt>Buttons verstecken (debug)</dt>
<dd>Versteckt die Buttons im Frontend mit CSS (display:none), sodass du im Frontend testen kannst, ohne dass deine Kunden beeinträchtigt werden. (siehe <a href="#testen">Testen</a>)</dd>
<dt>Art der Autorisierung</dt>
<dd>Hiermit können Sie einstellen, wann die Autorisierung der Zahlung durchgeführt werden soll. Wenn Sie keine besonderen Anforderungen haben, sollten Sie es hier bei der Standard-Einstellung belassen.
Bei Auswahl der Standard-Einstellung `Unbedingt während des Checkouts` versucht das Plugin, die Zahlung bereits während des Checkouts zu autorisieren, 
um den Kunden im Fall einer Ablehnung zur Auswahl einer anderen Zahlungsart zu bewegen. 
</dd>
<dt>Art des Zahlungseinzugs</dt>
<dd>In den allermeisten Fällen ist der Einzug direkt nach Autorisierung die beste Wahl.</dd>
<dt>Auftragsstatus nach erfolgreicher Autorisierung</dt>
<dd>Hier können Sie eine Status-ID hinterlegen (z.B. 5.0). Diese wird nach erfolgreicher Autorisierung gesetzt, um zu signalisieren, dass die Ware versendet werden kann. Um die Warenbestandsautomatik zu nutzen, kann hier auch "4/5" eingetragen werden, damit der Status je nach Bestand auf 4.0 oder 5.0 gesetzt wird.</dd>
<dt>E-Mail-Adresse für Versandadresse verwenden</dt>
<dd>Wenn diese Einstellung aktiviert ist, wird die E-Mail-Adresse des Kunden in die Versandadresse aufgenommen (z.B. zur Übergabe an Paketdienste)</dd>
</dl>

##Zahlungsablauf
Eine Amazon Pay Zahlung besteht aus zwei Teilen: Einer Autorisierung und dem eigentlichen Zahlungseinzug. Sie haben so
die Möglichkeit, die Zahlung erst bei Versand einzuziehen, da Amazon Pay für eine bestimmte Zeit den erfolgreichen
Zahlungseinzug garantiert. Trotzdem ist es vorteilhaft, die Zahlung sofort einzuziehen, da es sonst aus
Sicherheitsgründen nötig sein kann, dass der Kunde die Zahlung erneut bestätigen muss, was zu Verzögerungen führen kann.

Sie haben in den Plugin-Einstellungen vielfältige Möglichkeiten, den für Sie passenden Zahlungsablauf einzustellen.

!!! info "Schnellstart"
    Wenn Sie keine besonderen Anforderungen an Ihren Zahlungsablauf haben, können Sie die ursprünglichen Einstellungen
    beibehalten. Dann wird die Zahlung immer sofort eingezogen und Sie müssen lediglich Erstattungen entweder per
    Ereignisaktion oder direkt in der Sellercentral vornehmen.

### Ereignisaktionen

!!! danger "Wichtig"
    Bei den Ereignisaktionen wird die Plugin-Konfiguration des Hauptmandanten geladen. Bitte konfigurieren Sie das Plugin
    daher auch vollständig mit allen Zugangsdaten im Plugin-Set des Hauptmandanten.
    Ereignisaktionen können eingerichtet werden unter: `Einrichtung` > `Aufträge` > `Ereignisse`.

#### Vollständiger Einzug der Amazon Pay Zahlung

Diese Ereignisaktion muss nur dann eingerichtet werden, wenn der Zahlungseinzug nicht direkt nach Autorisierung erfolgen
soll. Sinnvoll ist eine Kopplung an den Versand, also z.B. an den Statuswechsel auf `[7] Warenausgang gebucht`.

Dies könnte z.B. so aussehen:
![](images/event_procedure_capture.png)

#### Erstattung der Amazon Pay Zahlung

Um Kunden eine Zahlung im Fall einer Gutschrift zu erstatten, z.B. bei einer Retoure, könnten Sie diese Ereignisaktion
beispielsweise für den Statuswechsel einer Gutschrift oder die Anlage des Gutschriftdokuments einrichten. Die Aktion
veranlasst eine Rückzahlung in Höhe der Gutschrift, die Sie angelegt haben.

Dies könnte z.B. so aussehen:
![](images/event_procedure_refund.png)

##Testen

###Sandbox
Zum Testen der Integration empfiehlt es sich, in den Sandbox-Modus zu schalten, da dann keine realen Transaktionen
vorgenommen werden. Um Amazon Pay in Ihrem Shop Frontend aus Kundensicht zu testen, benötigen Sie einen speziellen
Test-Account, da in der Sandbox reale Login-Daten von Amazon Konten nicht funktionieren. Einen Test-Account können Sie
hier in Ihrer Sellercentral anlegen:  
[https://sellercentral.amazon.de/gp/pyop/seller/testing](https://sellercentral.amazon.de/gp/pyop/seller/testing)

Eine englische Video-Anleitung stellt Amazon Pay hier zur Verfügung:  
[Youtube-Video](https://www.youtube.com/watch?v=UFK4cnxH3F4)

###Versteckte Buttons
Wenn Sie in Ihrem Live-Shop testen, können Sie die Amazon Pay Buttons ausblenden lassen, indem Sie bei der
Plugin-Konfiguration die Option "Buttons verstecken (debug)" anwählen. Um die versteckten Buttons anzuzeigen, können Sie
diesen Befehl in Ihre Browser-Konsole eingeben:

    jQuery('.amazon-pay-button, .amzLoginButton').css('cssText', 'display:block !important;');

##Hilfe
Sollten noch Fragen offen sein oder unerwartete Probleme auftauchen, kontaktieren Sie bitte unseren Support.

[:fontawesome-solid-envelope: Support kontaktieren](mailto:info@alkim.de){:.md-button.md-button--primary.block.center.mt5}