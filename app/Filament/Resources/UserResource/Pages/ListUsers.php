<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Filament\Forms;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmAccess')
                ->label('🔒 Verify Identity to Access')
                ->color('warning')
                ->visible(fn () => !session('user_management_access'))
                ->form([
                    Forms\Components\TextInput::make('password')
                        ->label('Enter your current password to continue')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    if (!Hash::check($data['password'], auth()->user()->password)) {
                        Notification::make()
                            ->title('Incorrect password')
                            ->body('Please enter your correct current password.')
                            ->danger()
                            ->send();
                        return;
                    }

                    session(['user_management_access' => true]);

                    Notification::make()
                        ->title('Identity verified')
                        ->body('You now have access to User Accounts.')
                        ->success()
                        ->send();

                    // Use correct Filament redirect
                    $this->redirectRoute('filament.admin.resources.users.index');
                }),

            Actions\CreateAction::make()
                ->label('New User Account')
                ->visible(fn () => (bool) session('user_management_access')),
        ];
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        if (!session('user_management_access')) {
            return parent::getTableQuery()->whereRaw('1 = 0');
        }

        return parent::getTableQuery();
    }
}