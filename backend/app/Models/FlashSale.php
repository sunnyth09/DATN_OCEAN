<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    protected $table = 'flash_sales';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'status',    // draft, active, ended, cancelled
        'is_combo',  // true = bundle combo mode
        'combo_label',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_combo' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class, 'flash_sale_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    /**
     * Chỉ lấy Flash Sale đang active (trạng thái + thời gian)
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('status', 'active')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);
    }

    /**
     * Chỉ lấy Flash Sale dạng combo bundle
     */
    public function scopeCombo(Builder $query): Builder
    {
        return $query->where('is_combo', true);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isCombo(): bool
    {
        return (bool) $this->is_combo;
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        $now = Carbon::now();

        return $now->gte($this->start_time) && $now->lte($this->end_time);
    }
}
