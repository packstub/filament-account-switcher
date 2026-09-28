<?php

namespace Packstub\AccountSwitcher\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterTeam extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register team';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $team = Team::query()->create($data);
        $team->members()->attach(auth()->user());

        return $team;
    }
}
