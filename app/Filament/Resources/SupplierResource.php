<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $navigationLabel = 'Suppliers';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Supplier Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('country')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\Textarea::make('address')
                        ->label('Address')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('currency')
                        ->options([
                            'USD' => 'USD — US Dollar',
                            'EUR' => 'EUR — Euro',
                            'GBP' => 'GBP — British Pound',
                            'CNY' => 'CNY — Chinese Yuan',
                            'JPY' => 'JPY — Japanese Yen',
                            'KHR' => 'KHR — Cambodian Riel',
                            'THB' => 'THB — Thai Baht',
                            'SGD' => 'SGD — Singapore Dollar',
                        ])
                        ->required()
                        ->default('USD'),

                    Forms\Components\TextInput::make('lead_time_days')
                        ->label('Lead Time (days)')
                        ->numeric()
                        ->default(14),
                ])->columns(2),

            Forms\Components\Section::make('Contact Information')
                ->schema([
                    Forms\Components\TextInput::make('contact_name')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_email')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contact_phone')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('website')
                        ->url()
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Additional')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Supplier')
                        ->default(true),
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

                Tables\Columns\TextColumn::make('country')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currency')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('contact_email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchaseOrders_count')
                    ->label('Orders')
                    ->counts('purchaseOrders'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active suppliers'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'HR Verifier', 'HR Approver']);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'HR Verifier', 'HR Approver']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasRole('Admin');
    }
    public static function canAccess(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole([
                'Admin', 'HR Approver', 'HR Verifier',
            ]);
    }
}