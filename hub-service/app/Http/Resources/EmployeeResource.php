<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        if (($data['country'] ?? '') === 'USA' && isset($data['ssn'])) {
            $data['ssn'] = self::maskSsn($data['ssn']);
        }

        return $data;
    }

    /**
     * Mask SSN to ***-**-XXXX format, showing only last 4 digits.
     */
    public static function maskSsn(?string $ssn): ?string
    {
        if ($ssn === null) {
            return null;
        }

        if ($ssn === '') {
            return '';
        }

        $last4 = substr($ssn, -4);

        return "***-**-{$last4}";
    }
}
