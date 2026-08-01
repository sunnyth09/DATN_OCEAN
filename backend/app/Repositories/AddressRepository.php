<?php

namespace App\Repositories;

use App\Models\Address;

class AddressRepository
{
    public function findUserAddress(int $userId, int $addressId)
    {
        return Address::where('address_id', $addressId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data)
    {
        return Address::create($data);
    }
}
