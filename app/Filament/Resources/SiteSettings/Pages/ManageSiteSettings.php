<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\ManageRecords;

class ManageSiteSettings extends ManageRecords
{
    protected static string $resource = SiteSettingResource::class;

    /**
     * Create rows for settings added since the last visit, so the list is
     * always complete. Access is checked first so a refused visit writes
     * nothing (Filament's own check runs later in the request).
     *
     * @return void
     */
    public function mount(): void
    {
        abort_unless(SiteSettingResource::canViewAny(), 403);

        parent::mount();

        SiteSetting::ensure_all();
    }
}
