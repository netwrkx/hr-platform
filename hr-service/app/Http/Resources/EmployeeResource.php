<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'last_name'  => $this->last_name,
            'salary'     => $this->salary,
            'country'    => $this->country,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // USA-specific
            'ssn'     => $this->when($this->country === 'USA', fn () => $this->maskSsn($this->ssn)),
            'address' => $this->when($this->country === 'USA', $this->address),
            // Germany-specific
            'tax_id' => $this->when($this->country === 'Germany', $this->tax_id),
            'goal'   => $this->when($this->country === 'Germany', $this->goal),
        ];
    }

    /**
     * Mask SSN for API responses: ***-**-XXXX
     */
    private function maskSsn(?string $ssn): ?string
    {
        if (!$ssn) {
            return null;
        }

        $lastFour = substr(preg_replace('/\D/', '', $ssn), -4);

        return "***-**-{$lastFour}";
    }
}
