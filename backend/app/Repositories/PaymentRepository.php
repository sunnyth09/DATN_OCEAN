<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
    public function create(array $data)
    {
        $payment = Payment::where('order_id', $data['order_id'])
            ->where('payment_method', $data['payment_method'])
            ->first();

        if ($payment) {
            if ($payment->status === 'success') {
                return $payment;
            }

            $payment->fill($data);
            $payment->save();

            return $payment;
        }

        return Payment::create($data);
    }
}
