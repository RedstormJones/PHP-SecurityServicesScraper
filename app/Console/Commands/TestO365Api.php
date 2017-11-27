<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class TestO365Api extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:o365api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test command for querying the Office 365 API';

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
        $cookiejar = storage_path('app/cookies/o365cookie.txt');

        $ms_tenant_id = getenv('MS_TENANT_ID');

        $crawler = new \Crawler\Crawler($cookiejar);

        $url = 'https://login.microsoftonline.com/'.$ms_tenant_id.'/oauth2/token?api-version=1.0';

        $post_array = [
            'client_id'     => getenv('MS_APP_ID'),
            'client_secret' => getenv('MS_PWD'),
            'resource'      => 'https://graph.microsoft.com/',
            'grant_type'    => 'client_credentials',
        ];

        $json_response = $crawler->post($url, '', $this->postArrayToString($post_array));
        $response = \Metaclassing\Utility::decodeJson($json_response);

        $access_token = $response['access_token'];
        echo 'access_token: '.$access_token.PHP_EOL;

        //$url = 'https://manage.office.com/api/v1.0/'.$ms_tenant_id.'/activity/feed/subscriptions/content?contentType=Audit.Exchange&PublisherIdentifier='.$ms_tenant_id;
        $url = 'https://manage.office.com/api/v1.0/subscriptions/content?contentType=Audit.General&PublisherIdentifier='.$ms_tenant_id;

        $headers = [
            'Authorization' => 'Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $json_response = $crawler->get($url);
        var_dump($json_response);
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }
}
