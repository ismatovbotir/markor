<?php

namespace App\Http\Controllers\Printing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelImage;
use App\Domain\Printing\Services\LabelPdfService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * One label of a product, without a marking code (the code area shows the Markor logo).
 * Every label of a product is the same: the client sets the number of copies in the print dialog.
 */
class ProductLabelController extends Controller
{
    public function pdf(Request $request, Product $product, LabelPdfService $service)
    {
        Gate::authorize('printLabel', $product);
        $template = $this->template($request);
        $pdf = $service->productLabelPdf($template, $product, app(LabelImage::class)->companyLogo($product->company));
        $name = Str::slug($product->name ?: $product->gtin, '_') ?: 'label';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$name}.pdf\"",
        ]);
    }

    /** Browser print page with the one label; the system print dialog opens by itself. */
    public function print(Request $request, Product $product, LabelPdfService $service)
    {
        Gate::authorize('printLabel', $product);
        $template = $this->template($request);
        [$css, $label] = $service->productLabelHtml($template, $product, app(LabelImage::class)->companyLogo($product->company));

        return response()->view('studio.print', [
            'pages'  => [[$label]],
            'css'    => $css,
            'width'  => (float) $template->width_mm,
            'height' => (float) $template->height_mm,
            'gap'    => 0,
            'pageW'  => (float) $template->width_mm,
            'from'   => 1,
            'to'     => 1,
            'total'  => 1,
        ]);
    }

    /** A template the client may use (the global scope hides other companies' private ones). */
    private function template(Request $request): LabelTemplate
    {
        return $request->filled('template') ? LabelTemplate::findOrFail($request->integer('template')) : LabelTemplate::default();
    }
}
