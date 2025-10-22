<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudioBooking extends Model
{
    use HasFactory;

    protected $table = 'tbl_studio_bookings';

    protected $fillable = [
        'request_id',
        'title',
        'description',
        'project_type',
        'custom_project_type',
        'requester_id',
        'direct_manager_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_hours',
        'time_preference',
        'equipment_needed',
        'additional_services',
        'crew_size',
        'client_name',
        'client_phone',
        'client_email',
        'business_name',
        'business_type',
        'client_agreed',
        'agreement_date',
        'special_notes',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'equipment_needed' => 'array',
        'additional_services' => 'array',
        'client_agreed' => 'boolean',
        'agreement_date' => 'datetime',
        'duration_hours' => 'decimal:2',
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
}
