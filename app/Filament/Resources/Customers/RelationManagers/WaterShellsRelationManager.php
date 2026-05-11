<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\DeliverySetting;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class WaterShellsRelationManager extends RelationManager
{
    protected static string $relationship = 'waterShells';

    protected static ?string $title = 'Cascos em circulação';

    protected static ?string $modelLabel = 'Casco';

    protected static ?string $pluralModelLabel = 'Cascos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return DeliverySetting::trackingShells();
    }

    public function form(Schema $schema): Schema
    {
        // Read-mostly view; movements are driven by sales.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variant.product')->where('shell_count', '>', 0))
            ->defaultSort('expires_at')
            ->columns([
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('variant.product.name')
                    ->label('Produto')
                    ->wrap(),

                TextColumn::make('variant.size')
                    ->label('Tamanho')
                    ->badge(),

                TextColumn::make('expires_at')
                    ->label('Validade')
                    ->date('m/Y')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->lte(now()->addDays(90)) ? 'warning' : 'success'))
                    ->weight('semibold'),

                TextColumn::make('shell_count')
                    ->label('Cascos')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),

                TextColumn::make('last_out_at')
                    ->label('Última saída')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('expired')
                    ->label('Apenas vencidos')
                    ->query(fn ($query) => $query->whereDate('expires_at', '<', now())),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
