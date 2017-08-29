<?php

// this is a base class others are derived from

namespace Crawler;

class Crawler
{
    public $curl;

    // constructor sets mandatory fields
    public function __construct($cookiePath = '/tmp/crawlercookie.txt')
    {
        // set our cookie path
        $this->cookiePath = $cookiePath;

        // setup curl handle
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_NOBODY, false);
        // set curl cookie options
        curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->cookiePath);
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookiePath);
        // set curl user agent spoof
        curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7');
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_CERTINFO, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($this->curl, CURLOPT_VERBOSE      , true);
    }

    public function get($url, $referer = '')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_REFERER, $referer);

        return $this->curl_exec();
    }

    public function post($url, $referer = '', $post = '')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_REFERER, $url);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post);

        return $this->curl_exec();
    }

    public function put($url, $referer = '', $put = '')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_REFERER, $referer);
        curl_setopt($this->curl, CURLOPT_UPLOAD, true);
        curl_setopt($this->curl, CURLOPT_READDATA, $put);

        return $this->curl_exec();
    }

    public function delete($url, $referer = '')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_REFERER, $referer);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->curl_exec();
    }

    // curl wrapper for fun
    public function curl_getinfo()
    {
        return curl_getinfo($this->curl);
    }

    public function curl_exec()
    {
        return curl_exec($this->curl);
    }

    /** The same as curl_exec except tries its best to convert the output to utf8 **/
    public function curl_exec_utf8()
    {
        $data = curl_exec($this->curl);
        if (!is_string($data)) {
            return $data;
        }

        unset($charset);
        $content_type = curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);

        /* 1: HTTP Content-Type: header */
        preg_match('@([\w/+]+)(;\s*charset=(\S+))?@i', $content_type, $matches);
        if (isset($matches[3])) {
            $charset = $matches[3];
        }

        /* 2: <meta> element in the page */
        if (!isset($charset)) {
            preg_match('@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches);
            if (isset($matches[3])) {
                $charset = $matches[3];
            }
        }

        /* 3: <xml> element in the page */
        if (!isset($charset)) {
            preg_match('@<\?xml.+encoding="([^\s"]+)@si', $data, $matches);
            if (isset($matches[1])) {
                $charset = $matches[1];
            }
        }

        /* 4: PHP's heuristic detection */
        if (!isset($charset)) {
            $encoding = mb_detect_encoding($data);
            if ($encoding) {
                $charset = $encoding;
            }
        }

        /* 5: Default for HTML */
        if (!isset($charset)) {
            if (strstr($content_type, 'text/html') === 0) {
                $charset = 'ISO 8859-1';
            }
        }

        /* Convert it if it is anything but UTF-8 */
        /* You can change "UTF-8"  to "UTF-8//IGNORE" to
           ignore conversion errors and still output something reasonable */
        if (isset($charset) && strtoupper($charset) != 'UTF-8') {
            $data = iconv($charset, 'UTF-8', $data);
        }

        return $data;
    }
}
