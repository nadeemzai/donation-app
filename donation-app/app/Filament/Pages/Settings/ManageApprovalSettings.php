<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Settings\ApprovalSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageApprovalSettings extends BaseSettingsPage
{
    protected static string $settings = ApprovalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Approvals';

    protected static ?string $title = 'Disbursement approvals';

    protected static ?string $slug = 'settings/approvals';

    protected static ?int $navigationSort = 40;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Founder self-approval')
                ->description('While the NGO has a single approver, the founder may approve their own disbursement requests. Each one is flagged "self-approved" and recorded in the audit log. Turn this off once a second approver or trustee joins — two-person approval then applies to every new request.')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('allow_founder_self_approval')
                        ->label('Allow founder self-approval'),
                ]),
            Section::make('Approval levels')
                ->description('Amounts in Pakistani rupees.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('second_level_threshold')
                        ->label('Second approver from (PKR)')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->prefix('Rs')
                        ->required(),
                    TextInput::make('trustee_threshold')
                        ->label('Trustee approval from (PKR)')
                        ->numeric()
                        ->integer()
                        ->prefix('Rs')
                        ->required()
                        ->minValue(fn (Get $get): int => (int) $get('second_level_threshold'))
                        ->validationMessages(['min' => 'Must be at least the second-approver amount.']),
                ]),
        ]);
    }
}
