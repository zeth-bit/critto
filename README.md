# Sistema Crittografico di Zeth

> A server-side Vigenère-style cipher tool built for an tabletop RPG, delivered as a single PHP file with a client-side message archive.

---

## Overview

This project was created in January 2025 as a **private communication tool for an online role-playing game**, in collaboration with AI. The requirement was practical and concrete: players in different factions needed a way to exchange secret messages *in-game*, where the encryption mechanism itself was part of the fiction.

The solution is a self-contained PHP page that implements a **modified Caesar cipher with a multi-value numeric key**, conceptually close to a [Vigenère cipher](https://en.wikipedia.org/wiki/Vigen%C3%A8re_cipher), paired with a client-side message archive stored in `localStorage`. No database, no user accounts, no server state beyond the PHP processing itself.

The tool is intentionally minimal in its deployment footprint: one `.php` file, one `site.webmanifest`, and a set of favicons, everything needed to host it on a shared server like Altervista.

---

## Architecture

The application follows a **single-file, server-renders-once, client-manages-state** pattern.

```
┌─────────────────────────────────────────────────────────────────┐
│                         index.php                               │
│                                                                 │
│  ┌──────────────┐    POST     ┌────────────────────────────┐   │
│  │   HTML Form  │ ──────────► │  PHP: cifra_decifra()      │   │
│  │  (key+text+  │             │  - Validates input         │   │
│  │   operation) │ ◄────────── │  - Applies shift per char  │   │
│  └──────────────┘   Re-render │  - Returns sanitized HTML  │   │
│                               └────────────────────────────┘   │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  JavaScript (client-side, no server involvement)        │    │
│  │  - File reader (.txt → textarea)                        │    │
│  │  - localStorage CRUD (save / search / export / import) │    │
│  │  - Clipboard API with legacy fallback                  │    │
│  │  - Live clock (setInterval)                            │    │
│  │  - Modal confirmation system                           │    │
│  │  - Toast notification system                           │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

**Data flow:**

1. User fills in key (e.g. `3,-1,5`), text, and operation (encrypt/decrypt).
2. The form POSTs to itself; PHP processes and re-renders the page with the result injected into the HTML.
3. From the rendered result, the user can copy, download as `.txt`, or save to the client-side archive via JavaScript.
4. The archive lives entirely in `localStorage` under the key `zethMessages`, no round-trip to the server.

**Why this separation?** The cipher itself is stateless and deterministic, so server-side PHP is sufficient and keeps the logic auditable. The archive, however, benefits from staying on the client: no login system is needed, data is naturally scoped to the individual browser, and the hosting environment (a free shared host) imposes no database overhead.

---

## Key Design Decisions

### 1. Vigenère-style key over a fixed Caesar shift

A classic Caesar cipher uses a single integer shift applied uniformly to every letter. This project extends it to a **comma-separated list of integers** (e.g. `3,-2,7`), cycling through the key values character by character, exactly the structure of a Vigenère cipher, but with arbitrary integer offsets instead of alphabet-based keywords.

This was a deliberate complexity increase motivated by game context: a single-number key is trivially brute-forced; a cycling multi-value key requires knowing both the key length and its values, which is enough friction for a casual in-game secret.

Negative offsets are supported, which means the same key format can produce a wider spread of ciphertext even for short messages.

**Trade-off:** This is still a classical substitution cipher and offers no real cryptographic security. For game purposes that's acceptable, the goal is plausibility and fun, not confidentiality against a determined attacker.

### 2. Key index advances only on alphabetic characters

```php
if (ctype_alpha($carattere)) {
    $offset = $spostamenti[$indice_chiave % $lunghezza_chiave];
    // ...
    $indice_chiave++;
}
```

Spaces, punctuation, digits, and newlines pass through unmodified and **do not consume a key position**. This is a meaningful design choice: it means the ciphertext preserves the word structure of the plaintext (spaces remain spaces), which is a deliberate legibility trade-off. In a game context, this makes the output feel more like a "coded message" and less like random noise, while also making decryption slightly more predictable.

Newlines are explicitly preserved (`"\n"` and `"\r"` are passed through), so multi-paragraph messages encrypt and decrypt with their formatting intact.

### 3. Modular arithmetic with positive normalization

```php
$new_pos = (($pos + $offset) % 26 + 26) % 26;
```

The `+ 26) % 26` pattern ensures negative offsets (from decryption or negative key values) never produce a negative index, which `%` alone would in PHP. This is a standard fix for modular arithmetic on negative numbers that PHP does not handle the same way as Python's `%`.

### 4. All output is sanitized server-side

```php
function sanitize_output($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
```

Every user-supplied value that is echoed into HTML goes through `sanitize_output()`. This prevents stored or reflected XSS, which matters even in a private tool, especially since messages may contain angle brackets or quotes if someone pastes code or HTML into the text field.

The client-side archive uses its own equivalent:

```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
```

Using a throwaway DOM node to escape is a reliable browser-native approach that avoids regex-based sanitization pitfalls.

### 5. localStorage as the persistence layer

The archive stores an array of message objects under `zethMessages`. Each entry records: original text, result, key, operation, and ISO timestamp. This means:

- No server database or login required.
- Data is scoped to the specific browser profile, appropriate for personal use.
- Import/export as JSON gives portability across devices without any backend.

**Trade-off:** `localStorage` is not encrypted at rest. Anyone with physical access to the browser can read the archive. For a game tool this is an acceptable limitation.

### 6. Security headers

```php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
```

These are set even for a small personal project, a good habit that prevents MIME sniffing, clickjacking via iframes, and activates the browser's built-in XSS filter in older browsers.

---

## Code Walkthrough

The file is structured in this reading order:

| Lines | Content |
|-------|---------|
| 1–15 | PHP configuration: error reporting (disabled for output), security headers, `sanitize_output()` helper |
| 18–59 | Core cipher function `cifra_decifra()`: input validation, character-by-character loop, modular shift |
| 61–97 | POST handler: parse and validate the form submission, call the cipher, populate `$risultato` or `$messaggio_errore` |
| 99–627 | HTML + CSS: page structure, two-panel layout (main form + archive sidebar), responsive media queries |
| 628–736 | HTML body: form markup, conditional result block, archive panel, modal overlay, clock widget |
| 752–1171 | JavaScript: notification system, modal system, file reader, localStorage CRUD, clipboard handling, form validation, live clock |

**Start with `cifra_decifra()`**, it is the heart of the application and can be understood in isolation. Everything else is input/output scaffolding around it.

---

## Modification Guide

### Changing the cipher algorithm

All logic is in `cifra_decifra()` (lines 18–59). To swap in a different algorithm (e.g. a true Vigenère using letter-based keys), replace only the inner block that computes `$new_pos`. The function contract, accepting `$testo`, `$spostamenti` (array of ints), `$operazione`, can remain unchanged.

### Adding support for non-Latin characters

Currently, only ASCII `[a-z]` and `[A-Z]` are shifted. The code uses `ctype_alpha()` which is locale-sensitive but defaults to ASCII range. To support accented characters (e.g. Italian `à`, `è`):

1. Replace `ctype_alpha()` with a `preg_match('/^\pL$/u', $carattere)` check.
2. Replace `ord()`/`chr()` with `mb_ord()`/`mb_chr()` (available in PHP 7.2+).
3. Redefine the alphabet bounds, this is the hard part, as Italian adds characters outside the 26-letter range.

### Extending the archive schema

The message object saved to `localStorage` is constructed in `salvaMessaggio()` (line 938). Add new fields there and update the `updateSavedPanel()` rendering function to display them. The import/export format is plain JSON, so schema changes are backwards-compatible as long as you handle missing fields with `??` fallbacks.

### Making the key visually validated client-side

The current client-side validation (line 1160) checks that all comma-separated values are parseable integers but does not enforce a minimum key length. You could add:

```javascript
if (numeri.length < 2) {
    e.preventDefault();
    showNotification('Usa almeno 2 valori nella chiave per maggiore sicurezza', 'warning');
}
```

---

## Debugging & Problem Solving

### The `(% 26 + 26) % 26` pattern

If you modify the shift logic and start seeing characters jumping outside `[a-z]`/`[A-Z]`, the likely cause is a negative intermediate value. In PHP, `-3 % 26 === -3`, not `23`. The double-modulo pattern is the correct fix; do not simplify it.

### Key index desync between encrypt and decrypt

Because `$indice_chiave` only increments for alphabetic characters, encrypt and decrypt must both apply the same filtering rule. If you ever change which characters are "skipped," apply that change symmetrically in both directions, otherwise a message encrypted with one version will not decrypt correctly with another.

### File upload reads `.doc`/`.docx` as binary garbage

The file input accepts `.doc` and `.docx` in its `accept` attribute, but the JavaScript reader uses `readAsText()`, which will return garbled output for binary Office formats. Only `.txt` files work correctly in practice. The `.doc`/`.docx` entries in the accept list are aspirational, a real implementation would need a server-side parser (e.g. `php-docx-parser`) or a client-side library.

### `localStorage` quota exceeded

Browsers typically allow 5–10 MB per origin. Long messages saved repeatedly can hit this limit. The current code does not handle `localStorage.setItem()` throwing a `QuotaExceededError`. A defensive fix:

```javascript
try {
    localStorage.setItem('zethMessages', JSON.stringify(messages));
} catch (e) {
    showNotification('Archivio pieno: esporta e cancella alcuni messaggi', 'error');
}
```

### Import merge vs. replace modal logic

The `importaMessaggi()` function has a subtle flow: it calls `showModal()` with a callback, then immediately overwrites the modal's buttons via `querySelector('.modal-buttons').innerHTML`. This works, but it couples the import function to the internal DOM structure of the modal. If the modal markup is refactored, this secondary DOM manipulation will silently break. A safer approach is to pass button labels and callbacks as parameters to `showModal()` itself.

---

## Limitations & Future Improvements

| Limitation | Notes |
|---|---|
| Cipher is not cryptographically secure | A cycling integer Caesar shift is a classical substitution cipher. It can be broken with frequency analysis given sufficient ciphertext. For game use: acceptable. For real secrets: use AES. |
| `.doc`/`.docx` upload is non-functional | Only `.txt` files read correctly via `FileReader.readAsText()`. |
| No key validation beyond "is it a number" | A key of `0,0,0` produces no encryption. No warning is shown. |
| `localStorage` quota not handled | Large archives will throw an unhandled exception on save. |
| No key exchange mechanism | Both parties need to agree on the key out-of-band. For the game context this is a feature, not a bug. |
| Archive is browser-local | Sharing the archive between devices requires manual JSON export/import. |
| No Content Security Policy header | A CSP header would further harden the page against XSS alongside the existing headers. |
| `pdf.js` is loaded but unused | `cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js` is included in `<head>` but no PDF functionality is implemented. This is dead weight on every page load (~300 KB). |

---

## Project Structure

```
.
├── index.php               # Entire application (PHP + HTML + CSS + JS)
├── favicon.ico
├── favicon-16x16.png
├── favicon-32x32.png
├── android-chrome-192x192.png
├── android-chrome-512x512.png
├── apple-touch-icon.png
└── site.webmanifest
```

---

*Created: January 19, 2025, Personal project for tabletop RPG use.*
