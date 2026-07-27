<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class DailySalesReport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        //
    }
}
