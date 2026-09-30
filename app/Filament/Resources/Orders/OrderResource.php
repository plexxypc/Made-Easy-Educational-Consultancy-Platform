<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\PinsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Staff + admin (panel access). Record-level rules come from OrderPolicy;
 * status changes only through the actions on ViewOrder. There is no create or
 * edit page: status and totals are not mass-assignable.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 10;

    /**
     * @param  Schema  $schema
     * @return Schema
     */
    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /**
     * Listing query, scoped with the existing Order::visibleTo() rule.
     *
     * @return Builder<Order>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /**
     * Include soft-deleted orders when opening a record (admins can restore them).
     *
     * @return Builder<Order>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Title shown on the view page, e.g. "Order ME-7K3Q9A" (or "Order #42" for old orders).
     *
     * @param  Model|null  $record
     * @return string
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Order ? "Order {$record->display_reference}" : 'Order';
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            PinsRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
