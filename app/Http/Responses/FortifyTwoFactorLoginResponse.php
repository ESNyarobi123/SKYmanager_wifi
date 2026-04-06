<?php

namespace App\Http\Responses;

use App\Support\PostAuthRedirect;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FortifyTwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->to(PostAuthRedirect::url($request));
    }
}
