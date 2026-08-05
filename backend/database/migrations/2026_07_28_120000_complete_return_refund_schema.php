<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'returned_quantity')) {
                $table->integer('returned_quantity')->default(0)->after('quantity');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE orders
                MODIFY fulfillment_status ENUM(
                    'pending',
                    'confirmed',
                    'processing',
                    'packing',
                    'shipping',
                    'delivered',
                    'completed',
                    'cancelled',
                    'return_requested',
                    'return_approved',
                    'return_rejected',
                    'returning',
                    'warehouse_received',
                    'inspection_failed',
                    'inspected_ok',
                    'returned',
                    'refunded'
                ) NOT NULL DEFAULT 'pending'
            ");
        }

        Schema::table('return_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('return_requests', 'return_code')) {
                $table->string('return_code', 40)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('return_requests', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('admin_note');
            }
            if (! Schema::hasColumn('return_requests', 'inspection_note')) {
                $table->text('inspection_note')->nullable()->after('reject_reason');
            }
            if (! Schema::hasColumn('return_requests', 'return_tracking_code')) {
                $table->string('return_tracking_code', 100)->nullable()->after('inspection_note');
            }
            if (! Schema::hasColumn('return_requests', 'return_carrier')) {
                $table->string('return_carrier', 100)->nullable()->after('return_tracking_code');
            }
            if (! Schema::hasColumn('return_requests', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->after('return_carrier');
                $table->unique(['user_id', 'order_id', 'idempotency_key'], 'return_requests_user_order_idempotency_unique');
            }
            if (! Schema::hasColumn('return_requests', 'returning_at')) {
                $table->timestamp('returning_at')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('return_requests', 'warehouse_received_at')) {
                $table->timestamp('warehouse_received_at')->nullable()->after('returning_at');
            }
            if (! Schema::hasColumn('return_requests', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('warehouse_received_at');
            }
            if (! Schema::hasColumn('return_requests', 'refund_started_at')) {
                $table->timestamp('refund_started_at')->nullable()->after('inspected_at');
            }
            if (! Schema::hasColumn('return_requests', 'refund_failed_at')) {
                $table->timestamp('refund_failed_at')->nullable()->after('refund_started_at');
            }
            if (! Schema::hasColumn('return_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('refund_failed_at');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE return_requests
                MODIFY status ENUM(
                    'pending',
                    'approved',
                    'rejected',
                    'received',
                    'refunded',
                    'return_pending',
                    'return_approved',
                    'return_rejected',
                    'returning',
                    'warehouse_received',
                    'inspection_failed',
                    'inspected_ok',
                    'refunding',
                    'refund_pending',
                    'refund_failed',
                    'return_completed'
                ) NOT NULL DEFAULT 'return_pending'
            ");
        }

        DB::table('return_requests')->where('status', 'pending')->update(['status' => 'return_pending']);
        DB::table('return_requests')->where('status', 'approved')->update(['status' => 'return_approved']);
        DB::table('return_requests')->where('status', 'rejected')->update(['status' => 'return_rejected']);
        DB::table('return_requests')->where('status', 'received')->update(['status' => 'warehouse_received']);
        DB::table('return_requests')->where('status', 'refunded')->update(['status' => 'return_completed']);

        if (! Schema::hasTable('return_request_items')) {
            Schema::create('return_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items', 'order_item_id')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products', 'product_id')->nullOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants', 'variant_id')->nullOnDelete();
                $table->integer('ordered_quantity');
                $table->integer('requested_quantity');
                $table->integer('received_quantity')->default(0);
                $table->integer('qc_pass_quantity')->default(0);
                $table->integer('qc_fail_quantity')->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('refundable_amount', 12, 2)->default(0);
                $table->enum('qc_status', ['pending', 'passed', 'failed', 'partial'])->default('pending');
                $table->text('qc_note')->nullable();
                $table->timestamp('inventory_updated_at')->nullable();
                $table->timestamps();

                $table->index(['return_request_id', 'qc_status']);
                $table->index(['order_item_id']);
            });
        }

        if (! Schema::hasTable('refund_transactions')) {
            Schema::create('refund_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders', 'order_id')->cascadeOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments', 'payment_id')->nullOnDelete();
                $table->string('gateway', 50)->default('manual');
                $table->string('method', 50);
                $table->decimal('amount', 12, 2);
                $table->enum('status', ['pending', 'processing', 'success', 'failed', 'timeout'])->default('pending');
                $table->string('idempotency_key', 160)->unique();
                $table->string('gateway_refund_id', 120)->nullable();
                $table->json('gateway_response')->nullable();
                $table->text('failure_reason')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->foreignId('requested_by')->nullable()->constrained('admins', 'admin_id')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['return_request_id', 'status']);
                $table->index(['order_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
        Schema::dropIfExists('return_request_items');

        DB::statement("\n            ALTER TABLE orders\n            MODIFY fulfillment_status ENUM(\n                'pending',\n                'confirmed',\n                'processing',\n                'packing',\n                'shipping',\n                'delivered',\n                'completed',\n                'cancelled',\n                'return_requested',\n                'return_approved',\n                'return_rejected',\n                'returned',\n                'refunded'\n            ) NOT NULL DEFAULT 'pending'\n        ");

        Schema::table('return_requests', function (Blueprint $table) {
            if (Schema::hasColumn('return_requests', 'idempotency_key')) {
                $table->dropUnique('return_requests_user_order_idempotency_unique');
            }
        });

        DB::table('return_requests')->where('status', 'return_pending')->update(['status' => 'pending']);
        DB::table('return_requests')->where('status', 'return_approved')->update(['status' => 'approved']);
        DB::table('return_requests')->where('status', 'return_rejected')->update(['status' => 'rejected']);
        DB::table('return_requests')->where('status', 'warehouse_received')->update(['status' => 'received']);
        DB::table('return_requests')->where('status', 'return_completed')->update(['status' => 'refunded']);

        DB::statement("\n            ALTER TABLE return_requests\n            MODIFY status ENUM('pending', 'approved', 'rejected', 'received', 'refunded') NOT NULL DEFAULT 'pending'\n        ");

        Schema::table('return_requests', function (Blueprint $table) {
            $columns = [
                'return_code',
                'reject_reason',
                'inspection_note',
                'return_tracking_code',
                'return_carrier',
                'idempotency_key',
                'returning_at',
                'warehouse_received_at',
                'inspected_at',
                'refund_started_at',
                'refund_failed_at',
                'completed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('return_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'returned_quantity')) {
                $table->dropColumn('returned_quantity');
            }
        });
    }
};
