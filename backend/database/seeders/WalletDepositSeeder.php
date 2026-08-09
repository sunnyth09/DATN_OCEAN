<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletDepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = DB::table('users')->pluck('user_id')->toArray();

        if (empty($users)) {
            $this->command->info('No users found. Please seed users first.');

            return;
        }

        $methods = ['vnpay', 'bank_transfer'];
        $statuses = ['pending', 'completed', 'failed'];

        $deposits = [];

        // Tạo khoảng 30 records
        for ($i = 0; $i < 30; $i++) {
            $status = $statuses[array_rand($statuses)];
            $amount = rand(5, 50) * 100000; // 500k to 5tr
            $isCompleted = $status === 'completed';

            $deposits[] = [
                'user_id' => $users[array_rand($users)],
                'deposit_code' => 'DEP-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'method' => $methods[array_rand($methods)],
                'status' => $status,
                'gateway_transaction_id' => $isCompleted ? 'GTW-'.strtoupper(Str::random(8)) : null,
                'gateway_response' => json_encode(['msg' => 'Test data']),
                'completed_at' => $isCompleted ? Carbon::now()->subDays(rand(1, 30)) : null,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('wallet_deposits')->insert($deposits);

        $this->command->info('Wallet deposits seeded successfully.');
    }
}
