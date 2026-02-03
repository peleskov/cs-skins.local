<?php

namespace App\Filament\Resources\VirtualItems\Pages;

use App\Filament\Resources\VirtualItems\VirtualItemResource;
use Filament\Resources\Pages\ListRecords;

class ListVirtualItems extends ListRecords
{
    protected static string $resource = VirtualItemResource::class;
}
