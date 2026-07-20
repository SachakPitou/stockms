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
    protected static ?string $navigationGroup = 'Stock';
    protected static ?string $navigationLabel = 'Products';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Basic Information')
                ->description('Fill in the essential details for this product.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Product Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->placeholder('e.g. Huawei ONU HG8145V5'),

                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(Category::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Select a category'),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Select a supplier'),

                    Forms\Components\Select::make('unit')
                        ->label('Unit of Measure')
                        ->options([
                            'pcs'    => 'Pieces (pcs)',
                            'meter'  => 'Meter',
                            'box'    => 'Box',
                            'set'    => 'Set',
                            'roll'   => 'Roll',
                            'bottle' => 'Bottle',
                            'bag'    => 'Bag',
                            'ream'   => 'Ream',
                            'litre'  => 'Litre',
                            'kg'     => 'Kilogram (kg)',
                        ])
                        ->default('pcs')
                        ->required(),

                    Forms\Components\TextInput::make('reorder_point')
                        ->label('Low Stock Alert — Warn me when stock drops below')
                        ->numeric()
                        ->default(10)
                        ->suffix('units')
                        ->helperText('You will see a warning on the dashboard when stock reaches this number.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('This product is active')
                        ->default(true)
                        ->helperText('Inactive products are hidden from stock operations.'),
                ])->columns(2),

            Forms\Components\Section::make('Pricing & Cost')
                ->description('Optional — fill in if this item has a cost or selling price.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Unit Cost (what we pay)')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\Select::make('cost_currency')
                        ->label('Cost Currency')
                        ->options([
                            'USD' => 'USD — US Dollar',
                            'KHR' => 'KHR — Cambodian Riel',
                            'CNY' => 'CNY — Chinese Yuan',
                            'THB' => 'THB — Thai Baht',
                            'SGD' => 'SGD — Singapore Dollar',
                            'EUR' => 'EUR — Euro',
                        ])
                        ->default('USD'),

                    Forms\Components\TextInput::make('selling_price')
                        ->label('Selling Price (what we charge, if applicable)')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->helperText('Leave as 0 if this item is not sold to customers.'),
                ])->columns(3),

            Forms\Components\Section::make('Advanced')
                ->description('SKU, barcode and reorder quantity — for admin use only.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU (Stock Keeping Unit)')
                        ->helperText('A unique code to identify this product. Leave blank to auto-generate.')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. NET-001'),

                    Forms\Components\TextInput::make('reorder_qty')
                        ->label('Reorder Quantity')
                        ->helperText('How many units to order when restocking.')
                        ->numeric()
                        ->default(50)
                        ->suffix('units'),

                    Forms\Components\TextInput::make('barcode')
                        ->label('Barcode')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->placeholder('Optional'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No category'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('stockLevels_sum_quantity')
                    ->label('Total Stock')
                    ->getStateUsing(fn ($record) =>
                        $record->stockLevels->sum('quantity') . ' ' . $record->unit
                    )
                    ->color(fn ($record): string =>
                        $record->stockLevels->sum('quantity') <= $record->reorder_point
                            ? 'danger' : 'success'
                    ),

                // Hidden by default
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('No supplier')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reorder_point')
                    ->label('Alert Below')
                    ->suffix(fn ($record) => ' ' . $record->unit)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Filter by Category'),

                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Filter by Supplier'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active products only'),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn ($query) =>
                        $query->whereHas('stockLevels', fn ($q) =>
                            $q->whereRaw('stock_levels.quantity <= products.reorder_point')
                        )
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit'),
                Tables\Actions\ViewAction::make()
                    ->label('View Stock'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Add your first product to get started.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
            'view'   => Pages\ViewProduct::route('/{record}'),
        ];
    }
}
