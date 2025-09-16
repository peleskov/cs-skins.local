<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Client;
use Illuminate\Console\Command;
use Exception;

class ReleaseTransactionHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:release-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release transaction holds after 7 days and credit sellers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for transactions ready for release...');

        // Находим все транзакции готовые к снятию холда
        $readyTransactions = Transaction::readyForRelease()
            ->with('client')
            ->get();

        if ($readyTransactions->isEmpty()) {
            $this->info('No transactions ready for release.');
            return Command::SUCCESS;
        }

        $this->info("Found {$readyTransactions->count()} transactions to process.");

        $completed = 0;
        $errors = 0;

        foreach ($readyTransactions as $transaction) {
            try {
                $this->info("Processing transaction #{$transaction->id} for client #{$transaction->client_id}");

                // Начисляем деньги клиенту только для типа 'sale'
                if ($transaction->type === Transaction::TYPE_SALE && $transaction->client) {
                    $transaction->client->credit($transaction->amount);
                    $this->info("Credited {$transaction->amount} to client #{$transaction->client_id}");
                }

                // Обновляем статус транзакции
                $transaction->update(['status' => Transaction::STATUS_COMPLETED]);

                $completed++;

            } catch (Exception $e) {
                $this->error("Failed to process transaction #{$transaction->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Processing completed!");
        $this->info("Released: $completed transactions");

        if ($errors > 0) {
            $this->error("Errors: $errors transactions failed");
        }

        return Command::SUCCESS;
    }
}