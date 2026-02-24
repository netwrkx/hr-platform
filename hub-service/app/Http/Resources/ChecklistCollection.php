<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistCollection extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'],
            'employees' => array_map(
                fn (array $employee) => (new ChecklistResource($employee))->resolve($request),
                $this->resource['employees']
            ),
        ];
    }
}
