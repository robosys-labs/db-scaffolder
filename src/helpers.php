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