<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\User;

class UpdateLastLoginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $ip;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $ip)
    {
        $this->userId = $userId;
        $this->ip = $ip;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        User::where('id', $this->userId)
            ->update([
                'last_login_at' => now(),
                'last_login_ip' => $this->ip,
            ]);
    }
}
