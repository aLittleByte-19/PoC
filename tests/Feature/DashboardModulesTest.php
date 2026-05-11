<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('dashboard modules section shows exactly two modules', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('AI Assistant Generativo')
        ->assertSee('AI Co-Pilot per CdL')
        ->assertDontSee('Metriche operative');
});

test('dashboard modules section does not contain metriche operative', function () {
    Livewire::test(Dashboard::class)
        ->assertDontSee('Metriche operative');
});
