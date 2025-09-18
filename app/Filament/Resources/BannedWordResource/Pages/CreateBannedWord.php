<?php

namespace App\Filament\Resources\BannedWordResource\Pages;

use App\Filament\Resources\BannedWordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBannedWord extends CreateRecord
{
    protected static string $resource = BannedWordResource::class;
}
