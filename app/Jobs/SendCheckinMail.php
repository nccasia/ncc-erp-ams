<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;
use App\Mail\CheckinMail;

class SendCheckinMail implements ShouldQueue
{
    protected $data;
    protected $user_email;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $user_email)
    {
        $this->data = $data;
        $this->user_email = $user_email;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->user_email)->cc(Setting::first()->admin_cc_email)->send(new CheckinMail($this->data));
    }
}
