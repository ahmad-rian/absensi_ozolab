<?php

namespace App\Http\Middleware;

use App\Models\StudioToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga endpoint Tyas Studio.
 *
 * Studio tidak punya sesi di aplikasi ini — ia aplikasi lain di subdomain lain.
 * Yang dibawanya cuma `Authorization: Bearer <token>`.
 *
 * PERINGATAN untuk siapa pun yang menambah endpoint di balik middleware ini:
 * global scope `school` membaca `auth()->user()->school_id`, dan di sini tidak
 * ada user yang login sama sekali. Scope-nya MATI. `Student::query()` akan
 * mengembalikan siswa dari semua sekolah. Setiap query wajib dibatasi sendiri
 * lewat `StudioRequest::token($request)->batasi(...)`.
 */
class AuthenticateStudioToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $mentah = $request->bearerToken();

        // Pesannya sengaja sama untuk "tidak ada token", "token ngawur", dan
        // "token sudah dicabut". Membedakannya memberi tahu penebak bahwa
        // tebakannya sudah separuh benar.
        if (! $mentah || ! ($token = StudioToken::untukMentah($mentah))) {
            return response()->json(['message' => 'Token Studio tidak berlaku.'], 401);
        }

        // Cukup sekali per menit. Tanpa penahan ini setiap permintaan — termasuk
        // polling status unggahan — menulis satu baris UPDATE.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('studio_token', $token);

        return $next($request);
    }
}
