<x-filament-panels::page>
    <div class="nexum-admin-dashboard">
        <section class="nexum-panel">
            <div class="nexum-panel-heading">
                <div>
                    <p class="nexum-eyebrow">Amministrazione</p>
                    <h3>Gestione credenziali</h3>
                </div>
            </div>

            <p class="nexum-admin-copy">
                Da qui puoi creare, consultare e aggiornare gli account autorizzati ad accedere alla PoC NEXUM.
                Le password vengono salvate tramite hashing Laravel e non sono mai rese visibili dopo la creazione.
            </p>

            <div class="nexum-actions">
                <a class="nexum-action nexum-action-primary" href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}">
                    Gestisci utenti
                </a>
                <a class="nexum-action nexum-action-secondary" href="{{ route('poc.app') }}">
                    Apri applicativo
                </a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
