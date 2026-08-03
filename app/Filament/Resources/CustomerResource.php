<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationLabel = 'Customers (CID)';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Customer Information')
                ->schema([
                    Forms\Components\TextInput::make('cid')
                        ->label('Customer ID (CID)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. MAT-00123')
                        ->helperText('The unique customer ID from the billing system.'),

                    Forms\Components\TextInput::make('name')
                        ->label('Customer Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\Select::make('area')
                        ->label('Service Area')
                        ->options([
                            'Phnom Penh' => 'Phnom Penh',
                            'Poipet'     => 'Poipet',
                            'Siem Reap'  => 'Siem Reap',
                            'Other'      => 'Other',
                        ]),

                    Forms\Components\TextInput::make('address')
                        ->label('Address')
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->label('Account Status')
                        ->options([
                            'active'     => 'Active',
                            'suspended'  => 'Suspended',
                            'terminated' => 'Terminated',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function ($query) {
                if (\App\Helpers\WarehouseHelper::seesAllWarehouses()) {
                    return $query;
                }

                $warehouseName = auth()->user()?->warehouse?->name;
                if (! $warehouseName) {
                    return $query->whereRaw('1 = 0');
                }

                // Match "Poipet Warehouse" → area "Poipet", "Phnom Penh Warehouse" → area "Phnom Penh"
                $area = str_replace(' Warehouse', '', $warehouseName);
                return $query->where('area', $area);
            })
            ->columns([
                Tables\Columns\TextColumn::make('cid')
                    ->label('CID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('area')
                    ->label('Area')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'active'     => 'success',
                        'suspended'  => 'warning',
                        'terminated' => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('activeIssuances_count')
                    ->label('Equipment Out')
                    ->counts('activeIssuances')
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'     => 'Active',
                        'suspended'  => 'Suspended',
                        'terminated' => 'Terminated',
                    ]),

                Tables\Filters\SelectFilter::make('area')
                    ->options([
                        'Phnom Penh' => 'Phnom Penh',
                        'Poipet'     => 'Poipet',
                        'Siem Reap'  => 'Siem Reap',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
            'view'   => Pages\ViewCustomer::route('/{record}'),
        ];
    }
    public static function canAccess(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyPermission([
                'view customers',
                'issue equipment to customer',
            ]);
    }
}
