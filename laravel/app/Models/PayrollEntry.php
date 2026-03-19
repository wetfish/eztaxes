<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    protected $fillable = [
        'tax_year_id',
        'payroll_import_id',
        'type',
        'name',
        'is_officer',
        'date',
        'gross_pay',
        'employee_deductions',
        'employer_contributions',
        'employee_taxes',
        'employer_taxes',
        'net_pay',
        'employer_cost',
        'check_amount',
        'wage_type',
        'currency',
        'foreign_amount',
        'payment_status',
        'hours',
        'hourly_rate',
        'department',
        'tips_payment',
        'tips_cash',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_officer' => 'boolean',
            'gross_pay' => 'decimal:2',
            'employee_deductions' => 'decimal:2',
            'employer_contributions' => 'decimal:2',
            'employee_taxes' => 'decimal:2',
            'employer_taxes' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'employer_cost' => 'decimal:2',
            'check_amount' => 'decimal:2',
            'foreign_amount' => 'decimal:2',
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'tips_payment' => 'decimal:2',
            'tips_cash' => 'decimal:2',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class);
    }

    public function payrollImport(): BelongsTo
    {
        return $this->belongsTo(PayrollImport::class);
    }
}