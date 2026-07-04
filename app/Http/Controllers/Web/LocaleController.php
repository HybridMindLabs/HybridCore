<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Localization\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:10']]);

        if (! $this->locales->isSupported($data['locale'])) {
            return back()->withErrors(['locale' => 'This language is not available.']);
        }

        $request->session()->put('locale', $data['locale']);

        $request->user()?->update(['locale' => $data['locale']]);

        return back();
    }
}
