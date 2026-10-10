<?php

namespace App\Garage\Http\Controllers;

use App\Garage\Actions\Invoices\ConfirmInvoiceDraft;
use App\Garage\Actions\Invoices\SaveInvoiceDraft;
use App\Garage\Actions\Invoices\StartInvoiceExtraction;
use App\Garage\Actions\Invoices\UploadInvoice;
use App\Garage\Http\Resources\InvoiceImportResource;
use App\Http\Controllers\Controller;
use App\Models\InvoiceImport;
use App\Models\Motorcycle;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceImportController extends Controller
{
    private function authorizeOwner(
        Request $request,
        Motorcycle $motorcycle,
        ?InvoiceImport $invoiceImport = null
    ): void {
        $ownerId = $this->authenticatedUser($request)->id;
        abort_unless($motorcycle->user_id === $ownerId, 404);
        abort_if(
            $invoiceImport && (
                $invoiceImport->motorcycle_id !== $motorcycle->id || $invoiceImport->user_id !== $ownerId
            ),
            404,
        );
    }

    public function index(Request $request, Motorcycle $motorcycle): AnonymousResourceCollection
    {
        $this->authorizeOwner($request, $motorcycle);

        return InvoiceImportResource::collection(
            InvoiceImport::query()->where('motorcycle_id', $motorcycle->id)->with('invoice')->latest('id')->paginate(10)
        );
    }

    public function show(Request $request, Motorcycle $motorcycle, InvoiceImport $invoiceImport): InvoiceImportResource
    {
        $this->authorizeOwner($request, $motorcycle, $invoiceImport);

        return new InvoiceImportResource($invoiceImport->load('invoice'));
    }

    public function store(Request $request, Motorcycle $motorcycle, UploadInvoice $upload): InvoiceImportResource
    {
        $this->authorizeOwner($request, $motorcycle);
        $request->validate(['file' => ['required', 'file']]);

        return new InvoiceImportResource(
            $upload($this->authenticatedUser($request), $motorcycle, $request->file('file'))
        );
    }

    public function extract(
        Request $request,
        Motorcycle $motorcycle,
        InvoiceImport $invoiceImport,
        StartInvoiceExtraction $start
    ): InvoiceImportResource {
        $this->authorizeOwner($request, $motorcycle, $invoiceImport);

        return new InvoiceImportResource($start($this->authenticatedUser($request), $invoiceImport));
    }

    public function update(
        Request $request,
        Motorcycle $motorcycle,
        InvoiceImport $invoiceImport,
        SaveInvoiceDraft $save
    ): InvoiceImportResource {
        $this->authorizeOwner($request, $motorcycle, $invoiceImport);
        $input = Input::object(
            $request->validate(['draft' => ['required', 'array'], 'version' => ['required', 'integer', 'min:0']])
        );

        return new InvoiceImportResource(
            $save($this->authenticatedUser($request), $invoiceImport, Input::object(
                $input['draft'],
                'draft'
            ), Input::integer(
                $input['version'],
                'version'
            ))
        );
    }

    public function confirm(
        Request $request,
        Motorcycle $motorcycle,
        InvoiceImport $invoiceImport,
        ConfirmInvoiceDraft $confirm
    ): InvoiceImportResource {
        $this->authorizeOwner($request, $motorcycle, $invoiceImport);
        $input = Input::object(
            $request->validate(['draft' => ['required', 'array'], 'version' => ['required', 'integer', 'min:0']])
        );

        return new InvoiceImportResource(
            $confirm($this->authenticatedUser($request), $invoiceImport, Input::object(
                $input['draft'],
                'draft'
            ), Input::integer(
                $input['version'],
                'version'
            ))->load(
                'invoice'
            )
        );
    }

    public function download(Request $request, Motorcycle $motorcycle, InvoiceImport $invoiceImport): StreamedResponse
    {
        $this->authorizeOwner($request, $motorcycle, $invoiceImport);

        $extension = match ($invoiceImport->mime) {
            'application/pdf' => '.pdf',
            'image/png' => '.png',
            default => '.jpg',
        };

        return Storage::disk('invoices')->download(
            $invoiceImport->path,
            'invoice-' . $invoiceImport->id . $extension,
            ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
