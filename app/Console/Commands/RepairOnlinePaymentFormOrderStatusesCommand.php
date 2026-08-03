<?php

namespace App\Console\Commands;

use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairOnlinePaymentFormOrderStatusesCommand extends Command
{
    protected $signature = 'form-orders:repair-online-payment-statuses
                            {--apply : Zapisuje korektę; bez tej opcji działa jako dry-run}
                            {--limit= : Maksymalna liczba rekordów do pokazania/zapisania}';

    protected $description = 'Koryguje form_orders.payment_status dla zamówień online, które mają opłaconą próbę płatności';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $rows = $this->candidateRows($limit);

        $this->warn($apply
            ? 'Tryb zapisu (--apply): zostanie zmienione wyłącznie form_orders.payment_status.'
            : 'Dry-run: nic nie zostanie zapisane. Dodaj --apply po akceptacji listy.');
        $this->info('Kryterium: online_gateway + payment_status != paid + istnieje online_payment_orders.status = paid.');
        $this->info('Do korekty: '.$rows->count().' zamówień.');

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['FormOrder ID', 'Ident', 'Kurs', 'E-mail', 'Status', 'FV', 'Paid OPO', 'Próby'],
            $rows->take(50)->map(fn ($row): array => [
                $row->id,
                $row->ident,
                $row->product_id,
                Str::limit((string) $row->orderer_email, 35),
                $row->payment_status,
                $row->invoice_number ?: '—',
                $row->paid_online_payment_order_id,
                Str::limit((string) $row->payment_attempts, 80),
            ])->all()
        );

        if ($rows->count() > 50) {
            $this->line('... i '.($rows->count() - 50).' kolejnych.');
        }

        $ids = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->line('FormOrder IDs: '.implode(', ', $ids));

        if (! $apply) {
            return self::SUCCESS;
        }

        $logPath = storage_path('logs/form-orders-online-payment-status-repair-'.now()->format('Y-m-d_His').'.txt');
        $logLines = ["form_order_id\told_payment_status\tnew_payment_status\tpaid_online_payment_order_id\tattempts\n"];
        foreach ($rows as $row) {
            $logLines[] = implode("\t", [
                $row->id,
                $row->payment_status,
                FormOrder::PAYMENT_STATUS_PAID,
                $row->paid_online_payment_order_id,
                $row->payment_attempts,
            ])."\n";
        }
        file_put_contents($logPath, implode('', $logLines));

        $updated = 0;
        DB::transaction(function () use ($ids, &$updated): void {
            $updated = DB::table('form_orders')
                ->whereIn('id', $ids)
                ->where('payment_mode', FormOrder::PAYMENT_MODE_ONLINE_GATEWAY)
                ->where(function ($query): void {
                    $query->whereNull('payment_status')
                        ->orWhere('payment_status', '!=', FormOrder::PAYMENT_STATUS_PAID);
                })
                ->whereExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('online_payment_orders')
                        ->whereColumn('online_payment_orders.form_order_id', 'form_orders.id')
                        ->where('online_payment_orders.status', OnlinePaymentOrder::STATUS_PAID);
                })
                ->update([
                    'payment_status' => FormOrder::PAYMENT_STATUS_PAID,
                    'updated_at' => now(),
                ]);
        });

        $this->info("Zaktualizowano zamówień: {$updated}.");
        $this->info("Log korekty: {$logPath}");

        return self::SUCCESS;
    }

    private function candidateRows(?int $limit)
    {
        $query = DB::table('form_orders as fo')
            ->join('online_payment_orders as opo', 'opo.form_order_id', '=', 'fo.id')
            ->where('fo.payment_mode', FormOrder::PAYMENT_MODE_ONLINE_GATEWAY)
            ->where(function ($query): void {
                $query->whereNull('fo.payment_status')
                    ->orWhere('fo.payment_status', '!=', FormOrder::PAYMENT_STATUS_PAID);
            })
            ->groupBy(
                'fo.id',
                'fo.ident',
                'fo.product_id',
                'fo.orderer_email',
                'fo.payment_status',
                'fo.invoice_number'
            )
            ->havingRaw('MAX(CASE WHEN opo.status = ? THEN opo.id END) IS NOT NULL', [OnlinePaymentOrder::STATUS_PAID])
            ->select([
                'fo.id',
                'fo.ident',
                'fo.product_id',
                'fo.orderer_email',
                'fo.payment_status',
                'fo.invoice_number',
                DB::raw("MAX(CASE WHEN opo.status = '".OnlinePaymentOrder::STATUS_PAID."' THEN opo.id END) as paid_online_payment_order_id"),
                DB::raw("GROUP_CONCAT(CONCAT(opo.ident, ':', opo.status) ORDER BY opo.id SEPARATOR ', ') as payment_attempts"),
            ])
            ->orderByDesc('fo.id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
