<?php

class MojoMedia
{
    static function submitLead($data)
    {
        $leads = Leads::getInstance();
        $apiKey = $leads->getConfiguration('mojo_media_api_key');

        if (empty($apiKey)) {
            return null;
        }

        // Key = our field; value = remote API field
        $dataMap = [
            'phone_number' => 'phone_number', // Special handling for cellphone/landline in processLeads
            'email' => 'email',
            'zip' => 'postal_code',
            'ip' => 'ip_address',
            'stamp' => 'tcpa_consent_date',
            'fname' => 'first_name',
            'lname' => 'last_name',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'addr' => 'address_1',
            'addr2' => 'address_2',
            'dob' => 'birth_date',
        ];

        $postData = array(
            'api_key' => $apiKey,
            'tcpa_consent' => '1',
            'test_mode' => 'development' === APPLICATION_ENV ? 1 : 0,
        );

        // Loop through our data map and set any API fields that are present in our input data.
        foreach ($dataMap as $dataKey => $apiKey) {
            if (!empty($data[$dataKey])) {
                $postData[$apiKey] = $data[$dataKey];
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://leads.pipes.ai/api/lead');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Empty result. Probably an API error, but fail safe.
        if (empty($result)) {
            return true;
        }

        // Malformed data. Probably a bad phone number, which we should catch before this anyway.
        if (200 != $http_code) {
            return 'Malformed';
        }

        // Not a valid JSON response.  Fail safe.
        $json = json_decode($result);
        if (null === $json) {
            return true;
        }

        if (isset($json->success) && false === $json->success) {
            return $json->error ?? 'Rejected';
        }

        return true;
    }
}
