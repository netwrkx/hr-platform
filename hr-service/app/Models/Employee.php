<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'last_name',
        'salary',
        'country',
        // USA-specific
        'ssn',
        'address',
        // Germany-specific
        'tax_id',
        'goal',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
    ];

    /**
     * Scope: filter by country.
     */
    public function scopeCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Mask SSN for API responses: ***-**-XXXX
     */
    public function getMaskedSsnAttribute(): ?string
    {
        if (!$this->ssn) {
            return null;
        }

        $lastFour = substr(preg_replace('/\D/', '', $this->ssn), -4);

        return "***-**-{$lastFour}";
    }
}
