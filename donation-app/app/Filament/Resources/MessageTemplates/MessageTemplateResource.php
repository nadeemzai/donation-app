<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageTemplates;

use App\Filament\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Resources\MessageTemplates\Pages\ListMessageTemplates;
use App\Models\MessageTemplate;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Message wording (FR-NTF-02). The set of templates is fixed by the code; staff edit
 * the text. Placeholders like {donor_name} are checked on save.
 */
class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Message templates';

    protected static ?int $navigationSort = 65;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $placeholders = collect(MessageTemplate::PLACEHOLDERS)
            ->map(fn (string $label, string $name): string => '{'.$name.'} — '.$label)
            ->implode(' · ');

        $text = fn (string $name, string $label, int $rows = 6, bool $urdu = false, bool $required = true): Textarea => Textarea::make($name)
            ->label($label)
            ->rows($rows)
            ->maxLength(4000)
            ->required($required)
            ->extraInputAttributes($urdu ? ['dir' => 'rtl', 'lang' => 'ur'] : [])
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $unknown = MessageTemplate::unknownPlaceholders(is_string($value) ? $value : null);

                if ($unknown !== []) {
                    $fail('Unknown placeholder: {'.implode('}, {', $unknown).'}. Use only the ones listed above.');
                }
            });

        return $schema->components([
            Section::make('Template')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(100),
                    Toggle::make('is_active')->label('Send this message')->inline(false)
                        ->helperText('Off = this message is never sent, on any channel.'),
                    TextInput::make('description')->maxLength(255)->columnSpanFull(),
                ]),
            Section::make('WhatsApp')
                ->description('Placeholders: '.$placeholders)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    $text('whatsapp_body', 'English'),
                    $text('whatsapp_body_ur', 'Urdu (optional — used for donors who chose Urdu)', urdu: true, required: false),
                ]),
            Section::make('Email')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    $text('email_subject', 'Subject (English)', rows: 1)->maxLength(200),
                    $text('email_subject_ur', 'Subject (Urdu, optional)', rows: 1, urdu: true, required: false)->maxLength(200),
                    $text('email_body', 'Body (English)', rows: 10),
                    $text('email_body_ur', 'Body (Urdu, optional)', rows: 10, urdu: true, required: false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (MessageTemplate $record): ?string => $record->description),
                TextColumn::make('key')->badge()->color('gray'),
                IconColumn::make('has_urdu')->label('Urdu')->boolean()
                    ->state(fn (MessageTemplate $record): bool => filled($record->whatsapp_body_ur) || filled($record->email_body_ur)),
                IconColumn::make('is_active')->label('On')->boolean(),
                TextColumn::make('updated_at')->label('Last changed')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMessageTemplates::route('/'),
            'edit' => EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
