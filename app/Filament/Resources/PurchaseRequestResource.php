<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-plus';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $navigationLabel = 'Purchase Requests';
    protected static ?int $navigationSort = 1;
    public static function canAccess(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole([
                'Admin',
                'HR Verifier',
                'HR Approver',
                'Technical Team PP',
                'Technical Team Poipet',
            ]);
    }

    public static function canCreate(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole([
                'Admin',
                'Technical Team PP',
                'Technical Team Poipet',
            ]);
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request Details')
                ->schema([
                    Forms\Components\TextInput::make('pr_number')
                        ->label('Request Number')
                        ->default('PR-' . strtoupper(Str::random(8)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn ($record) =>
                            $record && $record->status !== 'draft'
                        ),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft'                => '📝 Draft',
                            'pending_verification' => '🔍 Pending Verification',
                            'pending_approval'     => '⏳ Pending Approval',
                            'approved'             => '✅ Approved',
                            'rejected'             => '❌ Rejected',
                        ])
                        ->disabled()
                        ->default('draft'),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Deliver To Warehouse')
                        ->options(function () {
                            $user = auth()->user();
                            if ($user->hasRole('Technical Team Poipet')) {
                                return Warehouse::where('name', 'like', '%Poipet%')
                                    ->orWhere('name', 'like', '%Branch%')
                                    ->pluck('name', 'id');
                            }
                            return Warehouse::pluck('name', 'id');
                        })
                        ->required(),

                    Forms\Components\Textarea::make('reason')
                        ->label('Why is this purchase needed?')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('attachment_path')
                        ->label('Supporting Document (optional)')
                        ->helperText('Upload a scanned quotation, memo, or other hard-copy document related to this request.')
                        ->directory('purchase-request-attachments')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ])->columns(2),

            // B's verification comment — visible to everyone, editable by B only
            Forms\Components\Section::make('Verification (HR Staff)')
                ->schema([
                    Forms\Components\Textarea::make('verification_comment')
                        ->label('Verification Comment')
                        ->placeholder('HR Staff adds their review notes here...')
                        ->rows(3)
                        ->disabled(fn () =>
                            !auth()->user()->hasAnyRole(['Admin', 'HR Staff'])
                        )
                        ->columnSpanFull(),
                ])
                ->collapsed(fn ($record) => !$record || $record->status === 'draft')
                ->visible(fn ($record) =>
                    $record && $record->status !== 'draft'
                ),

            Forms\Components\Section::make('Items Requested')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(
                                    Product::where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn ($p) => [
                                            $p->id => $p->name . ' (' . $p->unit . ')'
                                        ])
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('estimated_unit_price')
                                ->label('Est. Unit Price')
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('customisation')
                                ->label('Special Instructions')
                                ->placeholder('e.g. specific colour, pre-config')
                                ->columnSpan(4),
                        ])
                        ->columns(8)
                        ->addActionLabel('+ Add Item')
                        ->defaultItems(1),
                ]),
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
                $query->where('requested_by', auth()->id());
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('pr_number')
                    ->label('PR Number')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Deliver To')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'draft'                => 'Draft',
                        'pending_verification' => 'Needs Verification',
                        'pending_approval'     => 'Needs Approval',
                        'approved'             => 'Approved',
                        'rejected'             => 'Rejected',
                        default                => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'draft'                => 'gray',
                        'pending_verification' => 'warning',
                        'pending_approval'     => 'info',
                        'approved'             => 'success',
                        'rejected'             => 'danger',
                        default                => 'gray',
                    }),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Doc')
                    ->icon(fn ($state) => $state ? 'heroicon-o-paper-clip' : 'heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->url(fn ($record) => $record->attachment_path ? \Illuminate\Support\Facades\Storage::url($record->attachment_path) : null)
                    ->openUrlInNewTab(),

                // ── Hidden by default, still available via column toggle ──────────
                Tables\Columns\TextColumn::make('verifiedBy.name')
                    ->label('Verified By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft'                => 'Draft',
                        'pending_verification' => 'Needs Verification',
                        'pending_approval'     => 'Needs Approval',
                        'approved'             => 'Approved',
                        'rejected'             => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('my_requests')
                    ->label('My Requests Only')
                    ->query(fn ($query) => $query->where('requested_by', auth()->id()))
                    ->visible(fn () => auth()->user()->hasAnyRole(['Technical Team PP', 'Technical Team Poipet']))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('verify')
                        ->label('Verify Request')
                        ->icon('heroicon-m-magnifying-glass')
                        ->color('warning')
                        ->visible(fn (PurchaseRequest $record) =>
                            $record->status === 'pending_verification' &&
                            auth()->user()->hasAnyRole(['Admin', 'HR Verifier'])
                        )
                        ->form([
                            Forms\Components\Textarea::make('verification_comment')
                                ->label('Your Verification Comment')
                                ->helperText('This comment will be visible to the Approver.')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PurchaseRequest $record, array $data) {
                            $record->update([
                                'status'               => 'pending_approval',
                                'verified_by'          => auth()->id(),
                                'verified_at'          => now(),
                                'verification_comment' => $data['verification_comment'],
                            ]);

                            Notification::make()
                                ->title('Request verified')
                                ->body('Sent to Approver for final approval.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('approve')
                        ->label('Approve Request')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->visible(fn (PurchaseRequest $record) =>
                            $record->status === 'pending_approval' &&
                            auth()->user()->hasAnyRole(['Admin', 'HR Approver'])
                        )
                        ->form(function (PurchaseRequest $record) {
                            return [
                                Forms\Components\Placeholder::make('summary')
                                    ->label('Request Summary')
                                    ->content(
                                        "Requested by: {$record->requestedBy->name}\n" .
                                        "Verified by: {$record->verifiedBy?->name}\n" .
                                        "Verification comment: {$record->verification_comment}\n" .
                                        "Supplier: {$record->supplier->name}\n" .
                                        "Deliver to: {$record->warehouse->name}"
                                    ),

                                Forms\Components\Select::make('currency')
                                    ->label('Order Currency')
                                    ->options([
                                        'USD' => 'USD — US Dollar',
                                        'KHR' => 'KHR — Cambodian Riel',
                                        'CNY' => 'CNY — Chinese Yuan',
                                        'THB' => 'THB — Thai Baht',
                                        'SGD' => 'SGD — Singapore Dollar',
                                    ])
                                    ->default('USD')
                                    ->required(),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Exchange Rate to USD')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),

                                Forms\Components\DatePicker::make('order_date')
                                    ->label('Order Date')
                                    ->default(now())
                                    ->required()
                                    ->displayFormat('d M Y'),

                                Forms\Components\DatePicker::make('expected_date')
                                    ->label('Expected Arrival Date')
                                    ->displayFormat('d M Y'),

                                Forms\Components\Textarea::make('po_notes')
                                    ->label('Purchase Order Notes')
                                    ->rows(2),
                            ];
                        })
                        ->action(function (PurchaseRequest $record, array $data) {
                            $record->update([
                                'status'      => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            $mainWarehouse = \App\Models\Warehouse::where('is_default', true)->first()
                                ?? \App\Models\Warehouse::firstOrFail();

                            $po = PurchaseOrder::create([
                                'po_number'                 => 'PO-' . strtoupper(Str::random(8)),
                                'supplier_id'               => $record->supplier_id,
                                'warehouse_id'              => $mainWarehouse->id,
                                'destination_warehouse_id'  => $record->warehouse_id,
                                'user_id'                   => auth()->id(),
                                'status'                    => 'draft',
                                'order_date'                => $data['order_date'],
                                'expected_date'             => $data['expected_date'] ?? null,
                                'currency'                  => $data['currency'],
                                'exchange_rate'             => $data['exchange_rate'],
                                'freight_cost'              => 0,
                                'customs_duty'              => 0,
                                'total'                     => 0,
                                'notes'                     => "Auto-created from PR #{$record->pr_number}. " . ($data['po_notes'] ?? ''),
                            ]);

                            foreach ($record->items as $item) {
                                PurchaseOrderItem::create([
                                    'purchase_order_id' => $po->id,
                                    'product_id'        => $item->product_id,
                                    'qty_ordered'       => $item->quantity,
                                    'qty_received'      => 0,
                                    'unit_price'        => $item->estimated_unit_price,
                                    'customisation'     => $item->customisation,
                                ]);
                            }

                            $destNote = $record->warehouse_id !== $mainWarehouse->id
                                ? " Stock will be received at {$mainWarehouse->name} first, then transferred to {$record->warehouse->name}."
                                : '';

                            Notification::make()
                                ->title('Approved! Purchase Order created.')
                                ->body("PO {$po->po_number} is ready.{$destNote}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(fn (PurchaseRequest $record) =>
                            ($record->status === 'pending_verification' &&
                                auth()->user()->hasAnyRole(['Admin', 'HR Verifier'])) ||
                            ($record->status === 'pending_approval' &&
                                auth()->user()->hasAnyRole(['Admin', 'HR Approver']))
                        )
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason for rejection')
                                ->helperText('The requester will see this reason.')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PurchaseRequest $record, array $data) {
                            $record->update([
                                'status'           => 'rejected',
                                'rejection_reason' => $data['rejection_reason'],
                            ]);

                            Notification::make()
                                ->title('Request rejected')
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (PurchaseRequest $record) =>
                            $record->status === 'draft' &&
                            auth()->id() === $record->requested_by
                        ),

                    Tables\Actions\ViewAction::make(),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequest::route('/create'),
            'edit'   => Pages\EditPurchaseRequest::route('/{record}/edit'),
            'view'   => Pages\ViewPurchaseRequest::route('/{record}'),
        ];
    }
    
}