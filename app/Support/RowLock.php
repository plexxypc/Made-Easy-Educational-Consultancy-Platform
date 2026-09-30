<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Row-lock clauses shared by the code that hands out scarce things under
 * concurrency (result checker PINs, consultation slots).
 */
class RowLock
{
    /**
     * FOR UPDATE SKIP LOCKED where the database supports it (PostgreSQL in
     * production): a transaction that finds the row already locked by another
     * one skips it instead of waiting and then re-reading it. Plain FOR UPDATE
     * otherwise (SQLite, used by the default test run, has no row locks and
     * serialises writers instead). Pass the result to Builder::lock().
     *
     * @return string|bool
     */
    public static function skip_locked(): string|bool
    {
        return in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true) ? 'for update skip locked' : true;
    }
}
