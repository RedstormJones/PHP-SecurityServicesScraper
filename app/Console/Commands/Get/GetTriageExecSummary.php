<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetTriageExecSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:triageexecsummary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the Triage executive summary for the last 24 hours';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info(PHP_EOL.PHP_EOL.'*****************************************'.PHP_EOL.'* Starting Triage Exec Summary crawler! *'.PHP_EOL.'*****************************************');

        $triage_token = getenv('TRIAGE_TOKEN');
        $triage_email = getenv('TRIAGE_EMAIL');

        $cookiejar = storage_path('app/cookies/triage_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $start_date = Carbon::now()->subDay()->toDateString();
        $end_date = Carbon::now()->toDateString();

        $exec_sum_url = getenv('TRIAGE_URL').'/executive_summary?start_date='.$start_date.'&end_date='.$end_date;
        Log::info('[+] executive summary url: '.$exec_sum_url);

        $headers = [
            'Authorization: Token token='.$triage_email.':'.$triage_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $json_response = $crawler->get($exec_sum_url);

        file_put_contents(storage_path('app/responses/triage_exec_sum.response'), $json_response);
    }
}
