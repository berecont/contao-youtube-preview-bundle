# Youtube Vorschaubild  
## Speichert Youtube Vorschaubilder in der Dateiverwaltung  
Im Backend wird eine neue Backendmodul-Kategorie **YOUTUBE** erstellt. Im Backendmodul **Youtube Vorschaubild** kann nach Eingabe der Youtube-ID das entsprechende Vorschaubild abgespeichert werden.  
### Beschreibung  
- neues Element erstellen
- Youtube ID eintragen
- Zielordner für das zu speichernde Bild auswählen
- SPEICHERN

Mit dem SPEICHERN wird im ausgewählten Ordner eine Bilddatei des Youtube Vorschaubildes gespeichert.  
Dateiname: youtubethumb-&lt;Youtube-ID&gt;.jpg  

Der Eintrag im Backendmodul kann gelöscht werden. Das Bild bleibt bestehen.  
Das Bild kann gelöscht werden. Der Eintrag im Backendmodul bleibt bestehen und neu abgespeichert werden.  
Bei Erneutem SPEICHERN wird das Bild überschrieben.  

Es wird versucht ein **maxresdefault.jpg** zu laden. Falls dies nicht mögich ist, wird ein **hqdefault.jpg** gesucht.  

Online-Alternative auf CodePen: [beRecont - youtube vorschaubild thumbnail](https://codepen.io/berecont/pen/JodBeeV)
