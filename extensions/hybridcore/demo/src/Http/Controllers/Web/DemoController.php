<?php

namespace Hybridcore\Demo\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class DemoController extends Controller
{
    public function index()
    {
        return response()->json(['message' => trans('demo::messages.welcome')]);
    }
}
