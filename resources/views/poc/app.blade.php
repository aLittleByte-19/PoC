<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>NEXUM | PoC</title>
  <link rel="stylesheet" href="{{ asset('nexum-app/styles.css') }}?v={{ filemtime(public_path('nexum-app/styles.css')) }}">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand-block">
        <img class="brand-logo" src="{{ asset('nexum-app/eggon_logo_43542.png') }}" alt="Eggon logo">
      </div>

      <nav class="side-nav" aria-label="Navigazione PoC">
        <div class="nav-section">
          <p class="nav-section-title">Panoramica</p>
          <button class="nav-item active" data-view="overview" data-target="overview-status">Overview</button>
          <button class="nav-subitem" data-view="overview" data-target="overview-modules">Moduli</button>
        </div>

        <div class="nav-section">
          <p class="nav-section-title">AI Assistant</p>
          <button class="nav-item" data-view="assistant" data-target="assistant-compose">Assistant</button>
          <button class="nav-subitem" data-view="assistant" data-target="assistant-compose">Generazione</button>
        </div>

        <div class="nav-section">
          <p class="nav-section-title">Co-Pilot CdL</p>
          <button class="nav-item" data-view="copilot" data-target="copilot-upload">Co-Pilot</button>
          <button class="nav-subitem" data-view="copilot" data-target="copilot-upload">Caricamento</button>
          <button class="nav-subitem" data-view="copilot" data-target="copilot-results">Storico invii</button>
        </div>
      </nav>
    </aside>

    <div class="workspace">
      <header class="topbar" id="workspace-top">
        <div>
          <p class="eyebrow">NEXUM PoC</p>
          <h2 id="view-title">Overview operativa</h2>
        </div>
        <div class="session-actions">
          <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Attiva tema scuro" aria-pressed="false">
            <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
            <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
          </button>
        </div>
      </header>

      <main class="view-stack">
        <section class="view active" data-view="overview">
          <article class="hero-card" id="overview-status">
            <div>
              <p class="eyebrow">Panoramica</p>
              <h3>PoC focalizzata su generazione contenuti e analisi documentale.</h3>
              <p>
                L'applicativo espone solo le funzioni necessarie alla dimostrazione: produzione di una bozza tramite AI Assistant e caricamento PDF con rilevazione campi, split iniziale e preview documento.
              </p>
            </div>

            <ul class="status-list">
              <li><strong>2</strong><span>Moduli PoC</span></li>
              <li><strong>0</strong><span>Funzioni autenticazione</span></li>
              <li><strong>1</strong><span>Interfaccia pubblica</span></li>
            </ul>
          </article>

          <article class="panel" id="overview-modules">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">Moduli</p>
                <h3>Funzioni mantenute nel perimetro</h3>
              </div>
            </div>

            <ul class="flow-list">
              <li>
                <strong>AI Assistant - Generazione</strong>
                <span>Prompt, tono e stile producono una bozza con titolo e testo revisionabili.</span>
              </li>
              <li>
                <strong>Co-Pilot CdL - Caricamento e storico invii</strong>
                <span>Upload PDF, split iniziale, campi OCR rilevati, anteprima dello split e preview del documento.</span>
              </li>
            </ul>
          </article>
        </section>

        <section class="view" data-view="assistant">
          <article class="panel flow-step" id="assistant-compose">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">AI Assistant</p>
                <h3>Genera una bozza</h3>
              </div>
            </div>

            <p class="panel-note">
              Inserisci il contenuto da generare e scegli solo tono e stile. La PoC produce titolo e testo revisionabili.
            </p>

            <div class="field-stack">
              <label class="field">
                <span>Prompt</span>
                <textarea id="prompt-input" rows="6" placeholder="Descrivi il contenuto da generare"></textarea>
              </label>

              <div class="form-grid">
                <label class="field">
                  <span>Tono</span>
                  <select id="tone-select">
                    <option>Chiaro e diretto</option>
                    <option>Più istituzionale</option>
                    <option>Più sintetico</option>
                    <option>Empatico</option>
                    <option>Tecnico</option>
                  </select>
                </label>

                <label class="field">
                  <span>Stile</span>
                  <select id="style-select">
                    <option>Testo informativo</option>
                    <option>Avviso operativo</option>
                    <option>Aggiornamento breve</option>
                  </select>
                </label>
              </div>
            </div>

            <div class="button-row">
              <button class="primary-button" id="generate-button" type="button">Genera bozza</button>
            </div>

            <p class="status-message" id="assistant-compose-note">La bozza comparirà qui sotto.</p>
          </article>

          <article class="panel flow-step locked" id="assistant-result" aria-disabled="true">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">Risultato</p>
                <h3>Bozza generata</h3>
              </div>
            </div>

            <div class="editor-stack">
              <label class="field">
                <span>Titolo</span>
                <input id="generated-title-input" type="text" value="" placeholder="Non disponibile">
              </label>

              <label class="field">
                <span>Testo</span>
                <textarea id="generated-body-input" rows="10" placeholder="Non disponibile"></textarea>
              </label>

              <div class="meta-row">
                <span id="meta-chars">0 caratteri</span>
                <span id="meta-time">0 min lettura</span>
                <span id="assistant-status">In attesa</span>
              </div>
            </div>
          </article>
        </section>

        <section class="view" data-view="copilot">
          <article class="panel flow-step" id="copilot-upload">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">Co-Pilot CdL</p>
                <h3>Rileva campi e split iniziale</h3>
              </div>
            </div>

            <p class="panel-note">
              Carica un PDF. La PoC salva il documento, prova lo split per destinatario e mostra i campi rilevati dall'OCR/AI.
            </p>

            <button class="upload-box" id="upload-box" type="button">
              <strong>Seleziona un PDF</strong>
              <span>Il sistema avvia automaticamente split iniziale ed estrazione campi.</span>
            </button>
            <input class="hidden" id="document-file-input" type="file" accept="application/pdf">

            <ul class="compact-list">
              <li><strong>Stato</strong><span id="upload-state">In attesa di caricamento</span></li>
              <li><strong>Output</strong><span id="upload-output">I risultati compariranno nella sezione sottostante.</span></li>
            </ul>
          </article>

          <article class="panel" id="copilot-results">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">Risultati</p>
                <h3>Documenti rilevati</h3>
              </div>
            </div>

            <ul class="status-list compact-kpi-list" id="document-summary-list">
              <li><strong>0</strong><span>Sotto-documenti</span></li>
              <li><strong>0</strong><span>Campi con confidenza</span></li>
              <li><strong>0</strong><span>Da verificare</span></li>
            </ul>

            <ul class="history-list document-history" id="document-history"></ul>
            <p class="empty-note" id="document-empty">Nessun documento analizzato.</p>
          </article>

          <article class="panel is-hidden" id="copilot-detail">
            <div class="panel-heading">
              <div>
                <p class="eyebrow">Dettaglio</p>
                <h3 id="detail-title">Sotto-documento selezionato</h3>
              </div>
            </div>

            <div class="document-detail-grid">
              <div class="document-preview">
                <p class="eyebrow">Split iniziale</p>
                <strong id="detail-preview-title">Documento</strong>
                <span id="detail-preview-meta">Nome file e pagine</span>
                <div class="document-preview-lines" id="detail-preview-lines"></div>
                <div class="document-preview-frame" id="detail-preview-frame">
                  <span>Preview documento non disponibile.</span>
                </div>
              </div>

              <div class="extracted-card document-inspector">
                <div class="inspector-heading">
                  <p class="section-label">Campi rilevati</p>
                </div>

                <div class="form-grid">
                  <label class="field">
                    <span>Nome e cognome</span>
                    <input id="detail-employee-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Azienda</span>
                    <input id="detail-company-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Nome file</span>
                    <input id="detail-file-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Data documento</span>
                    <input id="detail-date-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Numero pagine</span>
                    <input id="detail-pages-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Tipologia documento</span>
                    <input id="detail-type-input" type="text" readonly>
                  </label>

                  <label class="field">
                    <span>Confidenza</span>
                    <input id="detail-confidence-input" type="text" readonly>
                  </label>

                  <label class="field full-field">
                    <span>Breve descrizione</span>
                    <textarea id="detail-description-input" rows="3" readonly></textarea>
                  </label>
                </div>
              </div>
            </div>
          </article>
        </section>
      </main>
    </div>
  </div>

  <button class="back-to-top" id="back-to-top" type="button" aria-label="Torna su">↑</button>

  <script src="{{ asset('nexum-app/app.js') }}?v={{ filemtime(public_path('nexum-app/app.js')) }}"></script>
</body>
</html>
