<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'User Accounts';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Password')
                ->description('Leave blank to keep the current password when editing.')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->confirmed()
                        ->dehydrated(fn ($state) => filled($state)),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->revealable()
                        ->label('Confirm Password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(false),
                ])->columns(2),
                Forms\Components\Section::make('Role & Access')
                    ->description('Set what this user can do in the system.')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Role')
                            ->options([
                                'Admin'                   => 'Admin — Full system access',
                                'Verify Team'             => 'Verify Team — Can verify purchase requests',
                                'Approval Team'             => 'Approval Team — Can approve PRs and manage stock',
                                'Technical Team PP'       => 'Technical Team PP — Phnom Penh field team',
                                'Technical Team Poipet'   => 'Technical Team Poipet — Poipet field team',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Choose the role that matches this person\'s position.'),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Assigned Warehouse')
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->required(fn (Forms\Get $get) => in_array($get('roles'), ['Technical Team PP', 'Technical Team Poipet']))
                            ->visible(fn (Forms\Get $get) => in_array($get('roles'), ['Technical Team PP', 'Technical Team Poipet']))
                            ->helperText('This user will only see stock and equipment for this warehouse.'),
                    ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->badge()
                    ->color('info')
                    ->placeholder('— All (HR/Admin) —'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, User $record) {
                        // Prevent deleting your own account
                        if ($record->id === auth()->id()) {
                            \Filament\Notifications\Notification::make()
                                ->title('You cannot delete your own account')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'Verify Team', 'Approval Team']);
    }
}