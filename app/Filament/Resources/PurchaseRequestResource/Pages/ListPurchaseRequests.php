<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Purchase Request')
                ->visible(fn () => auth()->user()->hasAnyRole([
                    'Admin',
                    'Technical Team PP',
                    'Technical Team Poipet',
                ])),
        ];
    }

    public function getTabs(): array
    {
        $user  = auth()->user();
        $isTech = $user->hasAnyRole([
            'Technical Team PP',
            'Technical Team Poipet',
        ]);

        $tabs = [
            'all' => Tab::make('All'),
        ];

        if ($isTech) {
            $tabs['my_requests'] = Tab::make('My Requests')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('requested_by', $user->id)
                )
                ->badge(
                    PurchaseRequest::where('requested_by', $user->id)->count()
                );
        }

        if ($user->hasAnyRole(['Admin', 'HR Verifier'])) {
            $tabs['needs_verification'] = Tab::make('Needs Verification')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('status', 'pending_verification')
                )
                ->badge(
                    PurchaseRequest::where('status', 'pending_verification')->count()
                )
                ->badgeColor('warning');
        }

        if ($user->hasAnyRole(['Admin', 'HR Approver'])) {
            $tabs['needs_approval'] = Tab::make('Needs Approval')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('status', 'pending_approval')
                )
                ->badge(
                    PurchaseRequest::where('status', 'pending_approval')->count()
                )
                ->badgeColor('info');
        }

        $tabs['approved'] = Tab::make('Approved')
            ->modifyQueryUsing(fn (Builder $q) =>
                $q->where('status', 'approved')
            );

        $tabs['rejected'] = Tab::make('Rejected')
            ->modifyQueryUsing(fn (Builder $q) =>
                $q->where('status', 'rejected')
            );

        return $tabs;
    }
}