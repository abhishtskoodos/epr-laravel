<?php

namespace App\Filament\Resources\Vendors;

use App\Enums\VendorStatus;
use App\Filament\Actions\TransitionAction;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Models\Vendor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organization')
                ->columns(2)
                ->schema([
                    TextInput::make('legal_name')->required()->columnSpanFull(),
                    TextInput::make('trade_name'),
                    Select::make('entity_type')
                        ->options([
                            'private_ltd' => 'Private Limited',
                            'public_ltd' => 'Public Limited',
                            'partnership' => 'Partnership',
                            'llp' => 'LLP',
                            'proprietorship' => 'Proprietorship',
                            'society' => 'Society',
                            'trust' => 'Trust',
                            'section_8' => 'Section 8',
                        ])
                        ->required(),
                    TextInput::make('pan')->required()->maxLength(10),
                    TextInput::make('gst')->maxLength(15),
                    TextInput::make('cin')->maxLength(21),
                    TextInput::make('email')->email(),
                    TextInput::make('phone')->required()->tel()->maxLength(15),
                ]),

            Section::make('Address')
                ->columns(2)
                ->schema([
                    TextInput::make('address_line1')->required()->columnSpanFull(),
                    TextInput::make('address_line2')->columnSpanFull(),
                    TextInput::make('city')->required(),
                    TextInput::make('state')->required(),
                    TextInput::make('pincode')->required()->maxLength(6),
                    TextInput::make('country')->required()->default('IN')->maxLength(2),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->columns(3)->schema([
                TextEntry::make('legal_name'),
                TextEntry::make('trade_name')->placeholder('—'),
                TextEntry::make('entity_type'),
                TextEntry::make('pan'),
                TextEntry::make('gst')->placeholder('—'),
                TextEntry::make('cin')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('phone'),
                TextEntry::make('status')->badge(),
            ]),
            Section::make('Verification')->columns(3)->schema([
                TextEntry::make('verified_at')->dateTime()->placeholder('—'),
                TextEntry::make('verifier.name')->label('Verified by')->placeholder('—'),
                TextEntry::make('user.email')->label('Owner'),
            ]),
            Section::make('Address')->columns(2)->schema([
                TextEntry::make('address_line1'),
                TextEntry::make('address_line2')->placeholder('—'),
                TextEntry::make('city'),
                TextEntry::make('state'),
                TextEntry::make('pincode'),
                TextEntry::make('country'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('legal_name')
            ->columns([
                TextColumn::make('legal_name')->searchable()->sortable(),
                TextColumn::make('pan')->searchable(),
                TextColumn::make('city')->toggleable(),
                TextColumn::make('state')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('verified_at')->dateTime('Y-m-d')->toggleable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(VendorStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                TransitionAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendors::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
