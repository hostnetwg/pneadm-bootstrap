<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->string('ifirma_invoice_id', 64)->nullable()->after('ksef_number');
        });

        DB::table('debt_cases')
            ->select(['id', 'form_order_id', 'invoice_number', 'ksef_number'])
            ->orderBy('id')
            ->chunkById(200, function ($cases): void {
                $orderIds = $cases->pluck('form_order_id')->filter()->unique()->all();
                if ($orderIds === []) {
                    return;
                }

                $orders = DB::table('form_orders')
                    ->whereIn('id', $orderIds)
                    ->get(['id', 'invoice_number', 'ksef_number', 'ifirma_invoice_id'])
                    ->keyBy('id');

                foreach ($cases as $case) {
                    $order = $orders->get($case->form_order_id);
                    if ($order === null) {
                        continue;
                    }

                    $caseInvoice = $this->invoice($case->invoice_number);
                    $orderInvoice = $this->invoice($order->invoice_number);
                    if ($caseInvoice === null || $caseInvoice !== $orderInvoice) {
                        continue;
                    }

                    $updates = [];
                    $orderId = trim((string) ($order->ifirma_invoice_id ?? ''));
                    if ($orderId !== '') {
                        $updates['ifirma_invoice_id'] = $orderId;
                    }

                    $caseKsef = trim((string) ($case->ksef_number ?? ''));
                    $orderKsef = trim((string) ($order->ksef_number ?? ''));
                    if ($caseKsef === '' && $orderKsef !== '') {
                        $updates['ksef_number'] = $orderKsef;
                    }

                    if ($updates !== []) {
                        DB::table('debt_cases')->where('id', $case->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->dropColumn('ifirma_invoice_id');
        });
    }

    private function invoice(mixed $value): ?string
    {
        $value = trim(str_replace("\u{00A0}", '', (string) $value));

        if ($value === '' || $value === '0') {
            return null;
        }

        return $value;
    }
};
