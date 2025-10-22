<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryRequest extends Model
{
    use HasFactory;

    protected $table = 'tbl_inventory_requests';

    protected $fillable = [
        'request_id',
        'title',
        'description',
        'requester_id',
        'direct_manager_id',
        'warehouse_manager_id',
        'status',
        'rejection_reason',
        // Employee Information
        'employee_name',
        'employee_position',
        'employee_phone',
        // Exit Details
        'exit_purpose',
        'custom_exit_purpose',
        'client_entity_name',
        'shoot_location',
        'exit_duration_from',
        'exit_duration_to',
        // Return Tracking
        'return_date',
        'return_supervisor_name',
        'return_supervisor_phone',
        'equipment_condition_on_return',
        'supervisor_notes',
        'returned_by_employee',
    ];

    protected $casts = [
        'exit_duration_from' => 'datetime',
        'exit_duration_to' => 'datetime',
        'return_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function directManager()
    {
        return $this->belongsTo(User::class, 'direct_manager_id');
    }

    public function warehouseManager()
    {
        return $this->belongsTo(User::class, 'warehouse_manager_id');
    }

    public function items()
    {
        return $this->hasMany(InventoryRequestItem::class, 'inventory_request_id');
    }
}
