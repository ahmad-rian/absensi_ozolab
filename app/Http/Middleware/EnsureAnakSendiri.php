<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnakSendiri
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Student::acrossSchools()->findOrFail($request->route('anak'));
        $parent = $request->user()->parentProfile;
        abort_unless($parent && $student->parent_profile_id === $parent->id && $student->school_id === $parent->school_id, 403);
        $request->attributes->set('portalStudent', $student);

        return $next($request);
    }
}
