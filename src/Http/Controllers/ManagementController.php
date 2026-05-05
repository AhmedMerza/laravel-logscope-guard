<?php

declare(strict_types=1);

namespace LogScopeGuard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ManagementController extends Controller
{
    public function index(Request $request): View
    {
        $logscopeInstalled = class_exists('LogScope\\LogScope');

        $prefix = $logscopeInstalled
            ? config('logscope.routes.prefix', 'logscope').'/guard'
            : config('logscope.routes.prefix', 'guard');

        return view('logscope-guard::index', [
            'logscopeInstalled' => $logscopeInstalled,
            'apiBase'           => url($prefix.'/api'),
            'logscopeUrl'       => $logscopeInstalled ? url(config('logscope.routes.prefix', 'logscope')) : null,
        ]);
    }
}
