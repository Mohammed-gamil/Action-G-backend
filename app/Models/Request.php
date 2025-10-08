<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'requester_id',
        'direct_manager_id',
        'title',
        'description',
        'type',
    'category',
    'location',
        'desired_cost',
        'currency',
    'needed_by_date',
    'start_time',
    'end_time',
        'state',
        'current_approver_id',
        'payout_channel',
        'payout_reference',
    'funds_transferred_at',
    'active_from',
        'selected_quote_id',
        'client_name',
        'project_description',
        'total_cost',
        'total_benefit',
        'total_price',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'funds_transferred_at' => 'datetime',
        'active_from' => 'datetime',
        'desired_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'total_benefit' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Relationships
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function directManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_manager_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RequestQuote::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function selectedQuote(): BelongsTo
    {
        return $this->belongsTo(RequestQuote::class, 'selected_quote_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(RequestInventoryItem::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }

    public function scopePendingApproval($query)
    {
        return $query->whereIn('state', ['SUBMITTED', 'DM_APPROVED']);
    }

    public function scopeForApprover($query, $userId, $role)
    {
        return $query->where(function ($q) use ($userId, $role) {
            switch ($role) {
                case 'DIRECT_MANAGER':
                    // Pool for DMs: show all SUBMITTED requests
                    // Pool for DMs: show all SUBMITTED requests (purchases only)
                    $q->where('state', 'SUBMITTED')->where('type', 'purchase');
                    break;
                case 'FINAL_MANAGER':
                    // Pool for Final Managers: show all DM_APPROVED requests with quotes
                    // Final Managers:
                    // - For purchases: DM_APPROVED with quotes
                    // - For projects: SUBMITTED directly
                    $q->where(function ($qq) {
                        $qq->where(function ($p) {
                            $p->where('type', 'purchase')
                              ->where('state', 'DM_APPROVED')
                              ->whereHas('quotes');
                        })->orWhere(function ($p) {
                            $p->where('type', 'project')
                              ->where('state', 'SUBMITTED');
                        });
                    });
                    break;
                case 'ACCOUNTANT':
                    // Accountants:
                    // - For purchases: DM_APPROVED (to add quotes)
                    // - For projects: DONE (to confirm client payment)
                    $q->where(function ($qq) {
                        $qq->where(function ($p) {
                            $p->where('type', 'purchase')->where('state', 'DM_APPROVED');
                        })->orWhere(function ($p) {
                            $p->where('type', 'project')->where('state', 'DONE');
                        });
                    });
                    break;
            }
        });
    }

    // Helper methods
    public function isPurchaseRequest(): bool
    {
        return $this->type === 'purchase';
    }

    public function isProjectRequest(): bool
    {
        return $this->type === 'project';
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->requester_id === $user->id && in_array($this->state, ['DRAFT']);
    }

    public function canBeApprovedBy(User $user): bool
    {
        if (!$user->canApproveRequests()) {
            return false;
        }

        switch ($user->role) {
            case 'DIRECT_MANAGER':
                // Pool visibility, but enforce chosen DM if set
                if (!($this->type === 'purchase' && $this->state === 'SUBMITTED')) {
                    return false;
                }
                if (!is_null($this->direct_manager_id)) {
                    return $user->id === $this->direct_manager_id || $user->isAdmin();
                }
                return true;
            case 'FINAL_MANAGER':
                // Final Managers:
                // - Purchases: approve DM_APPROVED with quotes
                // - Projects: approve SUBMITTED directly
                if ($this->type === 'purchase') {
                    return $this->state === 'DM_APPROVED' && $this->quotes()->exists();
                }
                if ($this->type === 'project') {
                    return $this->state === 'SUBMITTED';
                }
                return false;
            case 'ACCOUNTANT':
                // Accountants can only add quotes, not approve (approval moved to Final Manager)
                return false;
            case 'ADMIN':
                return in_array($this->state, ['SUBMITTED', 'DM_APPROVED']);
        }

        return false;
    }

    public function canBeRejectedBy(User $user): bool
    {
        if (!$user->canApproveRequests()) {
            return false;
        }

        switch ($user->role) {
            case 'DIRECT_MANAGER':
                if (!($this->type === 'purchase' && $this->state === 'SUBMITTED')) {
                    return false;
                }
                if (!is_null($this->direct_manager_id)) {
                    return $user->id === $this->direct_manager_id || $user->isAdmin();
                }
                return true;
            case 'FINAL_MANAGER':
                // FM can reject:
                // - Purchases at DM_APPROVED
                // - Projects at SUBMITTED
                return ($this->type === 'purchase' && $this->state === 'DM_APPROVED')
                    || ($this->type === 'project' && $this->state === 'SUBMITTED');
            case 'ACCOUNTANT':
                // Accountants can still reject during their quote management phase
                return $this->type === 'purchase' && $this->state === 'DM_APPROVED';
            case 'ADMIN':
                return in_array($this->state, ['SUBMITTED', 'DM_APPROVED']);
        }

        return false;
    }

    public function canAddQuotes(User $user): bool
    {
        // Only accountants can add quotes, and only when request is DM_APPROVED
        return $user->role === 'ACCOUNTANT' && $this->state === 'DM_APPROVED';
    }

    public function canSelectQuote(User $user): bool
    {
        // Only Final Managers can select quotes, and only when request is DM_APPROVED with quotes
        return $user->role === 'FINAL_MANAGER' && 
               $this->state === 'DM_APPROVED' && 
               $this->quotes()->exists();
    }

    public function getNextApprovalState(string $decision): string
    {
        if ($decision === 'REJECTED') {
            switch ($this->state) {
                case 'SUBMITTED':
                    // For projects, FM rejects directly from SUBMITTED
                    return $this->type === 'project' ? 'FINAL_REJECTED' : 'DM_REJECTED';
                case 'DM_APPROVED':
                    // Check if rejector is Final Manager or Accountant
                    return 'FINAL_REJECTED';
            }
        }

        if ($decision === 'APPROVED') {
            switch ($this->state) {
                case 'SUBMITTED':
                    // For projects, FM approval from SUBMITTED moves to PROCESSING
                    return $this->type === 'project' ? 'PROCESSING' : 'DM_APPROVED';
                case 'DM_APPROVED':
                    // Final Manager approval leads to FINAL_APPROVED
                    return 'FINAL_APPROVED';
            }
        }

        return $this->state;
    }

    public function getTotalItemsCost(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }

    // Boot: ensure request_id assigned via atomic counter
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (!$request->request_id) {
                $request->request_id = static::nextRequestId($request->type);
            }
        });
    }

    /**
     * Generate the next unique request_id using a per-type, per-year counter under lock.
     */
    public static function nextRequestId(string $type): string
    {
        $prefix = $type === 'project' ? 'PROJ' : 'PR';
        $year = now()->year;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($type, $year, $prefix) {
            // Try to lock existing counter row for this type/year
            $counter = \App\Models\RequestCounter::where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                // No counter yet: initialize it from the current max existing request_id to avoid collisions
                // Example format: PROJ-2025-001 => extract the numeric suffix and get the max
                $maxExisting = \Illuminate\Support\Facades\DB::table('requests')
                    ->where('type', $type)
                    ->whereYear('created_at', $year)
                    ->where('request_id', 'like', $prefix.'-'.$year.'-%')
                    ->selectRaw("MAX(CAST(SUBSTRING_INDEX(request_id, '-', -1) AS UNSIGNED)) as max_seq")
                    ->value('max_seq');
                $initialSeq = (int) ($maxExisting ?? 0);

                try {
                    $counter = \App\Models\RequestCounter::create([
                        'type' => $type,
                        'year' => $year,
                        'seq' => $initialSeq,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Another transaction created it concurrently; fetch and lock it now
                    $counter = \App\Models\RequestCounter::where('type', $type)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();
                }
            }

            // Increment and persist, then format the id
            $counter->seq = ($counter->seq ?? 0) + 1;
            $counter->save();

            // Keep 3-digit padding for now; larger numbers will naturally expand
            return sprintf('%s-%d-%03d', $prefix, $year, $counter->seq);
        });
    }
}
