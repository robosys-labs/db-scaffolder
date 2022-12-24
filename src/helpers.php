<?php

use Google\Client;
use Google\Service\Sheets;

if ( ! function_exists('config_path'))
{
    /**
     * Get the configuration path.
     *
     * @param  string $path
     * @return string
     */
    function config_path($path = '')
    {
        return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
    }
}

function robosys_google_sheet($id = null, $range = null) {
 
    $array = array();

    $client = new Client();

    $client->setApplicationName('Google Sheets Reader');
    $client->setScopes('https://www.googleapis.com/auth/spreadsheets');
    $client->setAccessType('offline');
    $client->setAuthConfig(base_path('credentials.json'));
    $service = new Sheets($client);
    $spreadsheetId = $id;

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);

    $array = $response->getValues();

    return $array;
 }


/**
 * Scan a directory
 * and return all file names.
 *
 * @param $path
 * @param null $delimiter
 * @return array
 */
function robosys_get_Files($path, $delimiter = ".", $numericsort = true)
{

    $filesPath = base_path($path);
    $delimiter = $delimiter ?? Str::singular($path);

    if(! is_dir($filesPath)){
        return [];
    }

    $files = scandir($filesPath);

    //strip off delimiter from file name.

    $files = array_map(function($file) use($delimiter){
        return explode($delimiter,$file)[0];
    }, $files);

    $files = array_filter($files, function($file){
        if(in_array($file, ['.','..']) || strpos($file,'.json') || $file == null){
            return false;
        }

        return true;
    });
    $res = sort(array_values($files), $numericsort ? SORT_NUMERIC : SORT_REGULAR);
    return $res;
}
