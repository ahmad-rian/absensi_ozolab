<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Session-based school selection takes priority over user.school_id
        $schoolId = session('current_school_id', $user->school_id);

        // Ensure schoolId is valid and belongs to an active school
        $school = $schoolId ? School::where('id', $schoolId)->where('is_active', true)->first() : null;

        if ($school) {
            // Sync session + user model so all scopes read the same value
            session(['current_school_id' => $school->id]);

            if ($user->school_id !== $school->id) {
                $user->school_id = $school->id;
                $user->saveQuietly(); // no events, just sync
            }

            app()->instance('currentSchool', $school);
        }

        return $next($request);
    }
}
