<?php

namespace App\Filament\Resources\Candidates;

use App\Enums\CandidateStatus;
use App\Filament\Actions\TransitionAction;
use App\Filament\Resources\Candidates\Pages\ManageCandidates;
use App\Models\Candidate;
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
use Filament\Forms\Components\DatePicker;
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

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Candidates';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal')
                ->columns(2)
                ->schema([
                    TextInput::make('full_name')->required()->columnSpanFull(),
                    TextInput::make('aadhaar')
                        ->label('Aadhaar (12-digit)')
                        ->password()
                        ->revealable()
                        ->required(fn ($record) => $record === null)
                        ->visibleOn('create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Stored as SHA-256 token + last4 only — never the raw number.'),
                    Select::make('gender')->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ])->required(),
                    DatePicker::make('dob')->label('Date of birth')->required(),
                    Select::make('category')->options([
                        'gen' => 'General', 'obc' => 'OBC', 'sc' => 'SC', 'st' => 'ST',
                        'pwd' => 'PwD', 'minority' => 'Minority', 'ews' => 'EWS',
                    ])->required(),
                    Select::make('education_level')->options([
                        'below_8' => 'Below 8th', '8_pass' => '8th pass',
                        '10_pass' => '10th pass', '12_pass' => '12th pass',
                        'iti' => 'ITI', 'diploma' => 'Diploma',
                        'graduate' => 'Graduate', 'pg' => 'Post-graduate',
                    ])->required(),
                    TextInput::make('phone')->tel()->required()->maxLength(15),
                    TextInput::make('email')->email(),
                ]),
            Section::make('Address')
                ->columns(2)
                ->schema([
                    TextInput::make('address_line1')->required()->columnSpanFull(),
                    TextInput::make('address_line2')->columnSpanFull(),
                    TextInput::make('city')->required(),
                    TextInput::make('state')->required(),
                    TextInput::make('pincode')->required()->maxLength(6),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->columns(3)->schema([
                TextEntry::make('full_name'),
                TextEntry::make('gender'),
                TextEntry::make('dob')->date(),
                TextEntry::make('category'),
                TextEntry::make('education_level'),
                TextEntry::make('phone'),
                TextEntry::make('aadhaar_last4')
                    ->label('Aadhaar')
                    ->formatStateUsing(fn ($state) => 'XXXX-XXXX-'.$state),
                TextEntry::make('status')->badge(),
            ]),
            Section::make('Address')->columns(3)->schema([
                TextEntry::make('address_line1'),
                TextEntry::make('city'),
                TextEntry::make('state'),
                TextEntry::make('pincode'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')->searchable()->sortable(),
                TextColumn::make('aadhaar_last4')
                    ->label('Aadhaar')
                    ->formatStateUsing(fn ($state) => 'XXXX-XXXX-'.$state),
                TextColumn::make('phone')->searchable()->toggleable(),
                TextColumn::make('city')->toggleable(),
                TextColumn::make('state')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CandidateStatus::class),
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
            'index' => ManageCandidates::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
