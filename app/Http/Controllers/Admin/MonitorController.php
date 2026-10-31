<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Monitor\MonitorSekolah;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pertama super admin: sekolah mana yang hari ini tidak absen sama
 * sekali, diurutkan dari yang paling butuh perhatian.
 */
class MonitorController extends Controller
{
    public function index(Request $request, MonitorSekolah $monitor): Response
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.SchoolTime::todayString()]]);

        $tanggal = $request->filled('tanggal')
            ? Carbon::createFromFormat('Y-m-d', $request->string('tanggal')->toString(), SchoolTime::timezone())->startOfDay()
            : SchoolTime::today();

        return Inertia::render('admin/monitor/index', [
            'monitor' => $monitor->untuk($tanggal),
            'hariIni' => SchoolTime::todayString(),
        ]);
    }
}
