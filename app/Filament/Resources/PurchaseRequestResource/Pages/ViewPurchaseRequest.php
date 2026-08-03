<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseRequest extends ViewRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\EditAction::make()
            ->visible(fn () => $this->record->status === 'draft')];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Request Details')
                ->schema([
                    Infolists\Components\TextEntry::make('pr_number')
                        ->label('PR Number')
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Current Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match($state) {
                            'draft'                => 'Draft',
                            'pending_verification' => 'Pending Verification',
                            'pending_approval'     => 'Pending Approval',
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

                    Infolists\Components\TextEntry::make('supplier.name')
                        ->label('Supplier'),

                    Infolists\Components\TextEntry::make('warehouse.name')
                        ->label('Deliver To'),

                    Infolists\Components\TextEntry::make('requestedBy.name')
                        ->label('Requested By'),

                    Infolists\Components\TextEntry::make('submitted_at')
                        ->label('Submitted')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not submitted yet'),

                    Infolists\Components\TextEntry::make('verifiedBy.name')
                        ->label('Verified By')
                        ->placeholder('Not verified yet'),

                    Infolists\Components\TextEntry::make('verified_at')
                        ->label('Verified At')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('verification_comment')
                        ->label('HR Staff Verification Comment')
                        ->placeholder('No comment yet')
                        ->columnSpanFull()
                        ->visible(fn ($record) =>
                            !in_array($record->status, ['draft', 'pending_verification'])
                        ),

                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->color('danger')
                        ->visible(fn ($record) => $record->status === 'rejected'),
                    Infolists\Components\TextEntry::make('approvedBy.name')
                        ->label('Approved By')
                        ->placeholder('Not approved yet'),

                    Infolists\Components\TextEntry::make('approved_at')
                        ->label('Approved At')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('reason')
                        ->label('Reason for Purchase')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record->status === 'rejected'),
                ])->columns(2),

            Infolists\Components\Section::make('Items Requested')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')
                                ->label('Product'),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('Qty'),
                            Infolists\Components\TextEntry::make('estimated_unit_price')
                                ->label('Est. Price')
                                ->money('USD'),
                            Infolists\Components\TextEntry::make('customisation')
                                ->label('Instructions')
                                ->placeholder('—'),
                        ])->columns(4),
                ]),
        ]);
    }
}
