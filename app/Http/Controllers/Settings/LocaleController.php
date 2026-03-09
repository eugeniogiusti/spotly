<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED = ['en', 'it', 'es', 'fr', 'de', 'pt'];

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', self::SUPPORTED)],
        ]);

        $request->user()->update(['locale' => $data['locale']]);

        return redirect()->back();
    }
}
