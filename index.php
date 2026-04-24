<?php
// Configurazione errori (disabilitare in produzione)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Header di sicurezza
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Funzione helper per sanitizzare l'output
function sanitize_output($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Funzione per cifrare/decifrare (esclude gli a capo dal conteggio)
function cifra_decifra($testo, $spostamenti, $operazione) {
    if (empty($testo) || empty($spostamenti)) {
        return ['success' => false, 'error' => 'Dati mancanti'];
    }
    
    if (!in_array($operazione, ['cifra', 'decifra'], true)) {
        return ['success' => false, 'error' => 'Operazione non valida'];
    }
    
    $risultato = "";
    $lunghezza_chiave = count($spostamenti);
    $lunghezza_testo = mb_strlen($testo);
    $indice_chiave = 0;
    
    for ($i = 0; $i < $lunghezza_testo; $i++) {
        $carattere = mb_substr($testo, $i, 1);
        
        if ($carattere === "\n" || $carattere === "\r") {
            $risultato .= $carattere;
            continue;
        }
        
        if (ctype_alpha($carattere)) {
            $offset = $spostamenti[$indice_chiave % $lunghezza_chiave];
            if ($operazione === 'decifra') {
                $offset = -$offset;
            }
            
            $is_upper = ctype_upper($carattere);
            $base = $is_upper ? ord('A') : ord('a');
            $pos = ord($carattere) - $base;
            $new_pos = (($pos + $offset) % 26 + 26) % 26;
            $carattere = chr($base + $new_pos);
            
            $indice_chiave++;
        }
        
        $risultato .= $carattere;
    }
    
    return ['success' => true, 'risultato' => $risultato];
}

// Elaborazione POST
$risultato = "";
$messaggio_errore = "";
$dati_form = [
    'spostamenti' => '',
    'testo' => '',
    'operazione' => 'cifra'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testo = $_POST['testo'] ?? "";
    $spostamenti_raw = trim($_POST['spostamenti'] ?? "");
    $operazione = $_POST['operazione'] ?? "";
    
    $dati_form = [
        'spostamenti' => $spostamenti_raw,
        'testo' => $testo,
        'operazione' => $operazione
    ];
    
    $spostamenti = array_filter(
        array_map('intval', explode(',', $spostamenti_raw)),
        function($val) { return $val !== 0 || trim($val) === '0'; }
    );
    
    if (!empty($testo) && !empty($spostamenti)) {
        $esito = cifra_decifra($testo, $spostamenti, $operazione);
        
        if ($esito['success']) {
            $risultato = $esito['risultato'];
        } else {
            $messaggio_errore = $esito['error'];
        }
    } else {
        $messaggio_errore = "Compila tutti i campi obbligatori.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema crittografico avanzato per cifrare e decifrare messaggi">
    <title>Sistema Crittografico di Zeth</title>
    <link rel="apple-touch-icon" sizes="180x180" href="https://zeth.altervista.org/critto/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="https://zeth.altervista.org/critto/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://zeth.altervista.org/critto/favicon-16x16.png">
    <link rel="manifest" href="https://zeth.altervista.org/critto/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Bookman Old Style', serif;
            color: #d1d1e0;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        #main-wrapper {
            display: flex;
            gap: 30px;
            width: 100%;
            max-width: 1400px;
            align-items: flex-start;
            justify-content: center;
        }

        #container {
            flex: 1;
            max-width: 650px;
            padding: 40px;
            text-align: center;
            border: 2px solid #444;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.8), 0 0 60px rgba(107, 107, 255, 0.2);
            background: rgba(22, 33, 62, 0.98);
            backdrop-filter: blur(15px);
        }

        #saved-panel {
            width: 450px;
            padding: 25px;
            border: 2px solid #444;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.8), 0 0 60px rgba(107, 107, 255, 0.2);
            background: rgba(22, 33, 62, 0.98);
            backdrop-filter: blur(15px);
            max-height: 85vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        #saved-panel h2 {
            margin-top: 0;
            font-size: 1.4em;
            text-align: center;
        }

        .search-container {
            margin-bottom: 15px;
        }

        .search-container input {
            width: 100%;
            padding: 10px;
            border: 2px solid #333;
            border-radius: 8px;
            background: rgba(15, 52, 96, 0.8);
            color: #fff;
            font-size: 0.95em;
        }

        .saved-item {
            background: rgba(15, 52, 96, 0.8);
            padding: 18px;
            margin: 10px 0;
            border-radius: 10px;
            border: 2px solid #444;
            position: relative;
            transition: all 0.3s ease;
        }

        .saved-item:hover {
            border-color: #6b6bff;
            box-shadow: 0 0 15px rgba(107, 107, 255, 0.3);
        }

        .saved-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .saved-item-date {
            font-size: 0.85em;
            color: #999;
        }

        .saved-item-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .saved-item-actions button {
            padding: 6px 12px;
            font-size: 0.85em;
            min-width: auto;
        }

        .saved-item-text {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            max-height: 100px;
            overflow-y: auto;
            word-wrap: break-word;
            white-space: pre-wrap;
            margin: 5px 0;
            padding: 8px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 5px;
        }

        .saved-item-label {
            font-weight: bold;
            color: #e0e0ff;
            font-size: 0.9em;
            margin-top: 8px;
        }

        h1, h2 {
            color: #e0e0ff;
            text-shadow: 0 0 20px #6b6bff, 0 0 40px rgba(107, 107, 255, 0.5);
            margin-top: 0;
        }

        h1 {
            font-size: 2.2em;
            margin-bottom: 35px;
        }

        h2 {
            font-size: 1.5em;
            margin: 20px 0 10px;
        }

        label {
            display: block;
            text-align: left;
            margin: 18px 0 8px;
            color: #e0e0ff;
            font-weight: bold;
            font-size: 1.05em;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            margin: 5px 0 15px;
            padding: 14px;
            border: 2px solid #444;
            border-radius: 10px;
            background: rgba(15, 52, 96, 0.8);
            color: #fff;
            font-size: 1em;
            font-family: 'Bookman Old Style', serif;
            box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
            outline: none;
            transition: all 0.3s ease;
        }

        input[type="file"] {
            cursor: pointer;
            padding: 12px;
        }

        input::placeholder,
        textarea::placeholder {
            color: #888;
        }

        input:focus,
        textarea:focus,
        select:focus {
            box-shadow: 0 0 20px rgba(107, 107, 255, 0.5);
            border-color: #6b6bff;
        }

        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 25px 0;
        }

        button {
            flex: 1;
            min-width: 140px;
            padding: 14px 22px;
            background: rgba(15, 52, 96, 0.8);
            color: #e0e0ff;
            border: 2px solid #444;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1em;
            font-family: 'Bookman Old Style', serif;
            transition: all 0.3s ease;
            text-shadow: 0 0 10px #6b6bff;
        }

        button:hover:not(:disabled) {
            background: rgba(31, 64, 104, 0.9);
            color: #ffffff;
            box-shadow: 0 0 20px rgba(107, 107, 255, 0.5);
            border-color: #6b6bff;
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .file-upload-section {
            background: rgba(15, 52, 96, 0.6);
            padding: 25px;
            border-radius: 10px;
            border: 2px solid #444;
            margin: 25px 0;
        }

        .file-info {
            font-size: 0.9em;
            color: #999;
            margin-top: 8px;
            text-align: left;
        }

        .error-message {
            background: rgba(139, 0, 0, 0.3);
            border: 2px solid #8B0000;
            color: #ff6b6b;
            padding: 18px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: center;
        }

        .result-container {
            padding: 25px 0;
            position: relative;
            margin: 25px 0;
        }

        .result-text {
            background: rgba(15, 52, 96, 0.8);
            padding: 18px;
            border-radius: 10px;
            text-align: left;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            color: #fff;
            border: 2px solid #444;
            box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
            max-height: 220px;
            overflow-y: auto;
            overflow-x: hidden;
            margin-bottom: 15px;
        }

        .result-text::-webkit-scrollbar,
        .saved-item-text::-webkit-scrollbar,
        #saved-panel::-webkit-scrollbar {
            width: 10px;
        }

        .result-text::-webkit-scrollbar-track,
        .saved-item-text::-webkit-scrollbar-track,
        #saved-panel::-webkit-scrollbar-track {
            background: rgba(15, 52, 96, 0.8);
            border-radius: 5px;
        }

        .result-text::-webkit-scrollbar-thumb,
        .saved-item-text::-webkit-scrollbar-thumb,
        #saved-panel::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 5px;
        }

        .result-text::-webkit-scrollbar-thumb:hover,
        .saved-item-text::-webkit-scrollbar-thumb:hover,
        #saved-panel::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .copy-btn {
            padding: 10px 18px;
            background: rgba(15, 52, 96, 0.8);
            border: 2px solid #444;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-shadow: 0 0 10px #6b6bff;
            margin: 5px;
        }

        .copy-btn:hover {
            background: rgba(31, 64, 104, 0.9);
            color: #6b6bff;
            border-color: #6b6bff;
            transform: translateY(-2px);
        }

        .copy-btn .success {
            display: none;
        }

        .copy-btn.success-active .default {
            display: none;
        }

        .copy-btn.success-active .success {
            display: inline;
        }

        #clock {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(26, 26, 46, 0.95);
            color: #d1d1e0;
            padding: 14px 18px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.6);
            border: 2px solid #444;
        }

        #notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification {
            background: rgba(22, 33, 62, 0.98);
            border: 2px solid #444;
            border-radius: 10px;
            padding: 18px 22px;
            min-width: 320px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.8);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification.success {
            border-color: #4CAF50;
            color: #4CAF50;
        }

        .notification.error {
            border-color: #f44336;
            color: #ff6b6b;
        }

        .notification.warning {
            border-color: #ff9800;
            color: #ffb74d;
        }

        .notification.info {
            border-color: #2196F3;
            color: #64b5f6;
        }

        .notification-icon {
            font-size: 1.5em;
        }

        .notification-message {
            flex: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .notification.removing {
            animation: slideOut 0.3s ease-in;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: rgba(22, 33, 62, 0.98);
            border: 2px solid #444;
            border-radius: 15px;
            padding: 35px;
            max-width: 450px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.9);
        }

        .modal-content h3 {
            margin-top: 0;
            color: #e0e0ff;
            text-shadow: 0 0 20px #6b6bff;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .modal-buttons button {
            flex: 1;
            min-width: auto;
        }

        @media (max-width: 1200px) {
            #main-wrapper {
                flex-direction: column;
                align-items: center;
            }

            #saved-panel {
                width: 100%;
                max-width: 650px;
                max-height: 500px;
            }
        }

        @media (max-width: 600px) {
            #container {
                padding: 25px;
            }

            h1 {
                font-size: 1.6em;
            }

            .button-group {
                flex-direction: column;
            }

            button {
                width: 100%;
            }

            #clock {
                font-size: 12px;
                padding: 10px 14px;
            }

            .notification {
                min-width: 280px;
            }

            #notification-container {
                right: 10px;
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <div id="main-wrapper">
        <div id="container">
            <h1>Sistema Crittografico di Zeth</h1>
            
            <form action="" method="post" id="cryptoForm">
                <label for="spostamenti">Chiave di Cifratura:</label>
                <input 
                    type="text" 
                    id="spostamenti" 
                    name="spostamenti" 
                    placeholder="Esempio: 1, -2, 3, -4, 5..." 
                    required 
                    value="<?= sanitize_output($dati_form['spostamenti']) ?>"
                    aria-label="Chiave di cifratura (numeri separati da virgola)"
                >

                <div class="file-upload-section">
                    <h2 style="font-size: 1.2em; margin-top: 0;">Carica File di Testo</h2>
                    <label for="text_file">File (.txt, .doc, .docx):</label>
                    <input 
                        type="file" 
                        id="text_file" 
                        accept=".txt,.doc,.docx,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        aria-label="Carica file di testo"
                    >
                    <div class="file-info">
                        <i class="fas fa-info-circle"></i> Carica un file di testo per cifrarlo/decifrarlo automaticamente
                    </div>
                </div>
                
                <label for="testo">Testo Manuale:</label>
                <textarea 
                    id="testo" 
                    name="testo" 
                    rows="6" 
                    placeholder="Oppure scrivi il testo qui..." 
                    aria-label="Testo da cifrare o decifrare"
                ><?= sanitize_output($dati_form['testo']) ?></textarea>
                
                <label for="operazione">Operazione:</label>
                <select id="operazione" name="operazione" required aria-label="Scegli operazione">
                    <option value="cifra" <?= $dati_form['operazione'] == 'cifra' ? 'selected' : '' ?>>Cifra</option>
                    <option value="decifra" <?= $dati_form['operazione'] == 'decifra' ? 'selected' : '' ?>>Decifra</option>
                </select>
                
                <div class="button-group">
                    <button type="submit" aria-label="Esegui operazione">
                        <i class="fas fa-play"></i> Esegui
                    </button>
                    <button type="button" onclick="nuovaOperazione()" aria-label="Nuova operazione">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>

            <?php if ($messaggio_errore): ?>
            <div class="error-message" role="alert">
                <strong>Errore:</strong> <?= sanitize_output($messaggio_errore) ?>
            </div>
            <?php endif; ?>

            <?php if ($risultato): ?>
            <div class="result-container">
                <strong>Risultato:</strong>
                <div class="result-text" id="result-text"><?= sanitize_output($risultato) ?></div>
                <button class="copy-btn" onclick="copiaTesto()" aria-label="Copia risultato">
                    <span class="default"><i class="fas fa-clipboard"></i> Copia</span>
                    <span class="success"><i class="fas fa-check"></i> Copiato!</span>
                </button>
                <button class="copy-btn" onclick="salvaMessaggio()" aria-label="Salva messaggio">
                    <i class="fas fa-save"></i> Salva
                </button>
                <button class="copy-btn" onclick="scaricaFile()" aria-label="Scarica come file">
                    <i class="fas fa-download"></i> Scarica File
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div id="saved-panel">
            <h2><i class="fas fa-archive"></i> Archivio Messaggi</h2>
            
            <div class="search-container">
                <input 
                    type="text" 
                    id="search-input" 
                    placeholder="🔍 Cerca nei messaggi salvati..."
                    onkeyup="filtraMessaggi()"
                    aria-label="Cerca messaggi"
                >
            </div>
            
            <div class="button-group">
                <button onclick="document.getElementById('importFile').click()">
                    <i class="fas fa-file-import"></i> Importa
                </button>
                <button onclick="esportaMessaggi()">
                    <i class="fas fa-file-export"></i> Esporta
                </button>
                <button onclick="cancellaArchivio()">
                    <i class="fas fa-trash"></i> Cancella
                </button>
            </div>
            <input type="file" id="importFile" accept=".json" style="display: none;" onchange="importaMessaggi(event)">
            <div id="saved-messages-container"></div>
        </div>
    </div>

    <div id="clock" aria-live="polite" aria-atomic="true"></div>
    <div id="notification-container"></div>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <h3 id="modalTitle">Conferma</h3>
            <p id="modalMessage">Sei sicuro?</p>
            <div class="modal-buttons">
                <button onclick="closeModal(false)">Annulla</button>
                <button onclick="closeModal(true)">Conferma</button>
            </div>
        </div>
    </div>
 
    <script>
        'use strict';

        // Sistema di notifiche
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            else if (type === 'error') icon = 'fa-exclamation-circle';
            else if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            notification.innerHTML = `
                <div class="notification-icon"><i class="fas ${icon}"></i></div>
                <div class="notification-message">${message}</div>
            `;
            
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('removing');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Sistema modale
        let modalCallback = null;

        function showModal(title, message, callback) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').textContent = message;
            modalCallback = callback;
            modal.classList.add('active');
        }

        function closeModal(confirmed) {
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            if (modalCallback) {
                modalCallback(confirmed);
                modalCallback = null;
            }
        }

        // Gestione caricamento file
        document.getElementById('text_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(event) {
                const content = event.target.result;
                document.getElementById('testo').value = content;
                showNotification(`File "${file.name}" caricato con successo`, 'success');
            };
            
            reader.onerror = function() {
                showNotification('Errore durante la lettura del file', 'error');
            };
            
            reader.readAsText(file);
        });

        // Scarica risultato come file
        function scaricaFile() {
            const risultato = document.getElementById('result-text').textContent;
            const chiave = document.getElementById('spostamenti').value;
            const operazione = document.getElementById('operazione').value;
            
            if (!risultato) {
                showNotification('Nessun risultato da scaricare', 'warning');
                return;
            }
            
            const contenuto = `=== Sistema Crittografico di Zeth ===\n\n` +
                            `Operazione: ${operazione === 'cifra' ? 'Cifratura' : 'Decifratura'}\n` +
                            `Chiave: ${chiave}\n` +
                            `Data: ${new Date().toLocaleString('it-IT')}\n\n` +
                            `--- RISULTATO ---\n\n${risultato}`;
            
            const blob = new Blob([contenuto], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `zeth-${operazione}-${new Date().toISOString().split('T')[0]}.txt`;
            link.click();
            URL.revokeObjectURL(url);
            
            showNotification('File scaricato con successo', 'success');
        }

        // Gestione localStorage
        function getSavedMessages() {
            const saved = localStorage.getItem('zethMessages');
            return saved ? JSON.parse(saved) : [];
        }

        function saveMessage(data) {
            const messages = getSavedMessages();
            messages.unshift(data);
            localStorage.setItem('zethMessages', JSON.stringify(messages));
            updateSavedPanel();
            showNotification('Messaggio salvato con successo!', 'success');
        }

        function deleteMessage(index) {
            showModal('Elimina messaggio', 'Vuoi davvero eliminare questo messaggio?', (confirmed) => {
                if (confirmed) {
                    const messages = getSavedMessages();
                    messages.splice(index, 1);
                    localStorage.setItem('zethMessages', JSON.stringify(messages));
                    updateSavedPanel();
                    showNotification('Messaggio eliminato', 'success');
                }
            });
        }

        function loadMessage(index) {
            const messages = getSavedMessages();
            const msg = messages[index];
            
            document.getElementById('spostamenti').value = msg.chiave;
            document.getElementById('testo').value = msg.testoOriginale;
            document.getElementById('operazione').value = msg.operazione === 'cifra' ? 'decifra' : 'cifra';
            
            showNotification('Messaggio caricato', 'success');
        }

        function updateSavedPanel() {
            const container = document.getElementById('saved-messages-container');
            const messages = getSavedMessages();
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            
            const filteredMessages = messages.filter((msg, index) => {
                if (!searchTerm) return true;
                return msg.testoOriginale.toLowerCase().includes(searchTerm) ||
                       msg.risultato.toLowerCase().includes(searchTerm) ||
                       msg.chiave.toLowerCase().includes(searchTerm);
            });
            
            if (filteredMessages.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #888; padding: 20px;">Nessun messaggio trovato</p>';
                return;
            }
            
            container.innerHTML = filteredMessages.map((msg) => {
                const originalIndex = messages.indexOf(msg);
                return `
                <div class="saved-item">
                    <div class="saved-item-header">
                        <div class="saved-item-date">${new Date(msg.timestamp).toLocaleString('it-IT')}</div>
                        <div class="saved-item-actions">
                            <button onclick="loadMessage(${originalIndex})" title="Carica">
                                <i class="fas fa-upload"></i>
                            </button>
                            <button onclick="esportaSingoloMessaggio(${originalIndex})" title="Esporta">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button onclick="deleteMessage(${originalIndex})" title="Elimina">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="saved-item-label">Originale:</div>
                    <div class="saved-item-text">${escapeHtml(msg.testoOriginale.substring(0, 100))}${msg.testoOriginale.length > 100 ? '...' : ''}</div>
                    <div class="saved-item-label">${msg.operazione === 'cifra' ? 'Cifrato' : 'Decifrato'}:</div>
                    <div class="saved-item-text">${escapeHtml(msg.risultato.substring(0, 100))}${msg.risultato.length > 100 ? '...' : ''}</div>
                    <div class="saved-item-label">Chiave:</div>
                    <div class="saved-item-text">${escapeHtml(msg.chiave)}</div>
                </div>
            `}).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function filtraMessaggi() {
            updateSavedPanel();
        }

        function salvaMessaggio() {
            const testo = document.getElementById('testo').value;
            const chiave = document.getElementById('spostamenti').value;
            const operazione = document.getElementById('operazione').value;
            const risultato = document.getElementById('result-text').textContent;
            
            if (!testo || !chiave || !risultato) {
                showNotification('Nessun risultato da salvare', 'warning');
                return;
            }
            
            const data = {
                testoOriginale: testo,
                risultato: risultato,
                chiave: chiave,
                operazione: operazione,
                timestamp: new Date().toISOString()
            };
            
            saveMessage(data);
        }

        function esportaMessaggi() {
            const messages = getSavedMessages();
            
            if (messages.length === 0) {
                showNotification('Nessun messaggio da esportare', 'warning');
                return;
            }
            
            const dataStr = JSON.stringify(messages, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `zeth-messaggi-${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
            
            showNotification('Messaggi esportati con successo', 'success');
        }

        function esportaSingoloMessaggio(index) {
            const messages = getSavedMessages();
            const message = messages[index];
            
            if (!message) {
                showNotification('Messaggio non trovato', 'error');
                return;
            }
            
            const timestamp = new Date(message.timestamp).toISOString().split('T')[0];
            const preview = message.testoOriginale.substring(0, 20).replace(/[^a-zA-Z0-9]/g, '-');
            const fileName = `zeth-${message.operazione}-${preview}-${timestamp}.json`;
            
            const dataStr = JSON.stringify([message], null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            link.click();
            URL.revokeObjectURL(url);
            
            showNotification('Messaggio esportato con successo', 'success');
        }

        function importaMessaggi(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const imported = JSON.parse(e.target.result);
                    
                    if (!Array.isArray(imported)) {
                        throw new Error('Formato non valido');
                    }
                    
                    showModal('Importa messaggi', 
                        `Trovati ${imported.length} messaggi. Vuoi sostituire l'archivio attuale o aggiungere i messaggi?`,
                        (confirmed) => {
                            if (confirmed) {
                                localStorage.setItem('zethMessages', JSON.stringify(imported));
                            } else {
                                const existing = getSavedMessages();
                                const merged = [...imported, ...existing];
                                localStorage.setItem('zethMessages', JSON.stringify(merged));
                            }
                            updateSavedPanel();
                            showNotification(`${imported.length} messaggi importati`, 'success');
                        }
                    );
                    
                    const modalButtons = document.querySelector('.modal-buttons');
                    modalButtons.innerHTML = `
                        <button onclick="closeModal(false)">Aggiungi</button>
                        <button onclick="closeModal(true)">Sostituisci</button>
                    `;
                    
                } catch (error) {
                    showNotification('Errore nell\'importazione: file non valido', 'error');
                }
            };
            reader.readAsText(file);
            
            event.target.value = '';
        }

        function cancellaArchivio() {
            const messages = getSavedMessages();
            if (messages.length === 0) {
                showNotification('L\'archivio è già vuoto', 'info');
                return;
            }
            
            showModal('Cancella archivio', 
                `Vuoi davvero eliminare tutti i ${messages.length} messaggi salvati? Questa azione è irreversibile.`,
                (confirmed) => {
                    if (confirmed) {
                        localStorage.removeItem('zethMessages');
                        updateSavedPanel();
                        showNotification('Archivio cancellato', 'success');
                    }
                }
            );
        }

        function copiaTesto() {
            const resultText = document.getElementById('result-text').textContent;
            
            if (!navigator.clipboard) {
                const textArea = document.createElement('textarea');
                textArea.value = resultText;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    mostraSuccessoCopia();
                    showNotification('Testo copiato negli appunti', 'success');
                } catch (err) {
                    console.error('Errore durante la copia:', err);
                    showNotification('Impossibile copiare il testo', 'error');
                } finally {
                    document.body.removeChild(textArea);
                }
                return;
            }
            
            navigator.clipboard.writeText(resultText)
                .then(() => {
                    mostraSuccessoCopia();
                    showNotification('Testo copiato negli appunti', 'success');
                })
                .catch(err => {
                    console.error('Errore durante la copia:', err);
                    showNotification('Impossibile copiare il testo', 'error');
                });
        }

        function mostraSuccessoCopia() {
            const copyBtn = document.querySelector('.copy-btn');
            copyBtn.classList.add('success-active');
            setTimeout(() => {
                copyBtn.classList.remove('success-active');
            }, 2000);
        }

        function nuovaOperazione() {
            showModal('Reset', 'Vuoi davvero cancellare tutti i dati inseriti?', (confirmed) => {
                if (confirmed) {
                    document.getElementById('spostamenti').value = '';
                    document.getElementById('testo').value = '';
                    document.getElementById('text_file').value = '';
                    document.getElementById('operazione').value = 'cifra';

                    const resultContainer = document.querySelector('.result-container');
                    if (resultContainer) {
                        resultContainer.style.display = 'none';
                    }
                    
                    const errorMessage = document.querySelector('.error-message');
                    if (errorMessage) {
                        errorMessage.style.display = 'none';
                    }
                    
                    showNotification('Campi resettati', 'success');
                }
            });
        }

        function updateClock() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const dateTimeString = now.toLocaleString('it-IT', options);
            document.getElementById('clock').textContent = dateTimeString;
        }

        setInterval(updateClock, 1000);
        updateClock();

        document.getElementById('cryptoForm').addEventListener('submit', function(e) {
            const testo = document.getElementById('testo').value.trim();
            const spostamenti = document.getElementById('spostamenti').value.trim();
            
            if (!testo || !spostamenti) {
                e.preventDefault();
                showNotification('Compila tutti i campi obbligatori', 'warning');
                return false;
            }
            
            const numeri = spostamenti.split(',').map(n => n.trim());
            const nonValidi = numeri.filter(n => isNaN(parseInt(n)));
            
            if (nonValidi.length > 0) {
                e.preventDefault();
                showNotification('La chiave deve contenere solo numeri separati da virgole', 'error');
                return false;
            }
        });

        updateSavedPanel();
    </script>
</body>
</html>