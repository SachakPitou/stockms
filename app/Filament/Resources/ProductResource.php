<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Identity')
                ->schema([
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(Category::pluck('name', 'id'))
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Primary Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('barcode')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('unit')
                        ->default('pcs')
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\Select::make('cost_currency')
                        ->label('Cost Currency')
                        ->options([
                            'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP',
                            'CNY' => 'CNY', 'KHR' => 'KHR', 'THB' => 'THB',
                        ])
                        ->default('USD'),

                    Forms\Components\TextInput::make('selling_price')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),
                ])->columns(3),

            Forms\Components\Section::make('Stock Control')
                ->schema([
                    Forms\Components\TextInput::make('reorder_point')
                        ->label('Reorder Point')
                        ->helperText('Alert when stock falls to this level')
                        ->numeric()
                        ->default(10),

                    Forms\Components\TextInput::make('reorder_qty')
                        ->label('Reorder Quantity')
                        ->helperText('How many to order when restocking')
                        ->numeric()
                        ->default(50),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Product')
                        ->default(true),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reorder_point')
                    ->label('Reorder At')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name'),

                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
