<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Communication;
use App\Models\ExtractedData;
use App\Models\OriginalDocument;
use App\Models\User;
use App\Services\BedrockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('root redirects guests to the user login', function () {
    $this->get('/')
        ->assertRedirect('/login');
});

test('root redirects authenticated users to the application', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('poc.app'));
});

test('user login authenticates standard users into the application', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('poc.app'));

    $this->assertAuthenticatedAs($user);
});

test('admin users page is available only to admin users', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('Credenziali utenti');
});

test('admin panel keeps its dedicated login route for guests', function () {
    $this->get('/admin/users')
        ->assertRedirect('/admin/login');
});

test('standard users cannot access the admin users page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('poc api session requires authentication', function () {
    $this->getJson('/poc/api/session')->assertUnauthorized();
});

test('authenticated admin session returns profile csrf token and user management link', function () {
    $user = User::factory()->admin()->create(['name' => 'Nexum Admin']);

    $this->actingAs($user)
        ->getJson('/poc/api/session')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.initials', 'NA')
        ->assertJsonPath('user.isAdmin', true)
        ->assertJsonStructure(['csrfToken', 'links' => ['users', 'logout']]);
});

test('authenticated standard session does not expose the user management link', function () {
    $user = User::factory()->create(['name' => 'Nexum User']);

    $this->actingAs($user)
        ->getJson('/poc/api/session')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.isAdmin', false)
        ->assertJsonPath('links.users', null);
});

test('ai assistant generation uses validated parameters and stores a draft', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('generateCommunication')
            ->once()
            ->andReturn(['title' => 'Titolo reale', 'body' => 'Corpo reale']);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/poc/api/communications', [
            'prompt' => 'Comunicazione interna sulla nuova area documentale.',
            'audience' => 'Tutti i dipendenti',
            'tone' => 'Chiaro e diretto',
            'style' => 'Testo informativo',
            'channel' => 'Email interna',
        ])
        ->assertCreated()
        ->assertJsonPath('communication.title', 'Titolo reale');

    expect(Communication::query()->count())->toBe(1);
    expect(Communication::query()->first()->generated_body)->toBe('Corpo reale');
});

test('document ocr upload stores null extracted fields until ocr is implemented', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response([
            'document_id' => '1',
            'status' => 'placeholder',
            'message' => 'OCR predisposto.',
            'data' => [],
        ]),
    ]);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('cedolino.pdf', 16, 'application/pdf');

    $this->actingAs($user)
        ->postJson('/poc/api/documents/ocr', ['document' => $file])
        ->assertCreated()
        ->assertJsonPath('document.employee', null)
        ->assertJsonPath('document.confidence', null);

    expect(OriginalDocument::query()->count())->toBe(1);
    expect(ExtractedData::query()->first()->employee_first_name)->toBeNull();
    expect(ExtractedData::query()->first()->confidence_score)->toBeNull();
});

test('filament user creation hashes password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Operatore CdL',
            'email' => 'operatore@nexum.local',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'operatore@nexum.local')->first();

    expect($created)->not->toBeNull();
    expect(Hash::check('Password12345', $created->password))->toBeTrue();
});
