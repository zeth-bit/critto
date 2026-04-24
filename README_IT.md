# Sistema Crittografico di Zeth

> Uno strumento di cifratura in stile Vigenère lato server, costruito per un gioco di ruolo, distribuito come singolo file PHP con un archivio messaggi lato client.

---

## Panoramica

Questo progetto è stato creato nel gennaio 2025 come **strumento di comunicazione privata per un gioco di ruolo online**, con la collaborazione dell'IA. Il requisito era pratico e concreto: i giocatori di fazioni diverse o interne avevano bisogno di un modo per scambiare messaggi segreti *in gioco*, dove il meccanismo di cifratura stesso faceva parte della narrativa.

La soluzione è una pagina PHP autocontenuta che implementa un **cifrario di Cesare modificato con una chiave numerica a più valori**, concettualmente vicino a un [cifrario di Vigenère](https://en.wikipedia.org/wiki/Vigen%C3%A8re_cipher), abbinato a un archivio messaggi lato client memorizzato in `localStorage`. Nessun database, nessun account utente, nessuno stato server oltre all'elaborazione PHP stessa.

Lo strumento è volutamente minimo nel suo ingombro di distribuzione: un file `.php`, un `site.webmanifest` e un set di favicon, tutto il necessario per ospitarlo su un server condiviso come Altervista.

---

## Architettura

L'applicazione segue il pattern **file singolo, rendering lato server una volta sola, stato gestito dal client**.

```
┌─────────────────────────────────────────────────────────────────┐
│                         index.php                               │
│                                                                 │
│  ┌──────────────┐    POST     ┌────────────────────────────┐   │
│  │  Form HTML   │ ──────────► │  PHP: cifra_decifra()      │   │
│  │ (chiave+     │             │  - Valida l'input          │   │
│  │  testo+op.)  │ ◄────────── │  - Applica shift per char  │   │
│  └──────────────┘   Re-render │  - Restituisce HTML sanif. │   │
│                               └────────────────────────────┘   │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  JavaScript (lato client, senza coinvolgimento server)  │    │
│  │  - Lettore file (.txt → textarea)                       │    │
│  │  - CRUD localStorage (salva / cerca / esporta / import)│    │
│  │  - Clipboard API con fallback legacy                   │    │
│  │  - Orologio in tempo reale (setInterval)               │    │
│  │  - Sistema di conferma modale                          │    │
│  │  - Sistema di notifiche toast                          │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

**Flusso dei dati:**

1. L'utente compila la chiave (es. `3,-1,5`), il testo e l'operazione (cifra/decifra).
2. Il form fa un POST su se stesso; PHP elabora e ri-renderizza la pagina con il risultato iniettato nell'HTML.
3. Dal risultato renderizzato, l'utente può copiare, scaricare come `.txt`, o salvare nell'archivio lato client tramite JavaScript.
4. L'archivio risiede interamente in `localStorage` sotto la chiave `zethMessages`, nessun viaggio di ritorno al server.

**Perché questa separazione?** Il cifrario stesso è privo di stato e deterministico, quindi PHP lato server è sufficiente e mantiene la logica verificabile. L'archivio, invece, beneficia di restare sul client: non è necessario alcun sistema di login, i dati sono naturalmente circoscritti al singolo browser e l'ambiente di hosting (un server condiviso gratuito) non impone alcun overhead di database.

---

## Decisioni Progettuali Chiave

### 1. Chiave in stile Vigenère rispetto a uno spostamento fisso di Cesare

Un classico cifrario di Cesare usa un singolo spostamento intero applicato uniformemente a ogni lettera. Questo progetto lo estende a una **lista di interi separati da virgola** (es. `3,-2,7`), ciclando tra i valori della chiave carattere per carattere, esattamente la struttura di un cifrario di Vigenère, ma con offset interi arbitrari invece di parole chiave basate sull'alfabeto.

Si è trattato di un aumento deliberato della complessità motivato dal contesto di gioco: una chiave a singolo numero è banalmente attaccabile con forza bruta; una chiave ciclica a più valori richiede di conoscere sia la lunghezza della chiave che i suoi valori, il che costituisce una resistenza sufficiente per un segreto in gioco.

Sono supportati offset negativi, il che significa che lo stesso formato di chiave può produrre una distribuzione più ampia di testo cifrato anche per messaggi brevi.

**Compromesso:** Si tratta comunque di un cifrario a sostituzione classico e non offre vera sicurezza crittografica. Per scopi di gioco questo è molto più che accettabile, l'obiettivo è la verosimiglianza e il divertimento, non la totale riservatezza contro un attaccante.

### 2. L'indice della chiave avanza solo sui caratteri alfabetici

```php
if (ctype_alpha($carattere)) {
    $offset = $spostamenti[$indice_chiave % $lunghezza_chiave];
    // ...
    $indice_chiave++;
}
```

Spazi, punteggiatura, cifre e a capo passano inalterati e **non consumano una posizione della chiave**. Questa è una scelta progettuale significativa: significa che il testo cifrato preserva la struttura delle parole del testo in chiaro (gli spazi rimangono spazi), un compromesso di leggibilità deliberato. In un contesto di gioco, questo fa sì che l'output sembri più un "messaggio in codice" e meno rumore casuale, rendendo anche la decifratura leggermente più prevedibile.

I caratteri di a capo sono esplicitamente preservati (`"\n"` e `"\r"` vengono passati attraverso), quindi i messaggi multiparagrafo cifrano e decifrano con la formattazione intatta.

### 3. Aritmetica modulare con normalizzazione positiva

```php
$new_pos = (($pos + $offset) % 26 + 26) % 26;
```

Il pattern `+ 26) % 26` garantisce che gli offset negativi (dalla decifratura o da valori di chiave negativi) non producano mai un indice negativo, cosa che `%` da solo farebbe in PHP. Questa è una correzione standard per l'aritmetica modulare su numeri negativi che PHP non gestisce allo stesso modo dell'operatore `%` di Python.

### 4. Tutto l'output è sanitizzato lato server

```php
function sanitize_output($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
```

Ogni valore fornito dall'utente che viene echeggiato nell'HTML passa attraverso `sanitize_output()`. Questo previene XSS memorizzato o riflesso, il che è importante anche per uno strumento privato, specialmente perché i messaggi possono contenere parentesi angolari o virgolette se qualcuno incolla codice o HTML nel campo di testo.

L'archivio lato client usa il proprio equivalente:

```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
```

Usare un nodo DOM temporaneo per l'escape è un approccio affidabile nativo del browser che evita le insidie della sanitizzazione basata su regex.

### 5. localStorage come livello di persistenza

L'archivio memorizza un array di oggetti messaggio sotto `zethMessages`. Ogni voce registra: testo originale, risultato, chiave, operazione e timestamp ISO. Questo significa:

- Nessun database server o login richiesto.
- I dati sono circoscritti al profilo del browser specifico, appropriato per uso personale.
- L'importazione/esportazione come JSON fornisce portabilità tra dispositivi senza alcun backend.

**Compromesso:** `localStorage` non è cifrato a riposo. Chiunque abbia accesso fisico al browser può leggere l'archivio. Per uno strumento di gioco questo è un limite accettabile.

### 6. Header di sicurezza

```php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
```

Questi sono impostati anche per un piccolo progetto personale, una buona abitudine che previene il MIME sniffing, il clickjacking tramite iframe e attiva il filtro XSS integrato del browser nei browser più vecchi.

---

## Analisi del Codice

Il file è strutturato nel seguente ordine di lettura:

| Righe | Contenuto |
|-------|---------|
| 1–15 | Configurazione PHP: gestione errori (disabilitata per l'output), header di sicurezza, helper `sanitize_output()` |
| 18–59 | Funzione core del cifrario `cifra_decifra()`: validazione input, ciclo carattere per carattere, spostamento modulare |
| 61–97 | Gestore POST: analisi e validazione dell'invio del form, chiamata al cifrario, popolamento di `$risultato` o `$messaggio_errore` |
| 99–627 | HTML + CSS: struttura della pagina, layout a due pannelli (form principale + barra laterale archivio), media query responsive |
| 628–736 | Corpo HTML: markup del form, blocco risultato condizionale, pannello archivio, overlay modale, widget orologio |
| 752–1171 | JavaScript: sistema notifiche, sistema modale, lettore file, CRUD localStorage, gestione appunti, validazione form, orologio in tempo reale |

**Inizia da `cifra_decifra()`**: è il cuore dell'applicazione e può essere compresa in isolamento. Tutto il resto è scaffolding di input/output attorno ad essa.

---

## Guida alle Modifiche

### Cambiare l'algoritmo di cifratura

Tutta la logica è in `cifra_decifra()` (righe 18–59). Per sostituire un algoritmo diverso (es. un vero Vigenère con chiavi basate su lettere), sostituisci solo il blocco interno che calcola `$new_pos`. Il contratto della funzione, che accetta `$testo`, `$spostamenti` (array di interi), `$operazione`, può rimanere invariato.

### Aggiungere supporto per caratteri non latini

Attualmente, solo i caratteri ASCII `[a-z]` e `[A-Z]` vengono spostati. Il codice usa `ctype_alpha()` che è sensibile alla locale ma ha come default l'intervallo ASCII. Per supportare caratteri accentati (es. italiano `à`, `è`):

1. Sostituire `ctype_alpha()` con un controllo `preg_match('/^\pL$/u', $carattere)`.
2. Sostituire `ord()`/`chr()` con `mb_ord()`/`mb_chr()` (disponibili in PHP 7.2+).
3. Ridefinire i limiti dell'alfabeto, questa è la parte difficile, poiché l'italiano aggiunge caratteri al di fuori dell'intervallo a 26 lettere.

### Estendere lo schema dell'archivio

L'oggetto messaggio salvato in `localStorage` è costruito in `salvaMessaggio()` (riga 938). Aggiungi nuovi campi lì e aggiorna la funzione di rendering `updateSavedPanel()` per visualizzarli. Il formato di importazione/esportazione è JSON semplice, quindi le modifiche allo schema sono retrocompatibili purché si gestiscano i campi mancanti con fallback `??`.

### Rendere la chiave validata visivamente lato client

La validazione lato client attuale (riga 1160) verifica che tutti i valori separati da virgola siano interi analizzabili ma non impone una lunghezza minima della chiave. Si potrebbe aggiungere:

```javascript
if (numeri.length < 2) {
    e.preventDefault();
    showNotification('Usa almeno 2 valori nella chiave per maggiore sicurezza', 'warning');
}
```

---

## Debug e Risoluzione dei Problemi

### Il pattern `(% 26 + 26) % 26`

Se modifichi la logica di spostamento e inizi a vedere caratteri che saltano fuori da `[a-z]`/`[A-Z]`, la causa probabile è un valore intermedio negativo. In PHP, `-3 % 26 === -3`, non `23`. Il pattern del doppio modulo è la correzione corretta; non semplificarlo.

### Desincronizzazione dell'indice della chiave tra cifratura e decifratura

Poiché `$indice_chiave` si incrementa solo per i caratteri alfabetici, cifratura e decifratura devono entrambe applicare la stessa regola di filtraggio. Se mai cambi quali caratteri vengono "saltati", applica quel cambiamento simmetricamente in entrambe le direzioni, altrimenti un messaggio cifrato con una versione non verrà decifrato correttamente con un'altra.

### Il caricamento file legge `.doc`/`.docx` come dati binari illeggibili

L'input file accetta `.doc` e `.docx` nel suo attributo `accept`, ma il lettore JavaScript usa `readAsText()`, che restituirà output incomprensibile per i formati Office binari. In pratica, solo i file `.txt` funzionano correttamente. Le voci `.doc`/`.docx` nella lista di accettazione sono aspirazionali, una vera implementazione avrebbe bisogno di un parser lato server (es. `php-docx-parser`) o di una libreria lato client.

### Quota `localStorage` superata

I browser in genere consentono 5–10 MB per origine. Messaggi lunghi salvati ripetutamente possono raggiungere questo limite. Il codice attuale non gestisce `localStorage.setItem()` che lancia un `QuotaExceededError`. Una correzione difensiva:

```javascript
try {
    localStorage.setItem('zethMessages', JSON.stringify(messages));
} catch (e) {
    showNotification('Archivio pieno: esporta e cancella alcuni messaggi', 'error');
}
```

### Logica modale di importazione: unione vs. sostituzione

La funzione `importaMessaggi()` ha un flusso sottile: chiama `showModal()` con una callback, poi sovrascrive immediatamente i pulsanti del modale tramite `querySelector('.modal-buttons').innerHTML`. Questo funziona, ma accoppia la funzione di importazione alla struttura DOM interna del modale. Se il markup del modale viene refactorizzato, questa manipolazione DOM secondaria si romperà silenziosamente. Un approccio più sicuro è passare le etichette dei pulsanti e le callback come parametri a `showModal()` stesso.

---

## Limitazioni e Miglioramenti Futuri

| Limitazione | Note |
|---|---|
| Il cifrario non è crittograficamente sicuro | Uno spostamento di Cesare ciclico a interi è un cifrario a sostituzione classico. Può essere violato con l'analisi delle frequenze dato sufficiente testo cifrato. Per uso in gioco: accettabile. Per segreti reali: usare AES. |
| Il caricamento `.doc`/`.docx` non è funzionale | Solo i file `.txt` vengono letti correttamente tramite `FileReader.readAsText()`. |
| Nessuna validazione della chiave oltre "è un numero" | Una chiave `0,0,0` non produce alcuna cifratura. Nessun avviso viene mostrato. |
| Quota `localStorage` non gestita | Archivi grandi genereranno un'eccezione non gestita al salvataggio. |
| Nessun meccanismo di scambio chiavi | Entrambe le parti devono concordare la chiave fuori banda. Nel contesto di gioco questo è una caratteristica, non un bug. |
| L'archivio è locale al browser | Condividere l'archivio tra dispositivi richiede esportazione/importazione manuale in JSON. |
| Nessun header Content Security Policy | Un header CSP rafforzerebbe ulteriormente la pagina contro XSS in aggiunta agli header esistenti. |
| `pdf.js` è caricato ma non utilizzato | `cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js` è incluso nel `<head>` ma nessuna funzionalità PDF è implementata. Questo è peso morto ad ogni caricamento della pagina (~300 KB). |

---

## Struttura del Progetto

```
.
├── index.php               # Intera applicazione (PHP + HTML + CSS + JS)
├── favicon.ico
├── favicon-16x16.png
├── favicon-32x32.png
├── android-chrome-192x192.png
├── android-chrome-512x512.png
├── apple-touch-icon.png
└── site.webmanifest
```

---

*Creato il: 19 gennaio 2025, Progetto personale per uso in gioco di ruolo.*
