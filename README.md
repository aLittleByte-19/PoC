# NEXUM / aLittleByte - Document Intelligence PoC

Proof of Concept per validare l'integrazione di AI generativa e AI documentale nei flussi NEXUM. Il prototipo copre due casi d'uso principali:

* generazione assistita di comunicazioni HR;
* analisi di PDF multi-destinatario, split dei documenti e estrazione di metadati strutturati.

La PoC nasce per essere dimostrabile in locale senza costi AI, ma può essere collegata ad Amazon Bedrock per test realistici su modelli LLM/documentali.

## Stack Tecnologico

* **Backend: Laravel 12**
  Gestisce routing, validazione, persistenza, code Redis e job asincroni.
* **UI: Blade + CSS/JS custom**
  Mantiene l'esperienza applicativa leggera e aderente ai flussi demo, senza introdurre pannelli esterni.
* **AI: Amazon Bedrock**
  Driver opzionale per generazione contenuti, analisi PDF e estrazione dati. Il modello predefinito è `amazon.nova-lite-v1:0`, configurabile da `.env` o dashboard.
* **Infrastruttura locale: Docker, PostgreSQL, Redis, MinIO**
  PostgreSQL persiste i dati, Redis gestisce la coda, MinIO simula uno storage S3-compatible per originali e split PDF.

## Workflow

### Document Intelligence (Co-Pilot CdL)

1. L'utente carica un PDF dall'interfaccia PoC.
2. Il file originale viene salvato su storage S3-compatible, di default MinIO.
3. `ProcessOriginalDocumentJob` elabora il documento in background tramite Redis.
4. Il classifier configurato identifica i segmenti:
   * `fake`: restituisce un segmento demo deterministico;
   * `bedrock`: invia il PDF ad Amazon Bedrock e chiede i confini logici dei documenti.
5. FPDI genera fisicamente i PDF split e li salva nello storage.
6. L'OCR configurato estrae i campi:
   * `local`: produce dati demo deterministici;
   * `bedrock`: invia ogni split ad Amazon Bedrock per estrazione strutturata.
7. Il frontend riceve aggiornamenti progressivi via Server-Sent Events.

### AI Content Assistant

1. L'utente inserisce prompt, tono e stile.
2. Il backend costruisce un prompt HR-oriented per Bedrock.
3. La risposta JSON viene salvata come `Communication` in stato `draft`.
4. In modalità AI disabilitata, il servizio restituisce una bozza simulata.

## Amministrazione PoC

La dashboard locale è disponibile su:

```text
http://localhost:8080/admin
```

Da qui si possono:

* passare da simulazione locale a Bedrock reale;
* impostare modello Bedrock, soglia di confidenza e driver documento;
* inserire o rimuovere credenziali AWS per la demo;
* resettare i dati generati dalla PoC;
* riavviare la configurazione runtime e la coda.

Le impostazioni vengono scritte nel file `.env`. Questo è comodo per una PoC locale, ma non sostituisce un secret manager in un ambiente di produzione.

## Installazione Rapida

1. Crea il file ambiente:

   ```bash
   cp .env.example .env
   ```

2. Avvia lo stack:

   ```bash
   docker compose up -d --build
   ```

3. Apri i servizi:

   * Applicazione: `http://localhost:8080`
   * Admin PoC: `http://localhost:8080/admin`
   * MinIO Console: `http://localhost:9001`

## Configurazione AI

La configurazione predefinita è sicura per una demo locale: Bedrock è disabilitato, lo split è simulato e l'estrazione OCR usa un fallback locale.

```env
BEDROCK_ENABLED=false
BEDROCK_MODEL_ID=amazon.nova-lite-v1:0
DOCUMENT_CLASSIFIER_DRIVER=fake
DOCUMENT_OCR_DRIVER=local
POC_CONFIDENCE_THRESHOLD=80
```

Per usare Bedrock reale:

```env
BEDROCK_ENABLED=true
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_SESSION_TOKEN=...
AWS_DEFAULT_REGION=eu-north-1
DOCUMENT_CLASSIFIER_DRIVER=bedrock
DOCUMENT_OCR_DRIVER=bedrock
```

Valori supportati:

* `DOCUMENT_CLASSIFIER_DRIVER`: `fake` oppure `bedrock`
* `DOCUMENT_OCR_DRIVER`: `local` oppure `bedrock`
* `BEDROCK_ENABLED`: `false` per simulazione, `true` per chiamate reali

Le variabili Textract restano presenti in `.env.example` ma sono disattivate per questa PoC.

## Code e Timeout

La pipeline documentale può richiedere più tempo di una normale richiesta HTTP. Per questo lo stack usa Redis queue e un worker dedicato:

```text
php artisan queue:work redis --sleep=3 --tries=3 --timeout=330
```

Il retry Redis è allineato tramite:

```env
REDIS_QUEUE_RETRY_AFTER=360
```

## Organizzazione del Codice

Il codice specifico della PoC vive in `app/Poc`:

* `Controllers`: endpoint applicativi e dashboard admin;
* `Models`: documenti originali, split, dati estratti e comunicazioni;
* `Services`: integrazione Bedrock e pipeline documentale;
* `Jobs`: lavorazioni asincrone;
* `Requests`: validazione input;
* `Enums`: stati condivisi;
* `Commands`: utility operative, incluso reset dati.

Le view sono in `resources/views/poc`; CSS, JS e asset statici sono in `public/poc`.

## Verifiche

Comandi principali:

```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app ./vendor/bin/pest
```

Per controllare il worker:

```bash
docker compose top queue
```

## Obiettivi della PoC

La PoC serve a validare:

* fattibilità dello split automatico di PDF multi-destinatario;
* riduzione del data-entry manuale tramite estrazione assistita;
* qualità e controllabilità delle comunicazioni generate con AI;
* integrazione tra Laravel, code Redis, storage S3-compatible e servizi AI AWS.
