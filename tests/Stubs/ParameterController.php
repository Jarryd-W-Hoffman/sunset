<?php

namespace Jarryd\Sunset\Tests\Stubs;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParameterController
{
    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse([
            'parameters' => $request->route()?->parameters(),
            'id' => $id,
        ]);
    }
}
