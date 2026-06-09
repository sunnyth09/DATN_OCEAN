<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'maintenance_id';

    protected $fillable = [
        'court_id', 'title', 'description', 'start_datetime',
        'end_datetime', 'status', 'created_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by', 'admin_id');
    }
}
