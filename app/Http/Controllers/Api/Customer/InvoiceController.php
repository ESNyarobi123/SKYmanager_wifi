<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = $request->user()
            ->invoices()
            ->latest()
            ->paginate(20);

        return InvoiceResource::collection($invoices);
    }
}
