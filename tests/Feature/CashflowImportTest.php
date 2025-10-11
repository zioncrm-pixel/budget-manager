<?php

namespace Tests\Feature;

use App\Models\CashFlowSource;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CashflowImportTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleCsv(array $rows): UploadedFile
    {
        $content = "Date,Description,Amount\n";

        foreach ($rows as $row) {
            $content .= implode(',', $row) . "\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'import_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'statement.csv', 'text/csv', null, true);
    }

    private function cleanupUploadedFile(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath)) {
            @unlink($realPath);
        }
    }

    public function test_user_can_upload_csv_and_receive_analysis(): void
    {
        $user = User::factory()->create();

        $file = $this->createSampleCsv([
            ['2024-01-01', 'Salary', '10000'],
            ['2024-01-02', 'Groceries', '-250.5'],
        ]);

        $response = $this->actingAs($user)
            ->post(route('cashflow.import.upload'), [
                'file' => $file,
            ]);

        $this->cleanupUploadedFile($file);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'import_id',
            'file' => ['name', 'extension', 'size'],
            'meta' => ['total_rows', 'total_columns'],
            'columns',
            'rows',
            'header_candidates',
            'detected_date_range',
        ]);

        $json = $response->json();

        $this->assertSame(3, $json['meta']['total_rows']);
        $this->assertNotEmpty($json['columns']);
        $this->assertNotEmpty($json['rows']);
    }

    public function test_import_commit_creates_transactions(): void
    {
        $user = User::factory()->create();

        $incomeCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
            'color' => '#00FFAA',
            'icon' => '💼',
            'description' => null,
            'is_active' => true,
        ]);

        $expenseCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Groceries',
            'type' => 'expense',
            'color' => '#FF5577',
            'icon' => '🛒',
            'description' => null,
            'is_active' => true,
        ]);

        $incomeSource = CashFlowSource::create([
            'user_id' => $user->id,
            'name' => 'Bank Account',
            'type' => 'income',
            'color' => '#2563EB',
            'icon' => '🏦',
            'description' => null,
            'is_active' => true,
        ]);

        $expenseSource = CashFlowSource::create([
            'user_id' => $user->id,
            'name' => 'Credit Card',
            'type' => 'expense',
            'color' => '#DC2626',
            'icon' => '💳',
            'description' => null,
            'is_active' => true,
        ]);

        $file = $this->createSampleCsv([
            ['2024-02-01', 'Salary', '10000'],
            ['2024-02-03', 'Supermarket', '-350.45'],
        ]);

        $upload = $this->actingAs($user)
            ->post(route('cashflow.import.upload'), [
                'file' => $file,
            ])
            ->assertStatus(200)
            ->json();

        $this->cleanupUploadedFile($file);

        $payload = [
            'import_id' => $upload['import_id'],
            'excluded_rows' => [0],
            'mapping' => [
                'date' => ['column' => 0],
                'description' => ['column' => 1],
                'amount' => [
                    'mode' => 'single',
                    'column' => 2,
                    'negate' => false,
                ],
                'type' => ['mode' => 'auto_from_amount'],
                'posting_date' => ['mode' => 'same_as_transaction'],
                'reference' => ['column' => null],
                'notes' => ['column' => null],
            ],
            'defaults' => [
                'category_id' => null,
                'cash_flow_source_id' => null,
            ],
            'row_assignments' => [
                '1' => [
                    'category_id' => $incomeCategory->id,
                    'cash_flow_source_id' => $incomeSource->id,
                ],
                '2' => [
                    'category_id' => $expenseCategory->id,
                    'cash_flow_source_id' => $expenseSource->id,
                ],
            ],
        ];

        $preview = $this->postJson(route('cashflow.import.transform'), $payload)->assertStatus(200)->json();

        $this->assertSame(2, $preview['summary']['count']);
        $this->assertEmpty($preview['errors']);

        $result = $this->postJson(route('cashflow.import.commit'), $payload)->assertStatus(200)->json();

        $this->assertSame(2, $result['summary']['count']);
        $this->assertDatabaseCount('transactions', 2);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Salary',
            'type' => 'income',
            'amount' => 10000,
            'posting_date' => '2024-02-01 00:00:00',
            'category_id' => $incomeCategory->id,
            'cash_flow_source_id' => $incomeSource->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Supermarket',
            'type' => 'expense',
            'amount' => 350.45,
            'posting_date' => '2024-02-03 00:00:00',
            'category_id' => $expenseCategory->id,
            'cash_flow_source_id' => $expenseSource->id,
        ]);
    }
}
