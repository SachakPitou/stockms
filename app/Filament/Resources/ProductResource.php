<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
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
                    Forms\Components\FileUpload::make('image')
                        ->label('Product Image')
                        ->image()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('400')
                        ->imageResizeTargetHeight('400')
                        ->directory('products')
                        ->visibility('public')
                        ->columnSpanFull()
                        ->helperText('Upload a photo of this product. Square images work best.'),
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
                        ->helperText('You will see a warning on the dashboard when stock reaches this number.')
                        ->visible(fn (Forms\Get $get) => ! $get('is_serialized')),

                    Forms\Components\Toggle::make('is_active')
                        ->label('This product is active')
                        ->default(true)
                        ->helperText('Inactive products are hidden from stock operations.'),

                    Forms\Components\Toggle::make('is_serialized')  // 👈 add this block
                        ->label('Track by Serial Number')
                        ->helperText('Turn this on for Router/ONU. Stock quantity will be based on how many serial numbers are registered below — you won\'t set a manual quantity.')
                        ->live()
                        ->default(false),
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
                        ->suffix('units')
                        ->visible(fn (Forms\Get $get) => ! $get('is_serialized')),

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
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(asset('images/no-image.png'))
                    ->size(40),
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

                Tables\Columns\TextColumn::make('stock_summary')
                    ->label('Stock')
                    ->getStateUsing(function ($record) {
                        $seesAll = \App\Helpers\WarehouseHelper::seesAllWarehouses();

                        if ($record->is_serialized) {
                            $unitsQuery = $record->equipmentUnits();
                            if (! $seesAll) {
                                $unitsQuery->where('warehouse_id', auth()->user()?->warehouse_id);
                            }
                            $available = (clone $unitsQuery)->whereIn('condition', ['new', 'refurbished'])->count();
                            $inUse     = (clone $unitsQuery)->where('condition', 'in_use')->count();
                            $total     = (clone $unitsQuery)->count();

                            return "{$total} units ({$available} available, {$inUse} in use)";
                        }

                        if (! $seesAll) {
                            $level = $record->stockLevels->firstWhere('warehouse_id', auth()->user()?->warehouse_id);
                            return ($level->quantity ?? 0) . ' ' . $record->unit;
                        }

                        // Admin/HR — total + breakdown per warehouse
                        $total = $record->stockLevels->sum('quantity');
                        $breakdown = $record->stockLevels
                            ->filter(fn ($l) => $l->quantity > 0)
                            ->map(fn ($l) => "{$l->quantity} in {$l->warehouse->name}")
                            ->implode(', ');

                        return $total . ' ' . $record->unit . ($breakdown ? " ({$breakdown})" : '');
                    })
                    ->wrap()
                    ->color(fn ($record): string => match(true) {
                        $record->is_serialized => 'gray', // color logic below overrides this for serialized case if needed
                        $record->stockLevels->sum('quantity') <= $record->reorder_point => 'danger',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock by Warehouse')
                    ->getStateUsing(function (Product $record) {
                        if (! \App\Helpers\WarehouseHelper::seesAllWarehouses()) {
                            return null; // hidden for non-HR/Admin
                        }
                        $levels = $record->stockLevels()->with('warehouse')->get();
                        if ($levels->isEmpty()) return 'No stock';
                        return $levels->map(fn($l) =>
                            $l->warehouse->name . ': ' . $l->quantity . ' ' . $record->unit
                        )->implode(' | ');
                    })
                    ->placeholder('—')
                    ->visible(fn () => \App\Helpers\WarehouseHelper::seesAllWarehouses())
                    ->wrap(),
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
                Tables\Columns\IconColumn::make('is_serialized')
                    ->label('Serialized')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('primary'),
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
    public static function getRelations(): array
    {
        return [
            RelationManagers\EquipmentUnitsRelationManager::class,
        ];
    }
    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Approver']);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Approver']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasRole('Admin');
    }
}
