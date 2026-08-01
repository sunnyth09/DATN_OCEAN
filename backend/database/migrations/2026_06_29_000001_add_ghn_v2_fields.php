<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tracking_token')) {
                $table->string('tracking_token', 64)->nullable()->unique()->after('ghn_order_code');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'weight')) {
                $table->integer('weight')->default(500)->after('sold_count')->comment('Trọng lượng thực tế (gram)');
            }
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('order_status_histories', 'ghn_status')) {
                $table->string('ghn_status', 50)->nullable()->after('note');
            }
            if (! Schema::hasColumn('order_status_histories', 'source')) {
                $table->string('source', 20)->default('system')->after('ghn_status');
            }
            if (! Schema::hasColumn('order_status_histories', 'location')) {
                $table->string('location')->nullable()->after('source');
            }
            if (! Schema::hasColumn('order_status_histories', 'description')) {
                $table->text('description')->nullable()->after('location');
            }
            if (! Schema::hasColumn('order_status_histories', 'happened_at')) {
                $table->timestamp('happened_at')->nullable()->after('description');
            }
        });

        DB::table('orders')
            ->whereNull('tracking_token')
            ->orderBy('order_id')
            ->select(['order_id'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('order_id', $order->order_id)
                        ->update(['tracking_token' => hash('sha256', $order->order_id.Str::random(40))]);
                }
            }, 'order_id');

        DB::table('order_status_histories')
            ->whereNull('happened_at')
            ->update(['happened_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            foreach (['happened_at', 'description', 'location', 'source', 'ghn_status'] as $column) {
                if (Schema::hasColumn('order_status_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'weight')) {
                $table->dropColumn('weight');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tracking_token')) {
                $table->dropUnique(['tracking_token']);
                $table->dropColumn('tracking_token');
            }
        });
    }
};
