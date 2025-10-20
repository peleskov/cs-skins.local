<?php

return [
    'required' => 'Поле :attribute обязательно для заполнения.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'min' => [
        'array' => 'Поле :attribute должно содержать минимум :min элементов.',
        'string' => 'Поле :attribute должно содержать минимум :min символов.',
        'numeric' => 'Поле :attribute должно быть не меньше :min.',
    ],
    'attributes' => [
        'name' => 'Название',
        'slug' => 'URL',
        'description' => 'Описание',
        'price' => 'Цена',
        'fund_percent' => 'Процент в фонд',
        'tiers' => 'Уровни призов',
    ],
];
