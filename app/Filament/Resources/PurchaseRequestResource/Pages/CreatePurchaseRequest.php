<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseRequest extends CreateRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('saveAsDraft')
                ->label('Save as Draft')
                ->color('gray')
                ->action(function () {
                    $this->data['status'] = 'draft';
                    $this->create();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();
        $data['submitted_at'] = now();

        if (empty($data['status']) || $data['status'] === 'draft') {
            $data['status'] = 'pending_verification';
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $record = static::getModel()::create($data);

        foreach ($items as $item) {
            $record->items()->create($item);
        }

        return $record;
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Submit Request')
                ->color('primary')
                ->submit('create'),
            Actions\Action::make('saveAsDraft')
                ->label('Save as Draft')
                ->color('gray')
                ->action(function () {
                    $this->data['status'] = 'draft';
                    $this->create();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}