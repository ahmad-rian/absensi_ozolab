<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleLoginResponse implements LoginResponse
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return to_route($request->user()->must_change_password ? 'password.required.edit' : $request->user()->homeRoute());
    }
}
