<?php

namespace App\Garage\Actions\Invoices;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Enums\EvidenceKind;
use App\Garage\Exceptions\GarageInvoiceStorageException;
use App\Models\EvidenceDocument;
use App\Models\InvoiceImport;
use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Illuminate\Validation\ValidationException;

class UploadInvoice
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    public function __invoke(User $owner, Motorcycle $bike, UploadedFile $file): InvoiceImport
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        if (! ($bike->user_id === $owner->id)) {
            throw new ModelNotFoundException();
        }
        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240']]
        )->validate();
        $size = $file->getSize();
        if ($size === false) {
            throw GarageInvoiceStorageException::writeFailed();
        }
        $directory = 'invoices/' . $owner->id;
        $filename = $file->hashName();
        $path = $directory . '/' . $filename;
        try {
            return DB::transaction(
                function () use ($owner, $bike, $file, $path, $directory, $filename, $size): InvoiceImport {
                    $lockedOwner = User::query()->lockForUpdate()->findOrFail($owner->id);
                    if (! $lockedOwner->hasVerifiedEmail()) {
                        throw new AuthorizationException();
                    }
                    Motorcycle::query()->where('user_id', $lockedOwner->id)->lockForUpdate()->findOrFail($bike->id);
                    $documents = EvidenceDocument::query()->where('user_id', $lockedOwner->id)
                        ->lockForUpdate()->get(['id', 'size']);
                    $usedBytes = 0;
                    foreach ($documents as $document) {
                        $usedBytes += $document->size;
                    }
                    if (
                        $documents->count() >= Config::integer('garage.invoice_storage.max_documents')
                        || $usedBytes + $size > Config::integer(
                            'garage.invoice_storage.max_bytes'
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'file' => 'Your account document storage limit has been reached.',
                        ]);
                    }
                    if (! $file->storeAs($directory, $filename, ['disk' => 'invoices', 'visibility' => 'private'])) {
                        throw GarageInvoiceStorageException::writeFailed();
                    }
                    $import = InvoiceImport::query()->create([
                        'user_id' => $owner->id,
                        'motorcycle_id' => $bike->id,
                        'path' => $path,
                        'filename' => mb_substr(
                            basename(
                                str_replace('\\', '/', $file->getClientOriginalName())
                            ),
                            0,
                            255
                        ),
                        'mime' => $file->getMimeType(),
                        'size' => $size,
                        'sha256' => hash_file(
                            'sha256',
                            $file->getPathname()
                        ),
                        'status' => 'uploaded',
                    ]);
                    $document = new EvidenceDocument();
                    $document->forceFill([
                        'user_id' => $owner->id,
                        'uploaded_by_id' => $owner->id,
                        'kind' => EvidenceKind::Invoice,
                        'disk' => 'invoices',
                        'path' => $import->path,
                        'filename' => $import->filename,
                        'mime' => $import->mime,
                        'size' => $import->size,
                        'sha256' => $import->sha256,
                    ])->save();
                    $import->evidenceDocument()->associate($document);
                    $import->save();
                    ($this->recordUserActivity)(
                        $owner,
                        $owner->id,
                        ActivityEvent::InvoiceUploaded,
                        $import->id,
                        force: true
                    );

                    return $import;
                }
            );
        } catch (Throwable $exception) {
            Storage::disk('invoices')->delete($path);
            throw $exception;
        }
    }
}
