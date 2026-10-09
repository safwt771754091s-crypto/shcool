<?php

namespace App\Models\Finance;

use App\Models\Student\Student;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment (دفعة) collected against an invoice.
 */
class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CARD = 'card';

    public const METHOD_ONLINE = 'online';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CARD,
        self::METHOD_ONLINE,
        self::METHOD_OTHER,
    ];

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'student_id',
        'amount',
        'currency',
        'method',
        'reference',
        'receipt_number',
        'paid_at',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
