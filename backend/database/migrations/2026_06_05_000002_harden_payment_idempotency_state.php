<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingPostPaymentColumns = array_filter([
            'post_payment_status' => ! Schema::hasColumn('payments', 'post_payment_status'),
            'post_payment_started_at' => ! Schema::hasColumn('payments', 'post_payment_started_at'),
            'post_payment_last_error' => ! Schema::hasColumn('payments', 'post_payment_last_error'),
        ]);

        if ($missingPostPaymentColumns !== []) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'post_payment_status')) {
                    $table->string('post_payment_status', 20)->nullable()->after('post_payment_key');
                }

                if (! Schema::hasColumn('payments', 'post_payment_started_at')) {
                    $table->dateTime('post_payment_started_at')->nullable()->after('post_payment_status');
                }

                if (! Schema::hasColumn('payments', 'post_payment_last_error')) {
                    $table->text('post_payment_last_error')->nullable()->after('post_payment_source');
                }
            });
        }

        $this->deduplicatePayments();

        if (! $this->hasIndex('payments', 'payments_order_id_payment_method_unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['order_id', 'payment_method'], 'payments_order_id_payment_method_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('payments', 'payments_order_id_payment_method_unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_order_id_payment_method_unique');
            });
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('payments', 'post_payment_status') ? 'post_payment_status' : null,
            Schema::hasColumn('payments', 'post_payment_started_at') ? 'post_payment_started_at' : null,
            Schema::hasColumn('payments', 'post_payment_last_error') ? 'post_payment_last_error' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('payments', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('$tableName')"))
                ->contains(fn ($idx) => $idx->name === $indexName);
        }

        return DB::selectOne(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$tableName, $indexName]
        ) !== null;
    }

    private function deduplicatePayments(): void
    {
        $duplicateGroups = DB::table('payments')
            ->select('order_id', 'payment_method', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('order_id', 'payment_method')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $payments = DB::table('payments')
                ->where('order_id', $group->order_id)
                ->where('payment_method', $group->payment_method)
                ->orderByRaw("
                    CASE status
                        WHEN 'success' THEN 0
                        WHEN 'pending' THEN 1
                        WHEN 'failed' THEN 2
                        WHEN 'refunded' THEN 3
                        ELSE 4
                    END
                ")
                ->orderByDesc('confirmed_at')
                ->orderByDesc('paid_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('payment_id')
                ->get();

            $paymentToKeep = $payments->first();
            $duplicateIds = $payments->skip(1)->pluck('payment_id')->all();

            if ($paymentToKeep && $duplicateIds !== []) {
                DB::table('payments')
                    ->whereIn('payment_id', $duplicateIds)
                    ->delete();
            }
        }
    }
};
