<?php

namespace App\Repositories;

use App\Models\AffiliateClick;

class AffiliateClickRepository
{
    public function create(array $data): AffiliateClick
    {
        return AffiliateClick::create($data);
    }

    public function countByReferrer(int $referrerId): int
    {
        return AffiliateClick::where('referrer_id', $referrerId)->count();
    }
}
