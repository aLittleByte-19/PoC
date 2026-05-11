# NEXUM / aLittleByte PoC

Proof of Concept universitaria per i moduli:

- AI Assistant Generativo;
- AI Co-Pilot documentale per Consulenti del Lavoro.

La base applicativa e' Laravel con interfaccia amministrativa Filament. Il progetto resta volutamente leggero: dimostra i flussi principali dell'Analisi dei Requisiti, mentre la rifinitura architetturale e funzionale viene rimandata al PB.

## Stack locale

- Laravel 12
- Filament 3
- PostgreSQL
- Redis e Laravel Queue
- MinIO come storage S3-compatible locale
- Mailpit per test email
- AI worker FastAPI per OCR/parsing/split placeholder
- Bedrock e Textract configurabili, disabilitati di default

## Perimetro PoC

Il perimetro funzionale e le esclusioni sono descritti in [docs/poc-scope.md](docs/poc-scope.md).

## Avvio ambiente

Prima copia l'esempio di configurazione:

```bash
cp .env.example .env
```

Poi avvia i servizi e il bootstrap applicativo:

```bash
docker compose up -d --build
```

Durante l'avvio il container `app` crea `.env` da `.env.example` se manca, installa automaticamente le dipendenze Composer se `vendor/` manca o se cambia `composer.lock`, genera `APP_KEY`, esegue le migrazioni e crea l'utente Filament locale se non esiste.

Credenziali Filament locali di default:

```text
Email: admin@nexum.local
Password: Password123!
```

Se vuoi disabilitare o personalizzare il bootstrap automatico:

```env
COMPOSER_INSTALL_ON_STARTUP=false
LARAVEL_AUTOMATED_SETUP=false
FILAMENT_ADMIN_CREATE=false
FILAMENT_ADMIN_EMAIL=admin@nexum.local
FILAMENT_ADMIN_PASSWORD=Password123!
```

Servizi locali principali:

- Laravel/Nginx: `http://localhost:8080`
- Filament: `http://localhost:8080/admin`
- MinIO console: `http://localhost:9001`
- Mailpit: `http://localhost:8025`
- AI worker: `http://localhost:8001/health`

## Modalita AI

Per default `BEDROCK_ENABLED=false`, quindi l'applicazione usa risposte fake deterministiche utili alla demo locale.

Per provare Bedrock reale:

```env
BEDROCK_ENABLED=true
BEDROCK_AWS_REGION=eu-central-1
BEDROCK_MODEL_ID=<model-id>
AWS_ACCESS_KEY_ID=<access-key>
AWS_SECRET_ACCESS_KEY=<secret-key>
```

Le credenziali reali non devono essere versionate.

## Placeholder intenzionali

OCR reale, Textract, split documentale robusto, matching destinatario, metriche avanzate e consegna email reale sono predisposti ma non implementati in modo completo. Nella PoC vengono simulati o lasciati come punti di integrazione.
