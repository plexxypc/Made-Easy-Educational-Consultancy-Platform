<?php

namespace App\Http\Controllers;

use App\Actions\Documents\OpenOrderDocument;
use App\Models\OrderDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only HTTP route through which order documents leave the private disk.
 */
class OrderDocumentController extends Controller
{
    /**
     * Stream a document inline after authorising the viewer. Staff/admin views
     * are audited inside OpenOrderDocument.
     *
     * @param  Request  $request
     * @param  OrderDocument  $order_document
     * @param  OpenOrderDocument  $open_order_document
     * @return StreamedResponse
     */
    public function show(Request $request, OrderDocument $order_document, OpenOrderDocument $open_order_document): StreamedResponse
    {
        Gate::authorize('view', $order_document);

        return $open_order_document->handle(
            $order_document,
            $request->user(),
            $request->ip(),
            as_download: $request->boolean('download'),
        );
    }
}
