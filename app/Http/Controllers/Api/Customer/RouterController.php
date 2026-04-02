<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\RouterResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RouterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routers = $request->user()
            ->routers()
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->with('plan')])
            ->latest()
            ->get();

        return RouterResource::collection($routers);
    }

    public function show(Request $request, string $id): RouterResource|JsonResponse
    {
        $router = $request->user()->routers()->find($id);

        if (! $router) {
            return response()->json(['message' => 'Router not found.'], 404);
        }

        $router->load(['subscriptions.plan']);

        return new RouterResource($router);
    }
}
