<?php

namespace App\Console\Commands;

use App\Services\AppointmentWorkflow;
use Illuminate\Console\Command;

class ExpirePreReservations extends Command
{
    protected $signature = 'appointments:expire-pre-reservations';

    protected $description = 'Mark pre-reserved appointments as expired when their hold time has passed';

    public function __construct(private AppointmentWorkflow $workflow)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = $this->workflow->expirePreReservations();
        $this->info("Expired {$expired} pre-reserved appointments");

        return Command::SUCCESS;
    }
}
