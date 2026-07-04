<?php

namespace Hybridcore\Demo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Extensions/hybridcore/demo/Admin/Index', [
            'message' => trans('demo::messages.welcome'),
        ]);
    }
}
