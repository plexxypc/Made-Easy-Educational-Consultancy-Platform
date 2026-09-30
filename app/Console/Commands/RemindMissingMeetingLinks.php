<?php

namespace App\Console\Commands;

use App\Actions\Consultations\RemindStaffOfMissingMeetingLinks;
use Illuminate\Console\Command;

/**
 * Scheduled every 15 minutes (routes/console.php).
 */
class RemindMissingMeetingLinks extends Command
{
    /**
     * @var string
     */
    protected $signature = 'consultations:remind-missing-meeting-links';

    /**
     * @var string
     */
    protected $description = 'Remind staff about video consultations in the next 24 hours that have no meeting link (once per booking).';

    /**
     * @param  RemindStaffOfMissingMeetingLinks  $remind_staff_of_missing_meeting_links
     * @return int
     */
    public function handle(RemindStaffOfMissingMeetingLinks $remind_staff_of_missing_meeting_links): int
    {
        $reminded = $remind_staff_of_missing_meeting_links->handle();

        $this->info("Staff reminded about {$reminded} booking(s) without a meeting link.");

        return self::SUCCESS;
    }
}
