<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Synonym;
use App\Services\Admin\SynonymService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SynonymController extends Controller
{
    public function __construct(private readonly SynonymService $service) {}

    public function index(Request $request): JsonResponse
    {
        $synonyms = Synonym::active()
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($synonyms);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:equivalent,one_way'],
            'terms' => ['required_if:type,equivalent', 'array'],
            'from_term' => ['required_if:type,one_way', 'string'],
            'to_term' => ['required_if:type,one_way', 'string'],
            'is_active' => ['boolean'],
        ]);

        $synonym = $this->service->create($data);

        return response()->json($synonym, Response::HTTP_CREATED);
    }

    public function update(Request $request, Synonym $synonym): JsonResponse
    {
        $data = $request->validate([
            'type' => ['in:equivalent,one_way'],
            'terms' => ['array'],
            'from_term' => ['string'],
            'to_term' => ['string'],
            'is_active' => ['boolean'],
        ]);

        return response()->json($this->service->update($synonym, $data));
    }

    public function destroy(Synonym $synonym): Response
    {
        $this->service->delete($synonym);
        return response()->noContent();
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $content = $request->file('file')->getContent();
        $result = $this->service->bulkImport($content);

        return response()->json($result);
    }

    public function export(Request $request): Response
    {
        $csv = $this->service->exportCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="synonyms.csv"',
        ]);
    }
}
