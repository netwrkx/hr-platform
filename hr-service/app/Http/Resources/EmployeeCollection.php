<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeCollection extends ResourceCollection
{
    public $collects = EmployeeResource::class;

    /**
     * Customize pagination metadata to match existing API structure.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'last_page'    => $paginated['last_page'],
                'per_page'     => $paginated['per_page'],
                'total'        => $paginated['total'],
            ],
        ];
    }
}
