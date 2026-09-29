<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\DocumentsRelationManager;
use App\Models\DocumentAccessLog;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opening a document from the Filament order screen writes the same audit row
 * as GET /documents/{id}.
 */
class FilamentDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var OrderDocument
     */
    private OrderDocument $order_document;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderDocument::DISK);

        $this->order = Order::factory()->create();
        $this->order_document = OrderDocument::factory()
            ->for($this->order)
            ->create(['original_filename' => 'waec-result.pdf']);

        Storage::disk(OrderDocument::DISK)->put($this->order_document->stored_path, '%PDF-1.4 test');
    }

    /**
     * Filament "Download" and the controller route produce identically shaped log rows.
     *
     * @return void
     */
    public function test_filament_download_writes_the_same_access_log_as_the_controller(): void
    {
        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);

        $this->get(route('order-documents.show', $this->order_document))->assertOk();

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->order,
            'pageClass' => ViewOrder::class,
        ])
            ->assertCanSeeTableRecords([$this->order_document])
            ->callTableAction('download', $this->order_document)
            ->assertFileDownloaded('waec-result.pdf');

        $this->assertDatabaseCount('document_access_logs', 2);

        [$controller_row, $filament_row] = DocumentAccessLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentAccessLog $log): array => $log->getAttributes())
            ->all();

        $this->assertSame(array_keys($controller_row), array_keys($filament_row));

        $comparable_columns = fn (array $row): array => array_diff_key($row, array_flip(['id', 'viewed_at']));
        $this->assertEquals($comparable_columns($controller_row), $comparable_columns($filament_row));

        $this->assertEquals($staff_user->id, $filament_row['user_id']);
        $this->assertEquals($this->order_document->id, $filament_row['order_document_id']);
        $this->assertSame('127.0.0.1', $filament_row['ip_address']);
        $this->assertNotNull($filament_row['viewed_at']);
    }

    /**
     * "View" links to the audited controller route rather than serving the file itself.
     *
     * @return void
     */
    public function test_view_action_links_to_the_audited_route(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->order,
            'pageClass' => ViewOrder::class,
        ])
            ->assertTableActionHasUrl('view', route('order-documents.show', $this->order_document), $this->order_document);

        $this->assertDatabaseCount('document_access_logs', 0);
    }
}
