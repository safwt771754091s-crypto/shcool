<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF export defaults
    |--------------------------------------------------------------------------
    | dompdf ships without an Arabic-capable font. Point arabic_font_path at a
    | .ttf that contains Arabic glyphs (e.g. DejaVu Sans or Amiri) to render
    | Arabic reports correctly. When it is null the Latin default is used.
    */
    'pdf' => [
        'arabic_font_path' => env('EXPORT_PDF_ARABIC_FONT'),
        'default_font' => env('EXPORT_PDF_DEFAULT_FONT', 'DejaVu Sans'),
        'paper' => env('EXPORT_PDF_PAPER', 'A4'),
        'orientation' => env('EXPORT_PDF_ORIENTATION', 'portrait'),
    ],

];
