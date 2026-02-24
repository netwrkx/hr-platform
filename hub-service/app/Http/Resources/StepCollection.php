<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StepCollection extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'steps' => array_map(
                fn (array $step) => (new StepResource($step))->resolve($request),
                $this->resource
            ),
        ];
    }
}
