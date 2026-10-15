<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\KonteksGeneral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ganti konteks workspace general dari pemilih di kanan atas. Memilih
 * program sekaligus mengikat penyelenggaranya, lalu membuka dashboard program.
 */
class KonteksController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $konteks = $request->validate(['konteks' => ['required', 'string', 'max:40']])['konteks'];

        if ($konteks === KonteksGeneral::HAJI_LAMA) {
            $request->session()->put(KonteksGeneral::SESI, KonteksGeneral::HAJI_LAMA);

            return Inertia::location('/kartu-bebas');
        }

        $program = Program::acrossPenyelenggara()->findOrFail($konteks);
        $request->session()->put(KonteksGeneral::SESI, $program->id);
        $request->session()->put('current_penyelenggara_id', $program->penyelenggara_id);

        return Inertia::location(route('penyelenggara.program.dashboard', $program));
    }
}
