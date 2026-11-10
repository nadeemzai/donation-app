<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Disbursements\Pages\CreateDisbursement;
use App\Filament\Resources\Disbursements\Pages\EditDisbursement;
use App\Filament\Resources\Disbursements\Pages\ListDisbursements;
use App\Filament\Resources\Disbursements\Pages\ViewDisbursement;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Application;
use App\Models\Approval;
use App\Models\Disbursement;
use App\Models\DisbursementItem;
use App\Models\Fund;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\DisbursementService;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Disbursements (Week 10 Day 2, FR-DSB-01/02/04): request, approve / reject, withdraw.
 * Self-approved payouts are flagged in the list, on the page and in the audit log, and can be
 * filtered by period for trustee review. Status changes only through DisbursementActions.
 */
class DisbursementResource extends Resource
{
    protected static ?string $model = Disbursement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Disbursements';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'disbursement_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('payout_for')
                        ->label('Payout for')
                        ->options(['application' => 'A household (approved application)', 'project' => 'A project'])
                        ->default(fn (): string => request()->integer('project') > 0 ? 'project' : 'application')
                        ->inline()
                        ->live()
                        ->dehydrated(false)
                        ->visibleOn('create')
                        ->columnSpanFull(),
                    Select::make('project_id')
                        ->label('Project')
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->default(fn (): ?int => request()->integer('project') ?: null)
                        ->options(fn (): array => Project::query()->where('status', ProjectStatus::Active->value)->orderBy('name')->get()
                            ->mapWithKeys(fn (Project $p): array => [$p->id => $p->code.' · '.$p->name.' · left '.Money::rupees($p->budgetLeft())])->all())
                        ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('payout_for') === 'project')
                        ->visible(fn (Get $get, string $operation, ?Disbursement $record): bool => $operation === 'create' ? $get('payout_for') === 'project' : $record?->project_id !== null)
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $project = Project::query()->find($state);

                            if (! $project instanceof Project) {
                                return;
                            }

                            $set('payee_name', $project->name);
                            $set('fund_id', $project->fund_id);
                            $set('amount', $project->budgetLeft());
                        })
                        ->disabledOn('edit')
                        ->dehydrated(fn (Get $get, string $operation): bool => $operation === 'create' && $get('payout_for') === 'project')
                        ->columnSpanFull(),
                    Select::make('application_id')
                        ->label('Approved application')
                        ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('payout_for') !== 'project')
                        ->visible(fn (Get $get, string $operation, ?Disbursement $record): bool => $operation === 'create' ? $get('payout_for') !== 'project' : $record?->application_id !== null)
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->default(fn (): ?int => request()->integer('application') ?: null)
                        ->options(fn (): array => self::applicationQuery()->limit(50)->get()
                            ->mapWithKeys(fn (Application $a): array => [$a->id => self::applicationLabel($a)])->all())
                        ->getSearchResultsUsing(fn (string $search): array => self::applicationQuery()
                            ->where(fn (Builder $q) => $q
                                ->where('application_no', 'like', "%{$search}%")
                                ->orWhereHas('beneficiary', fn (Builder $b) => $b->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                            ->limit(20)->get()
                            ->mapWithKeys(fn (Application $a): array => [$a->id => self::applicationLabel($a)])->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => ($a = Application::query()->find($value)) instanceof Application ? self::applicationLabel($a) : null)
                        ->rule(fn (string $operation, Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($operation, $get): void {
                            if ($operation === 'create' && $get('payout_for') !== 'project' && ! self::applicationQuery()->whereKey($value)->exists()) {
                                $fail('Choose one of your approved applications.');
                            }
                        })
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $application = Application::query()->find($state);

                            if (! $application instanceof Application) {
                                return;
                            }

                            $set('payee_name', $application->beneficiary->name);
                            $set('fund_id', $application->fund !== null && $application->fund->is_active ? $application->fund_id : null);
                            $set('amount', app(DisbursementService::class)->remainingFor($application));
                        })
                        ->disabledOn('edit')
                        ->dehydrated(fn (Get $get, string $operation): bool => $operation === 'create' && $get('payout_for') !== 'project')
                        ->columnSpanFull(),
                    Select::make('fund_id')
                        ->label('Pay from fund')
                        ->required()
                        ->native(false)
                        ->options(fn (): array => Fund::query()->active()->get()
                            ->mapWithKeys(fn (Fund $f): array => [$f->id => $f->name.($f->is_zakat ? ' (Zakat)' : '').' · '.Money::rupees((string) $f->balance)])
                            ->all())
                        ->disabled(fn (Get $get): bool => $get('payout_for') === 'project')
                        ->dehydrated()
                        ->helperText(fn (Get $get): string => $get('payout_for') === 'project'
                            ? 'A project is paid from its own fund.'
                            : 'A Zakat fund can only pay a household found Zakat-eligible.'),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->prefix('Rs')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->hidden(fn (Get $get, string $operation): bool => $operation === 'create' && self::hasGoods($get))
                        ->disabled(fn (?Disbursement $record): bool => $record !== null && $record->goods()->exists())
                        ->helperText(function (Get $get): ?string {
                            if ($get('payout_for') === 'project') {
                                $project = Project::query()->find($get('project_id'));

                                return $project instanceof Project ? 'Budget left on this project: '.Money::rupees($project->budgetLeft()).'.' : null;
                            }

                            $application = Application::query()->find($get('application_id'));
                            $left = $application instanceof Application ? app(DisbursementService::class)->remainingFor($application) : null;

                            return $left !== null ? 'Still to pay on this application: '.Money::rupees($left).'.' : null;
                        }),
                    Select::make('method')
                        ->label('Paid by')
                        ->options(PayoutMethod::class)
                        ->default(PayoutMethod::Cash)
                        ->required()
                        ->native(false)
                        ->disabled(fn (?Disbursement $record): bool => $record !== null && $record->goods()->exists())
                        ->live(),
                    Repeater::make('goods')
                        ->label('Goods from our store')
                        ->helperText('Value = quantity × the item’s value per unit. Leave empty for goods bought or given directly, and type the value instead.')
                        ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && self::method($get) === PayoutMethod::InKind)
                        ->dehydrated(fn (Get $get, string $operation): bool => $operation === 'create' && self::method($get) === PayoutMethod::InKind)
                        ->defaultItems(0)
                        ->addActionLabel('Add item')
                        ->columns(2)
                        ->live()
                        ->columnSpanFull()
                        ->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->distinct()
                                ->options(fn (): array => InventoryItem::query()->where('is_active', true)->orderBy('name')->get()
                                    ->mapWithKeys(fn (InventoryItem $i): array => [$i->id => $i->name.' · available '.$i->quantityLabel($i->available())])->all()),
                            TextInput::make('quantity')->label('Quantity')->required()->numeric()->minValue(0.01),
                        ]),
                    TextInput::make('payee_name')->label('Paid to')->required()->maxLength(120),
                    TextInput::make('payee_account')
                        ->label(fn (Get $get): string => self::method($get) === PayoutMethod::Wallet ? 'Wallet number' : 'Bank account / IBAN')
                        ->maxLength(40)
                        ->visible(fn (Get $get): bool => self::method($get)?->needsAccount() ?? false)
                        ->required(fn (Get $get, string $operation, ?Disbursement $record): bool => (self::method($get)?->needsAccount() ?? false)
                            && ($operation === 'create' || $record?->payee_account === null))
                        ->helperText(fn (?Disbursement $record): string => $record?->maskedPayeeAccount() !== null
                            ? 'On file: '.$record->maskedPayeeAccount().'. Leave empty to keep it.'
                            : 'Stored encrypted and only shown masked.'),
                    Textarea::make('purpose')->label('What it is for')->required()->rows(3)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('disbursement_no')->label('Number')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('is_self_approved')->label('Self-approved')
                        ->state(fn (Disbursement $record): string => $record->is_self_approved ? 'Yes — approved by the requester' : 'No')
                        ->badge()
                        ->color(fn (Disbursement $record): string => $record->is_self_approved ? 'danger' : 'gray'),
                    TextEntry::make('amount')->state(fn (Disbursement $record): string => Money::rupees((string) $record->amount))->weight('bold'),
                    TextEntry::make('fund.name')->label('From fund')
                        ->state(fn (Disbursement $record): string => $record->fund->name.($record->fund->is_zakat ? ' (Zakat)' : '')),
                    TextEntry::make('method')->label('Paid by'),
                    TextEntry::make('payee_name')->label('Paid to'),
                    TextEntry::make('masked_account')->label('Account')->state(fn (Disbursement $record): ?string => $record->maskedPayeeAccount())->placeholder('—'),
                    TextEntry::make('application.application_no')->label('Application')
                        ->state(fn (Disbursement $record): ?string => $record->application !== null
                            ? $record->application->application_no.' · '.$record->application->beneficiary->name
                            : null)
                        ->url(fn (Disbursement $record): ?string => $record->application !== null && (bool) self::currentUser()?->can('view', $record->application)
                            ? ApplicationResource::getUrl('view', ['record' => $record->application_id])
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('project.name')->label('Project')
                        ->state(fn (Disbursement $record): ?string => $record->project !== null ? $record->project->code.' · '.$record->project->name : null)
                        ->url(fn (Disbursement $record): ?string => $record->project_id !== null && (bool) self::currentUser()?->can('view', $record->project)
                            ? ProjectResource::getUrl('view', ['record' => $record->project_id])
                            : null)
                        ->visible(fn (Disbursement $record): bool => $record->project_id !== null),
                    TextEntry::make('requester.name')->label('Requested by')->placeholder('—'),
                    TextEntry::make('requested_at')->label('Requested')->dateTime('d M Y H:i'),
                    TextEntry::make('fund_available')->label('Available in fund')
                        ->state(fn (Disbursement $record): string => Money::rupees(app(DisbursementService::class)->availableIn($record->fund)))
                        ->helperText('Balance minus payouts approved and not yet paid.')
                        ->visible(fn (Disbursement $record): bool => in_array($record->status, [DisbursementStatus::Requested, DisbursementStatus::Approved], true)),
                    TextEntry::make('paid_at')->label('Paid')->dateTime('d M Y H:i')
                        ->helperText(fn (Disbursement $record): ?string => $record->payer !== null ? 'by '.$record->payer->name : null)
                        ->visible(fn (Disbursement $record): bool => $record->paid_at !== null),
                    TextEntry::make('payment_reference')->label('Reference')->placeholder('—')
                        ->visible(fn (Disbursement $record): bool => $record->paid_at !== null),
                    TextEntry::make('purpose')->label('What it is for')->columnSpanFull(),
                ]),
            Section::make('Goods')
                ->columnSpanFull()
                ->visible(fn (Disbursement $record): bool => $record->goods->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('goods')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('item.name')->label('Item'),
                            TextEntry::make('quantity')->state(fn (DisbursementItem $record): string => $record->item->quantityLabel((string) $record->quantity)),
                            TextEntry::make('value')->state(fn (DisbursementItem $record): string => Money::rupees($record->value())),
                        ]),
                ]),
            Section::make('Approvals')
                ->columnSpanFull()
                ->description(fn (Disbursement $record): string => self::approvalProgress($record))
                ->schema([
                    RepeatableEntry::make('approvals')
                        ->hiddenLabel()
                        ->columns(5)
                        ->placeholder('No decision yet.')
                        ->schema([
                            TextEntry::make('level')->state(fn (Approval $record): string => 'Level '.$record->level),
                            TextEntry::make('approver.name')->label('By'),
                            TextEntry::make('decision')->badge(),
                            IconEntry::make('is_self_approval')->label('Self-approval')->boolean()
                                ->trueColor('danger')->falseColor('gray'),
                            TextEntry::make('decided_at')->label('When')->dateTime('d M Y H:i'),
                            TextEntry::make('comment')->placeholder('—')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('disbursement_no')->label('Number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('payee_name')->label('Paid to')->searchable()
                    ->description(fn (Disbursement $record): ?string => $record->application->application_no ?? $record->project?->code),
                TextColumn::make('fund.name')->label('Fund'),
                TextColumn::make('amount')->state(fn (Disbursement $record): string => Money::rupees((string) $record->amount))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('amount', $direction)),
                TextColumn::make('method')->label('By')->badge()->color('gray')->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('approvals_progress')->label('Approvals')
                    ->state(fn (Disbursement $record): string => self::approvedCount($record).' of '.$record->required_approvals),
                IconColumn::make('is_self_approved')->label('Self-approved')->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)->trueColor('danger')
                    ->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray')
                    ->tooltip(fn (Disbursement $record): ?string => $record->is_self_approved ? 'Approved by the person who requested it (BR-06)' : null),
                TextColumn::make('requester.name')->label('Requested by')->toggleable(),
                TextColumn::make('requested_at')->label('Requested')->date()->sortable(),
            ])
            ->filters([
                Filter::make('waiting_for_me')
                    ->label('Waiting for my decision')
                    ->query(fn (Builder $query): Builder => self::waitingFor($query, self::currentUser())),
                SelectFilter::make('status')->options(DisbursementStatus::class),
                SelectFilter::make('fund_id')->label('Fund')->relationship('fund', 'name'),
                TernaryFilter::make('is_self_approved')->label('Self-approved'),
                Filter::make('requested_between')
                    ->label('Requested between')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q) => $q->whereDate('requested_at', '>=', (string) $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q) => $q->whereDate('requested_at', '<=', (string) $data['until']))),
            ])
            ->recordActions([
                ViewAction::make(),
                ...DisbursementActions::all(),
            ]);
    }

    /**
     * @return Builder<Disbursement>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Disbursement> $query */
        $query = parent::getEloquentQuery()->with(['fund', 'application.beneficiary', 'project', 'requester', 'approvals', 'goods.item']);

        $user = self::currentUser();

        return $user === null ? $query->whereRaw('1 = 0') : $query->visibleTo($user);
    }

    /** Requests this user can decide on now (approvers only). */
    public static function getNavigationBadge(): ?string
    {
        $user = self::currentUser();

        if ($user === null || ! $user->can('disbursements.approve')) {
            return null;
        }

        $service = app(ApprovalService::class);
        $count = self::waitingFor(Disbursement::query()->visibleTo($user), $user)
            ->limit(200)
            ->get()
            ->filter(fn (Disbursement $d): bool => $service->cannotDecide($d, $user) === null)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for your decision';
    }

    /**
     * Waiting requests this user has not decided on and may decide on (their own only for the
     * founder while self-approval was allowed). The trustee rule is checked by the buttons.
     *
     * @param  Builder<Disbursement>  $query
     * @return Builder<Disbursement>
     */
    public static function waitingFor(Builder $query, ?User $user): Builder
    {
        if ($user === null || ! $user->can('disbursements.approve')) {
            return $query->whereRaw('1 = 0');
        }

        $isFounder = $user->hasRole(Role::SuperAdmin->value);

        return $query
            ->where('status', DisbursementStatus::Requested->value)
            ->whereDoesntHave('approvals', fn (Builder $a) => $a->where('approver_id', $user->id))
            ->where(fn (Builder $q) => $q
                ->whereNull('requested_by')
                ->orWhere('requested_by', '!=', $user->id)
                ->when($isFounder, fn (Builder $own) => $own->orWhere(fn (Builder $self) => $self
                    ->where('requested_by', $user->id)
                    ->where('self_approval_allowed', true))));
    }

    /**
     * Approved applications this user may request a payout for.
     *
     * @return Builder<Application>
     */
    public static function applicationQuery(): Builder
    {
        $user = self::currentUser();

        if ($user === null) {
            return Application::query()->whereRaw('1 = 0');
        }

        return Application::query()
            ->visibleTo($user)
            ->where('status', ApplicationStatus::Approved->value)
            ->whereHas('beneficiary')
            ->with('beneficiary')
            ->latest('decided_at');
    }

    public static function applicationLabel(Application $a): string
    {
        $left = app(DisbursementService::class)->remainingFor($a);

        return implode(' · ', array_filter([
            $a->application_no,
            $a->beneficiary->name,
            $a->category->label(),
            $a->approved_amount !== null ? 'approved '.Money::rupees((string) $a->approved_amount) : 'in kind',
            $left !== null ? 'left '.Money::rupees($left) : null,
        ]));
    }

    public static function approvedCount(Disbursement $record): int
    {
        return $record->approvals->filter(fn (Approval $a): bool => $a->decision === ApprovalDecision::Approved)->count();
    }

    public static function approvalProgress(Disbursement $record): string
    {
        $level = app(ApprovalService::class)->nextLevel($record);
        $text = self::approvedCount($record)." of {$record->required_approvals} approvals"
            .($record->required_approvals > 1 ? ' (amount at or above the second-approver limit)' : '').'.';

        if ($level !== null) {
            $text .= $level === 3 && $record->required_approvals === 3
                ? ' Next: the final approval, by a trustee.'
                : " Next: approval {$level}, by an approver who has not decided yet.";
        }

        if ($record->self_approval_allowed) {
            $text .= ' Founder self-approval was allowed when this was requested.';
        }

        return $text;
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** Goods lines chosen on the request form. */
    private static function hasGoods(Get $get): bool
    {
        if (self::method($get) !== PayoutMethod::InKind) {
            return false;
        }

        $goods = $get('goods');

        return collect(is_array($goods) ? $goods : [])->contains(fn (mixed $row): bool => is_array($row) && filled($row['item_id'] ?? null));
    }

    private static function method(Get $get): ?PayoutMethod
    {
        $value = $get('method');

        return $value instanceof PayoutMethod ? $value : PayoutMethod::tryFrom(is_string($value) ? $value : '');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDisbursements::route('/'),
            'create' => CreateDisbursement::route('/create'),
            'view' => ViewDisbursement::route('/{record}'),
            'edit' => EditDisbursement::route('/{record}/edit'),
        ];
    }
}
