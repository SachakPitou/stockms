<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $recordTitleAttribute = 'description';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('System')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match(true) {
                        str_contains($state, 'created') => 'success',
                        str_contains($state, 'updated') => 'info',
                        str_contains($state, 'deleted') => 'danger',
                        default                         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('On')
                    ->formatStateUsing(fn ($state) =>
                        $state ? class_basename($state) : '—'
                    )
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Record')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Filter by User')
                    ->options(
                        \App\Models\User::pluck('name', 'id')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Details'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'HR Verifier', 'HR Approver']);
    }
}