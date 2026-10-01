<?php

/*
|--------------------------------------------------------------------------
| Official FAQ wording: PDF section 13
|--------------------------------------------------------------------------
|
| PENDING: the client's exact wording has not been supplied yet, so this list
| is empty and Database\Seeders\FaqSeeder seeds its DRAFTED stand-ins instead.
|
| To switch to the official text, paste the seven questions here, in the order
| the PDF gives them, as [question, answer] pairs. Blank lines in an answer
| ("\n\n") separate paragraphs. Then run:
|
|   docker compose exec app php artisan db:seed --class=FaqSeeder --force
|
| That removes the drafted questions nobody has edited, adds these, and lists
| any drafted question an admin changed so it can be reviewed by hand
| (App\Actions\Faqs\InstallOfficialFaqs).
|
| Example entry:
|   ['What services do you offer?', "First paragraph.\n\nSecond paragraph."],
|
*/

return [
];
