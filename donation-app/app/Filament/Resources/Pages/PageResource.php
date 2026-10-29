<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Website pages: About us, Contact, Privacy and any others (Week 6 Day 5). Served at /{slug}.
 * The web address can't change after the page is created (links and search engines use it).
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get, string $operation): void {
                            if ($operation === 'create' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix(fn (): string => rtrim(url('/'), '/').'/')
                        ->required()
                        ->maxLength(60)
                        ->regex('/^'.Page::SLUG_REGEX.'$/')
                        ->rule(Rule::notIn(Page::RESERVED_SLUGS))
                        ->unique(ignoreRecord: true)
                        ->validationMessages([
                            'regex' => 'Use lowercase letters, numbers and dashes, e.g. our-team.',
                            'not_in' => 'This address is used by the website. Choose another.',
                        ])
                        ->disabledOn('edit')
                        ->helperText('Set once. It can’t be changed later.'),
                    Textarea::make('meta_description')
                        ->label('Search engine summary')
                        ->helperText('One or two sentences shown by Google and when the page is shared on WhatsApp or Facebook.')
                        ->rows(2)
                        ->maxLength(255)
                        ->columnSpanFull(),
                    RichEditor::make('body')
                        ->label('Text')
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['h2', 'h3'],
                            ['bulletList', 'orderedList', 'blockquote'],
                            ['undo', 'redo'],
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('Where it shows')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('is_published')->label('Published')->helperText('Off: only staff can see it.'),
                    Toggle::make('show_in_footer')->label('Link in the footer'),
                    Toggle::make('show_contact_form')->label('Show the contact form')->helperText('Messages arrive in Website → Messages.'),
                    TextInput::make('sort_order')->label('Order')->numeric()->integer()->minValue(0)->default(10),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->weight('bold')->searchable()
                    ->description(fn (Page $record): string => '/'.$record->slug),
                IconColumn::make('is_published')->label('Published')->boolean(),
                IconColumn::make('show_in_footer')->label('Footer')->boolean(),
                IconColumn::make('show_contact_form')->label('Form')->boolean(),
                TextColumn::make('updated_at')->label('Last changed')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Page $record): string => route('pages.show', $record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record): bool => $record->is_published),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
