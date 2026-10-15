<?php

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('lets an admin create a staff user with a role', function () {
    foreach (['super_admin', 'admin', 'crm_agent'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Agent',
            'email' => 'new.agent@example.com',
            'password' => 'Secret-12345',
            'roles' => [Role::where('name', 'crm_agent')->value('id')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'new.agent@example.com')->sole()->hasRole('crm_agent'))->toBeTrue();
});

it('offers roles by id and hides super_admin from plain admins', function () {
    foreach (['super_admin', 'admin', 'crm_agent'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    Filament::setCurrentPanel('admin');

    $agentId = Role::where('name', 'crm_agent')->value('id');
    Livewire::test(CreateUser::class)->assertFormFieldExists('roles', fn (\Filament\Forms\Components\Select $field): bool => array_key_exists($agentId, $field->getOptions())
        && ! in_array('super_admin', $field->getOptions(), true));
});
