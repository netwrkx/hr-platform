<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCollection extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'columns' => $this->resource['columns'],
            'data' => array_map(
                fn (array $employee) => (new EmployeeResource($employee))->resolve($request),
                $this->resource['data']
            ),
            'pagination' => $this->resource['pagination'],
        ];
    }
}
