<?php

namespace App\Models\Finance;

use App\Models\Student\Student;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An invoice (فاتورة) issued to a student for one or more fees.
 *
 * paid_amount, balance and status are always recomputed from the payments, so
 * they cannot drift out of sync with the money actually collected.
 */
class Invoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'student_id',
        'number',
        'title',
        'amount',
        'discount',
        'net_amount',
        'paid_amount',
        'balance',
        'currency',
        'status',
        'issued_on',
        'due_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'issued_on' => 'date',
            'due_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
