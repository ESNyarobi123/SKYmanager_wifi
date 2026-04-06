<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routerIds = $request->user()->routers()->pluck('id');

        $subscriptions = Subscription::whereIn('router_id', $routerIds)
            ->with(['plan', 'router'])
            ->latest()
            ->paginate(20);

        return SubscriptionResource::collection($subscriptions);
    }
}
