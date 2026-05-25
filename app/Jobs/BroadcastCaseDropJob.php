<?php

namespace App\Jobs;

use App\Events\CaseDropEvent;
use App\Models\CaseOpen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastCaseDropJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $caseOpenId)
    {
    }

    public function handle(): void
    {
        $caseOpen = CaseOpen::find($this->caseOpenId);

        if (! $caseOpen) {
            return;
        }

        broadcast(new CaseDropEvent($caseOpen));
    }
}
