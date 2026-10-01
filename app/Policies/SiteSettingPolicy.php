<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

/**
 * Site settings: admins only. The rows are fixed (SiteSettingKey), so
 * nobody creates or deletes them; admins only edit values.
 */
class SiteSettingPolicy
{
    /**
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  SiteSetting  $site_setting
     * @return bool
     */
    public function view(User $user, SiteSetting $site_setting): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  SiteSetting  $site_setting
     * @return bool
     */
    public function update(User $user, SiteSetting $site_setting): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  User  $user
     * @param  SiteSetting  $site_setting
     * @return bool
     */
    public function delete(User $user, SiteSetting $site_setting): bool
    {
        return false;
    }

    /**
     * @param  User  $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
