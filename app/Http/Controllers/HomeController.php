<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The public landing page, in English at / and in Brazilian Portuguese at
 * /pt. One template (resources/views/home.blade.php): the English copy is the
 * translation key and lang/pt_BR.json holds the Portuguese. Each language has
 * its own phone screens and hand photos (the app UI shows in that language).
 */
class HomeController extends Controller
{
    public function __invoke(string $locale = 'en'): View
    {
        app()->setLocale($locale);

        $pt = $locale === 'pt_BR';

        return view('home', [
            'htmlLang' => $pt ? 'pt-BR' : 'en',
            'ogLocale' => $pt ? 'pt_BR' : 'en_US',
            'canonical' => $pt ? url('/pt') : url('/'),
            'img' => $pt ? '/images/landing/pt/' : '/images/landing/',
            'utmSource' => $pt ? 'website_pt' : 'website',
        ]);
    }
}
