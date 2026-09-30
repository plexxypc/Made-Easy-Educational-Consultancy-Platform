<?php

namespace Tests\Feature\Filament;

use App\Actions\Users\ChangeUserRole;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\DocumentAccessLogs\DocumentAccessLogResource;
use App\Filament\Resources\DocumentAccessLogs\Pages\ListDocumentAccessLogs;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\DocumentAccessLog;
use App\Models\OrderDocument;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Access log, user/role management and catalogue screens are admin only.
 */
class AdminOnlyScreensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function admin_only_resources(): array
    {
        return [
            'document access log' => [DocumentAccessLogResource::class],
            'users & roles' => [UserResource::class],
            'services' => [ServiceResource::class],
            'service categories' => [ServiceCategoryResource::class],
        ];
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return void
     */
    #[DataProvider('admin_only_resources')]
    public function test_only_admin_can_open_resource(string $resource): void
    {
        $url = $resource::getUrl('index');

        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }

    /**
     * The access log lists who viewed what, and is searchable by staff member and by order.
     *
     * @return void
     */
    public function test_access_log_is_searchable_by_staff_and_order(): void
    {
        $alice = User::factory()->staff()->create(['name' => 'Alice Staff']);
        $bob = User::factory()->staff()->create(['name' => 'Bob Staff']);
        $first_document = OrderDocument::factory()->create();
        $second_document = OrderDocument::factory()->create();

        $alice_log = DocumentAccessLog::create(['user_id' => $alice->id, 'order_document_id' => $first_document->id, 'ip_address' => '10.0.0.1']);
        $bob_log = DocumentAccessLog::create(['user_id' => $bob->id, 'order_document_id' => $second_document->id, 'ip_address' => '10.0.0.2']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListDocumentAccessLogs::class)
            ->assertCanSeeTableRecords([$alice_log, $bob_log])
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$alice_log])
            ->assertCanNotSeeTableRecords([$bob_log])
            ->searchTable('#'.$second_document->order_id)
            ->assertCanSeeTableRecords([$bob_log])
            ->assertCanNotSeeTableRecords([$alice_log])
            ->searchTable(null)
            ->filterTable('user_id', $alice->id)
            ->assertCanSeeTableRecords([$alice_log])
            ->assertCanNotSeeTableRecords([$bob_log]);
    }

    /**
     * Access logs are read-only: no create/edit/delete routes or actions.
     *
     * @return void
     */
    public function test_access_log_is_read_only(): void
    {
        $this->assertSame(['index'], array_keys(DocumentAccessLogResource::getPages()));

        $admin_user = User::factory()->admin()->create();
        $log = DocumentAccessLog::create([
            'user_id' => User::factory()->staff()->create()->id,
            'order_document_id' => OrderDocument::factory()->create()->id,
        ]);

        $this->assertFalse($admin_user->can('update', $log));
        $this->assertFalse($admin_user->can('delete', $log));
        $this->assertFalse($admin_user->can('create', DocumentAccessLog::class));
    }

    /**
     * Admin can promote a customer to staff from the user list.
     *
     * @return void
     */
    public function test_admin_can_change_another_users_role(): void
    {
        $customer = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('changeRole')->table($customer), data: ['role' => UserRole::Staff->value])
            ->assertHasNoActionErrors();

        $this->assertSame(UserRole::Staff, $customer->refresh()->role);
    }

    /**
     * An admin cannot change (demote) their own role, in the UI or via the Action class.
     *
     * @return void
     */
    public function test_admin_cannot_demote_themselves(): void
    {
        $admin_user = User::factory()->admin()->create();
        $this->actingAs($admin_user);

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('changeRole')->table($admin_user));

        $this->assertFalse($admin_user->can('changeRole', $admin_user));

        try {
            app(ChangeUserRole::class)->handle($admin_user, UserRole::Customer, $admin_user);
            $this->fail('Admin was allowed to demote themselves.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('own role', $exception->getMessage());
        }

        $this->assertSame(UserRole::Admin, $admin_user->refresh()->role);
    }

    /**
     * Staff cannot change roles even by calling the Action class directly.
     *
     * @return void
     */
    public function test_staff_cannot_change_roles(): void
    {
        $staff_user = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->expectException(BusinessRuleException::class);

        app(ChangeUserRole::class)->handle($customer, UserRole::Admin, $staff_user);
    }
}
