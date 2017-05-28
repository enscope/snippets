<?php
/**
 * Simple pass-thru logger PHP script
 * (c) 2017 mhudak
 */

$logContentType = 'text/html';
$logRequestMethod = $_SERVER['REQUEST_METHOD'];
$logRequestUri = getenv('REQUEST_URI');
$logQueryString = getenv('QUERY_STRING');
$logPostData = json_encode($_POST);

$application_server = '*** HOSTNAME ***';
$uri = $_SERVER['REQUEST_URI'];
$realUrl = "https://{$application_server}{$uri}";
$requestBody = file_get_contents('php://input');

if (substr($uri, 1, 4) === 'mark')
{
    $marker = strtoupper(substr($logQueryString, 5));

    $fh = fopen('traffic-log.txt', 'ab') or die('unable to open log');
    fwrite($fh, "<request-marker>$marker</request-marker>\n");
    fclose($fh);

    exit();
}

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $realUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

switch ($logRequestMethod)
{
    case 'POST':
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl,CURLOPT_POSTFIELDS, $requestBody);
        break;
    case 'GET':
        // nothing special
        break;
}

$lastError = false;
$response = curl_exec($curl);
if (!$response)
{
    $lastError = curl_error($curl);
}
else
{
    $logContentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    header("Content-Type: $logContentType");
    echo $response;
}
curl_close($curl);

/** WRITE LOG **/

do {

    if ($logContentType !== 'application/json'
        && $logContentType !== 'text/html')
    {
        break;
    }

    $fh = fopen('traffic-log.txt', 'ab') or die('unable to open log');
    fwrite($fh, "<request method=\"$logRequestMethod\" uri=\"$logRequestUri\">\n");
    fwrite($fh, "<real-uri><![CDATA[$realUrl]]></real-uri>\n");
    fwrite($fh, "<content-type>$logContentType</content-type>\n");
    fwrite($fh, "<query-string><![CDATA[$logQueryString]]></query-string>\n");
    fwrite($fh, "<post-data><![CDATA[$logPostData]]></post-data>\n");
    if ($lastError)
    {
        fwrite($fh, "<error-message><![CDATA[$lastError]]></error-message>\n");
    }
    else
    {
        if (!empty($requestBody))
        {
            if (json_decode($requestBody))
            {
                $requestBody = json_encode(json_decode($requestBody), JSON_PRETTY_PRINT);
            }

            fwrite($fh, "<body-data><![CDATA[\n");
            fwrite($fh, "$requestBody\n");
            fwrite($fh, "]]></body-data>\n");
        }

        if (!empty($response))
        {
            if (json_decode($response))
            {
                $response = json_encode(json_decode($response), JSON_PRETTY_PRINT);
            }

            fwrite($fh, "<response><![CDATA[\n");
            fwrite($fh, "$response\n");
            fwrite($fh, "]]></response>\n");
        }
    }
    fwrite($fh, "</request>\n");
    fclose($fh);

} while (false);

exit();
