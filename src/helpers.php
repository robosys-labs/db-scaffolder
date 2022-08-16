<?php

use Google\Client;
use Google\Service\Sheets;

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
function robosys_get_Files($path, $delimiter = ".")
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
    return array_values($files);
}