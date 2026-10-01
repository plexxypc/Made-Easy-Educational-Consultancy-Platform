<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PDOException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * How long a run waits for another run on the same Postgres test database
     * before giving up.
     */
    private const RUN_LOCK_TIMEOUT = '20min';

    /**
     * Session that holds the per-database run lock for the life of this PHP
     * process. Postgres releases advisory locks when the session closes, so a
     * crashed or killed run can never leave a stale lock behind.
     */
    private static ?PDO $run_lock_session = null;

    /**
     * Tests must not depend on `npm run build` having produced a Vite manifest.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Take the run lock before RefreshDatabase / DatabaseTruncation get a
     * chance to run `migrate:fresh`.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        self::acquire_run_lock();

        return parent::setUpTraits();
    }

    /**
     * Serialise whole test runs that share one Postgres database.
     *
     * Two runs against the same database interleave their `migrate:fresh`
     * calls (one drops tables while the other creates or queries them). Because
     * RefreshDatabase retries the migration on every following test until one
     * succeeds, that collision fails almost the entire suite. A second run
     * therefore waits here for the first one to finish.
     *
     * Only the parent test process takes the lock; worker processes spawned by
     * the concurrency tests never extend this class.
     *
     * @return void
     *
     * @throws RuntimeException When another run holds the lock past the timeout.
     */
    private static function acquire_run_lock(): void
    {
        if (self::$run_lock_session !== null) {
            return;
        }

        $config = config('database.connections.'.config('database.default'));

        if (($config['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $session = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $lock_key = crc32('madeeasy-test-run:'.$config['database']);

        if (! $session->query("select pg_try_advisory_lock({$lock_key})")->fetchColumn()) {
            fwrite(STDERR, sprintf(
                "\nAnother test run is using the \"%s\" database; waiting up to %s for it to finish...\n",
                $config['database'],
                self::RUN_LOCK_TIMEOUT,
            ));

            try {
                $session->exec("set lock_timeout = '".self::RUN_LOCK_TIMEOUT."'");
                $session->query("select pg_advisory_lock({$lock_key})");
                $session->exec('set lock_timeout = 0');
            } catch (PDOException $exception) {
                throw new RuntimeException(sprintf(
                    'Gave up waiting for another test run on the "%s" database. Stop it, or point this run at a different DB_DATABASE.',
                    $config['database'],
                ), previous: $exception);
            }
        }

        self::$run_lock_session = $session;
    }
}
