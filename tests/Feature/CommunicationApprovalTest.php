<?php

use App\Enums\CommunicationStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CommunicationResource\Pages\ViewCommunication;
use App\Models\Communication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('approved is a valid communication status', function () {
    $communication = Communication::factory()->draft()->create();

    $communication->update(['status' => CommunicationStatus::Approved]);

    expect($communication->fresh()->status)->toBe(CommunicationStatus::Approved);
});

test('approved status has correct label and color', function () {
    expect(CommunicationStatus::Approved->label())->toBe('Approvata')
        ->and(CommunicationStatus::Approved->color())->toBe('success');
});

test('approve action transitions communication from draft to approved', function () {
    $communication = Communication::factory()->draft()->create();

    Livewire::test(ViewCommunication::class, ['record' => $communication->getRouteKey()])
        ->callAction('approve')
        ->assertHasNoActionErrors();

    expect($communication->fresh()->status)->toBe(CommunicationStatus::Approved);
});

test('approve action is not visible for approved communication', function () {
    $communication = Communication::factory()->approved()->create();

    Livewire::test(ViewCommunication::class, ['record' => $communication->getRouteKey()])
        ->assertActionHidden('approve');
});

test('approve action is not visible for discarded communication', function () {
    $communication = Communication::factory()->discarded()->create();

    Livewire::test(ViewCommunication::class, ['record' => $communication->getRouteKey()])
        ->assertActionHidden('approve');
});

test('dashboard counts only approved communications as finalizzati', function () {
    Communication::factory()->draft()->count(3)->create();
    Communication::factory()->approved()->count(2)->create();
    Communication::factory()->discarded()->create();

    $dashboard = app(Dashboard::class);
    $metrics = $dashboard->getAssistantMetrics();

    $finalizzati = collect($metrics)->firstWhere('label', 'Contenuti finalizzati');

    expect($finalizzati['value'])->toBe(2);
});
