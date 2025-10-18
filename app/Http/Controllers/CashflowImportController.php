<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashflowImport\ImportPasteRequest;
use App\Http\Requests\CashflowImport\ImportProcessRequest;
use App\Http\Requests\CashflowImport\ImportUploadRequest;
use App\Services\CashflowImport\CashflowImportProcessor;
use App\Services\CashflowImport\DatasetAnalyzer;
use App\Services\CashflowImport\ImportSessionManager;
use App\Services\CashflowImport\SpreadsheetReader;
use App\Services\CashflowImport\ClipboardParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CashflowImportController extends Controller
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly DatasetAnalyzer $analyzer,
        private readonly ImportSessionManager $sessions,
        private readonly CashflowImportProcessor $processor,
        private readonly ClipboardParser $clipboard,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $categories = $user->categories()
            ->orderBy('name')
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
            ]);

        $cashFlowSources = $user->cashFlowSources()
            ->orderBy('name')
            ->get()
            ->map(fn ($source) => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'icon' => $source->icon,
                'allows_refunds' => $source->allows_refunds,
            ]);

        return Inertia::render('Cashflow/Import', [
            'categories' => $categories,
            'cashFlowSources' => $cashFlowSources,
            'maxUploadSizeMb' => 20,
        ]);
    }

    public function upload(ImportUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');

        $storedFileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $storedPath = $file->storeAs(
            sprintf('cashflow_import_uploads/%d', $user->id),
            $storedFileName
        );

        $absolutePath = Storage::disk('local')->path($storedPath);

        $spreadsheet = $this->reader->read($absolutePath);
        $analysis = $this->analyzer->analyze($spreadsheet['rows'], $spreadsheet['total_columns']);

        $payload = [
            'file' => [
                'name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'stored_path' => $storedPath,
            ],
            'meta' => [
                'total_rows' => $spreadsheet['total_rows'],
                'total_columns' => $spreadsheet['total_columns'],
            ],
            'rows' => $analysis['rows'],
            'analysis' => [
                'columns' => $analysis['columns'],
                'header_candidates' => $analysis['header_candidates'],
                'detected_date_range' => $analysis['detected_date_range'],
                'numeric_columns' => $analysis['numeric_columns'],
            ],
        ];

        $session = $this->sessions->create($user->id, $payload);

        // Remove the raw file to avoid storing duplicates - the normalized dataset is persisted in the session payload.
        Storage::disk('local')->delete($storedPath);

        return response()->json([
            'import_id' => $session['id'],
            'file' => $payload['file'],
            'meta' => $payload['meta'],
            'columns' => array_map(fn ($column) => [
                'index' => $column['index'],
                'label' => $column['label'],
                'sample_values' => $column['sample_values'],
                'detected_types' => $column['detected_types'],
                'header_guess' => $column['header_guess'],
            ], $analysis['columns']),
            'rows' => array_map(fn ($row) => [
                'index' => $row['index'],
                'original_index' => $row['original_index'],
                'values' => $row['values'],
                'auto_skip' => $row['auto_skip'],
                'skip_reasons' => $row['skip_reasons'],
                'header_like_score' => $row['header_like_score'],
            ], $analysis['rows']),
            'header_candidates' => $analysis['header_candidates'],
            'detected_date_range' => $analysis['detected_date_range'],
            'numeric_columns' => $analysis['numeric_columns'],
        ]);
    }

    public function paste(ImportPasteRequest $request): JsonResponse
    {
        $user = $request->user();
        $content = $request->input('content', '');

        $dataset = $this->clipboard->parse($content);

        if ($dataset['total_rows'] === 0) {
            return response()->json([
                'message' => 'לא זוהו נתונים להדבקה. ודא שהעתקת טבלה מגיליון אקסל ונסה שוב.',
            ], 422);
        }

        $analysis = $this->analyzer->analyze($dataset['rows'], $dataset['total_columns']);

        $payload = [
            'source' => 'clipboard',
            'meta' => [
                'total_rows' => $dataset['total_rows'],
                'total_columns' => $dataset['total_columns'],
            ],
            'rows' => $analysis['rows'],
            'analysis' => [
                'columns' => $analysis['columns'],
                'header_candidates' => $analysis['header_candidates'],
                'detected_date_range' => $analysis['detected_date_range'],
                'numeric_columns' => $analysis['numeric_columns'],
            ],
        ];

        $session = $this->sessions->create($user->id, $payload);

        return response()->json([
            'import_id' => $session['id'],
            'source' => 'clipboard',
            'meta' => $payload['meta'],
            'columns' => array_map(fn ($column) => [
                'index' => $column['index'],
                'label' => $column['label'],
                'sample_values' => $column['sample_values'],
                'detected_types' => $column['detected_types'],
                'header_guess' => $column['header_guess'],
            ], $analysis['columns']),
            'rows' => array_map(fn ($row) => [
                'index' => $row['index'],
                'original_index' => $row['original_index'],
                'values' => $row['values'],
                'auto_skip' => $row['auto_skip'],
                'skip_reasons' => $row['skip_reasons'],
                'header_like_score' => $row['header_like_score'],
            ], $analysis['rows']),
            'header_candidates' => $analysis['header_candidates'],
            'detected_date_range' => $analysis['detected_date_range'],
            'numeric_columns' => $analysis['numeric_columns'],
        ]);
    }

    public function transform(ImportProcessRequest $request): JsonResponse
    {
        $user = $request->user();
        $importId = $request->input('import_id');

        $session = $this->sessions->get($user->id, $importId);

        if (!$session) {
            return response()->json([
                'message' => 'סשן הייבוא אינו זמין או שפג תוקפו. אנא טען את הקובץ מחדש.',
            ], 404);
        }

        $categories = $user->categories()->get()->keyBy('id');
        $sources = $user->cashFlowSources()->get()->keyBy('id');

        $result = $this->processor->transform(
            $session,
            $user->id,
            $request->validated(),
            $categories,
            $sources
        );

        $status = empty($result['errors']) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function commit(ImportProcessRequest $request): JsonResponse
    {
        $user = $request->user();
        $importId = $request->input('import_id');

        $session = $this->sessions->get($user->id, $importId);

        if (!$session) {
            return response()->json([
                'message' => 'סשן הייבוא אינו זמין או שפג תוקפו. אנא טען את הקובץ מחדש.',
            ], 404);
        }

        $categories = $user->categories()->get()->keyBy('id');
        $sources = $user->cashFlowSources()->get()->keyBy('id');

        $result = $this->processor->commit(
            $session,
            $user->id,
            $request->validated(),
            $categories,
            $sources
        );

        if (!empty($result['errors'])) {
            return response()->json($result, 422);
        }

        $this->sessions->delete($user->id, $importId);

        return response()->json($result);
    }
}
