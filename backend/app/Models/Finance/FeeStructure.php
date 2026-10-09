<?php

namespace App\Models\Finance;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\SchoolClass;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable fee definition (رسوم) that can be issued to students as invoices.
 */
class FeeStructure extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_TUITION = 'tuition';

    public const TYPE_REGISTRATION = 'registration';

    public const TYPE_TRANSPORT = 'transport';

    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_TUITION,
        self::TYPE_REGISTRATION,
        self::TYPE_TRANSPORT,
        self::TYPE_ACTIVITY,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'school_class_id',
        'name',
        'type',
        'amount',
        'currency',
        'due_on',
        'period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
