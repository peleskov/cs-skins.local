<?php

namespace App\Filament\Resources\Translations\Schemas;

use App\Models\Translation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('group')
                    ->label('Группа')
                    ->options(function () {
                        $groups = Translation::getAvailableGroups('en');

                        return array_combine($groups, $groups);
                    })
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Название группы')
                            ->required(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return $data['name'];
                    }),

                TextInput::make('key')
                    ->label('Ключ')
                    ->required()
                    ->placeholder('например: welcome_message')
                    ->helperText('Используйте точку для вложенных ключей: "menu.home"')
                    ->unique(
                        table: Translation::class,
                        column: 'key',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('group', $get('group'))
                            ->where('locale', $get('locale')),
                    )
                    ->validationMessages([
                        'unique' => 'Перевод с таким ключом в этой группе и языке уже существует.',
                    ]),

                Select::make('locale')
                    ->label('Язык')
                    ->options(function () {
                        $locales = Translation::getAvailableLocales();

                        return array_combine($locales, array_map('strtoupper', $locales));
                    })
                    ->required(),

                Textarea::make('value')
                    ->label('Текст перевода')
                    ->rows(4)
                    ->required(),
            ]);
    }
}
