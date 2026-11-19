<?php

namespace App\Modules\Imports;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Settings\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-imports');

        return ImportResource::collection(Import::with('uploader')->latest('id')->paginate(20));
    }

    public function store(Request $request, ImportRunner $runner): ImportResource
    {
        Gate::authorize('manage-imports');
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xls,xlsx']]);

        $file = $request->file('file');
        $import = Import::create([
            'source' => LeadSource::ManualUpload,
            'file' => $file->store('imports', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        try {
            $runner->prepare($import);
        } catch (Throwable) {
            Storage::disk('local')->delete($import->file);
            $import->delete();
            throw ValidationException::withMessages(['file' => 'This file could not be read. Upload a CSV or Excel (.xlsx/.xls) file.']);
        }

        return (new ImportResource($import->load('uploader')))->detailed();
    }

    public function show(Import $import): ImportResource
    {
        Gate::authorize('manage-imports');

        return (new ImportResource($import->load('uploader', 'message')))->detailed();
    }

    public function run(Request $request, Import $import, ImportRunner $runner): ImportResource
    {
        Gate::authorize('manage-imports');
        abort_unless($import->canRun(), 409, 'This import has already been processed.');

        $headers = $import->headers ?? [];
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.phone' => ['required', 'string', Rule::in($headers)],
            'mapping.*' => ['nullable', 'string', Rule::in($headers)],
            'save_mapping' => ['boolean'],
        ], ['mapping.phone.required' => 'Choose the column that holds the phone number.']);

        $mapping = array_filter(
            array_intersect_key($data['mapping'], array_flip(ColumnMapping::keys())),
            fn ($header) => filled($header),
        );

        if ($request->boolean('save_mapping')) {
            Setting::put('import_column_mapping', $mapping);
        }

        $runner->run($import, $mapping);

        return (new ImportResource($import->load('uploader', 'message')))->detailed();
    }
}
