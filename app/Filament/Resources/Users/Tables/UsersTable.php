<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Users\ChangeUserRole;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    /**
     * User list with a "Change role" action (hidden on the admin's own row).
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->is(auth()->user()) ? 'You' : null),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders'),
                TextColumn::make('email_verified_at')
                    ->label('Email verified')
                    ->dateTime('j M Y')
                    ->placeholder('Not verified')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('j M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change role')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->authorize('changeRole')
                    ->modalDescription(fn (User $record): string => "Staff and admins can open the back office. Changing {$record->name}'s role takes effect immediately.")
                    ->fillForm(fn (User $record): array => ['role' => $record->role->value])
                    ->schema([
                        Select::make('role')
                            ->options(UserRole::class)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data, Action $action, ChangeUserRole $change_user_role): void {
                        try {
                            $new_role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);

                            $change_user_role->handle($record, $new_role, auth()->user());
                        } catch (BusinessRuleException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            $action->halt();
                        }

                        Notification::make()->success()->title("{$record->name} is now {$record->role->getLabel()}")->send();
                    }),
            ]);
    }
}
