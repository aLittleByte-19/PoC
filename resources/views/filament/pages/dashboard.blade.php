@php
    $assistantMetrics = $this->getAssistantMetrics();
    $copilotMetrics = $this->getCopilotMetrics();
    $recentCommunications = $this->getRecentCommunications();
    $recentDocuments = $this->getRecentDocuments();
@endphp

<x-filament-panels::page>
    <div class="nexum-dashboard">
        <section class="nexum-hero">
            <div>
                <p class="nexum-eyebrow">Console operativa</p>
                <h2>Gestione assistita di comunicazioni interne e documenti del personale.</h2>
                <p>
                    NEXUM supporta redattori HR e operatori CdL nella preparazione dei contenuti,
                    classificazione documentale, verifica degli esiti e tracciamento delle consegne.
                </p>
            </div>

            <div class="nexum-actions">
                <a class="nexum-action nexum-action-primary" href="{{ \App\Filament\Resources\CommunicationResource::getUrl('create') }}">
                    Crea contenuto
                </a>
                <a class="nexum-action nexum-action-secondary" href="{{ \App\Filament\Resources\OriginalDocumentResource::getUrl('index') }}">
                    Carica documenti
                </a>
            </div>
        </section>

        <section class="nexum-panel">
            <div class="nexum-panel-heading">
                <div>
                    <p class="nexum-eyebrow">Moduli</p>
                    <h3>Da dove partire</h3>
                </div>
            </div>

            <div class="nexum-flow-list">
                <a href="{{ \App\Filament\Resources\CommunicationResource::getUrl('create') }}">
                    <strong>AI Assistant Generativo</strong>
                    <span>Scrivi il prompt, scegli tono e stile. Il sistema propone una bozza da revisionare.</span>
                </a>
                <a href="{{ \App\Filament\Resources\OriginalDocumentResource::getUrl('index') }}">
                    <strong>AI Co-Pilot per CdL</strong>
                    <span>Carica un PDF. La PoC prepara estrazione, split e stato di lavorazione.</span>
                </a>
                <a href="{{ \App\Filament\Resources\CommunicationResource::getUrl('index') }}">
                    <strong>Metriche operative</strong>
                    <span>Consulta storico, qualita percepita e stato dei flussi direttamente dalle liste Filament.</span>
                </a>
            </div>
        </section>

        <div class="nexum-grid">
            <section class="nexum-panel">
                <div class="nexum-panel-heading">
                    <div>
                        <p class="nexum-eyebrow">AI Assistant</p>
                        <h3>Stato contenuti</h3>
                    </div>
                </div>

                <div class="nexum-metric-list">
                    @foreach ($assistantMetrics as $metric)
                        <div>
                            <strong>{{ $metric['value'] }}</strong>
                            <span>{{ $metric['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="nexum-panel">
                <div class="nexum-panel-heading">
                    <div>
                        <p class="nexum-eyebrow">Co-Pilot CdL</p>
                        <h3>Stato documenti</h3>
                    </div>
                </div>

                <div class="nexum-metric-list">
                    @foreach ($copilotMetrics as $metric)
                        <div>
                            <strong>{{ $metric['value'] }}</strong>
                            <span>{{ $metric['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="nexum-grid">
            <section class="nexum-panel">
                <div class="nexum-panel-heading">
                    <div>
                        <p class="nexum-eyebrow">Storico prompt</p>
                        <h3>Ultime generazioni</h3>
                    </div>
                </div>

                <div class="nexum-history-list">
                    @forelse ($recentCommunications as $item)
                        <div>
                            <strong>{{ $item['title'] }}</strong>
                            <span>{{ $item['meta'] }}</span>
                        </div>
                    @empty
                        <div>
                            <strong>Nessuna bozza generata</strong>
                            <span>Usa il modulo AI Assistant per creare il primo contenuto.</span>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="nexum-panel">
                <div class="nexum-panel-heading">
                    <div>
                        <p class="nexum-eyebrow">Storico documenti</p>
                        <h3>Ultime analisi</h3>
                    </div>
                </div>

                <div class="nexum-history-list">
                    @forelse ($recentDocuments as $item)
                        <div>
                            <strong>{{ $item['title'] }}</strong>
                            <span>{{ $item['meta'] }}</span>
                        </div>
                    @empty
                        <div>
                            <strong>Nessun documento caricato</strong>
                            <span>Carica un PDF dal modulo Co-Pilot per avviare la demo documentale.</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
