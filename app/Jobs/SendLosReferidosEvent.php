<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendLosReferidosEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        protected string $url,
        protected int $referralId,
        protected int $partnerId,
        protected string $goalName,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $attempt = $this->attempts();

        Log::channel('losreferidos')->info('Отправка события в LR', [
            'attempt' => $attempt,
            'referral_id' => $this->referralId,
            'partner_id' => $this->partnerId,
            'goal_name' => $this->goalName,
            'url' => $this->url,
        ]);

        try {
            $response = Http::timeout(30)->get($this->url);

            if ($response->successful()) {
                Log::channel('losreferidos')->info('Событие успешно доставлено в LR', [
                    'attempt' => $attempt,
                    'referral_id' => $this->referralId,
                    'partner_id' => $this->partnerId,
                    'goal_name' => $this->goalName,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            } else {
                Log::channel('losreferidos')->warning('LR вернул ошибку', [
                    'attempt' => $attempt,
                    'referral_id' => $this->referralId,
                    'partner_id' => $this->partnerId,
                    'goal_name' => $this->goalName,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                throw new \Exception("LR API returned status {$response->status()}");
            }
        } catch (\Exception $e) {
            Log::channel('losreferidos')->error('Ошибка отправки события в LR', [
                'attempt' => $attempt,
                'referral_id' => $this->referralId,
                'partner_id' => $this->partnerId,
                'goal_name' => $this->goalName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('losreferidos')->error('Событие не доставлено в LR после всех попыток', [
            'referral_id' => $this->referralId,
            'partner_id' => $this->partnerId,
            'goal_name' => $this->goalName,
            'url' => $this->url,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->tries,
        ]);
    }
}
