<!DOCTYPE html>
<html lang="en-US" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="UTF-8"/>
    <title>API Specifications - {{ $companyName }}</title>
    <style type="text/css">
        body { font-family: Verdana, sans-serif; }
        table { border-collapse: collapse; page-break-after: always; }
        table td { border: 1px solid #000; padding: 5px; }
        thead td { font-weight: bold; text-align: center; }
    </style>
</head>
<body>

<h1>{{ $appName }}</h1>
<h2>Lead Submission API Specifications</h2>
<h3>Company: {{ $companyName }} (Feed: {{ $feed->idFeedIn }})</h3>

<p>The lead submission system works on a key-value pair submission via HTTP POST (recommended) or HTTP GET. An XML (or JSON) response is
    produced after each attempt to send a lead to the system. All submissions must use SSL over HTTPS.</p>

<p><strong>API URL:</strong> <code>{{ $apiUrl }}</code></p>

<h4>API Field Definitions</h4>

@if($feed->feedCategory === 'phone-preping')
    <p>A PING request must first be sent to the system. If the record is accepted, an "authorization" field will be returned in the response. This authorization field must be submitted
        back to the system in the POST request, along with any of the original values of the PING request exactly as they were submitted with the PING, and any additional fields required by the POST.</p>
    @if(!empty($feed->pingTimeout))
        <p>There is a timeout of <strong>{{ $feed->pingTimeout }} seconds</strong> between when the PING is accepted and when the lead may be posted back.</p>
    @endif
    <h5>PING REQUEST</h5>
    <table>
        <thead>
        <tr>
            <td>Field</td>
            <td>Type</td>
            <td>Required</td>
            <td>Format</td>
            <td>Notes</td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>ping</td>
            <td>integer</td>
            <td>Yes</td>
            <td>1</td>
            <td>Send a value of 1 to indicate this is a PING request.</td>
        </tr>
        @foreach(array_filter($allowedPingArray) as $allowed)
            <tr>
                <td>{{ $allowed }}</td>
                <td>{{ $findField($allowed, 'fieldDefinition') }}</td>
                <td>{{ in_array($allowed, $requiredArray) ? 'Yes' : 'No' }}</td>
                <td>{{ $findField($allowed, 'fieldFormat') }}</td>
                <td>{{ $findField($allowed, 'fieldDescription') }}</td>
            </tr>
        @endforeach
        <tr>
            <td>outFormat</td>
            <td>varchar(255)</td>
            <td>No</td>
            <td>xml, json</td>
            <td>Specify the format of the response. Defaults to XML if not specified.</td>
        </tr>
        </tbody>
    </table>
    <h5>POST REQUEST</h5>
@endif

<table>
    <thead>
    <tr>
        <td>Field</td>
        <td>Type</td>
        <td>Required</td>
        <td>Format</td>
        <td>Notes</td>
    </tr>
    </thead>
    <tbody>
    @foreach(array_filter($allowedArray) as $allowed)
        <tr>
            <td>{{ $allowed }}</td>
            <td>{{ $findField($allowed, 'fieldDefinition') }}</td>
            <td>{{ in_array($allowed, $requiredArray) ? 'Yes' : 'No' }}</td>
            <td>{{ $findField($allowed, 'fieldFormat') }}</td>
            <td>{{ $findField($allowed, 'fieldDescription') }}</td>
        </tr>
    @endforeach
    <tr>
        <td>outFormat</td>
        <td>varchar(255)</td>
        <td>No</td>
        <td>xml, json</td>
        <td>Specify the format of the response. Defaults to XML if not specified.</td>
    </tr>
    </tbody>
</table>

<h4>API Responses</h4>

@if($feed->feedCategory === 'phone-preping')
    <h5>Valid XML PING Response Example</h5>
    <pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;response&gt;
    &lt;success&gt;true&lt;/success&gt;
    &lt;reason&gt;Successfully ping.&lt;/reason&gt;
    &lt;authorization&gt;...&lt;/authorization&gt;
&lt;/response&gt;</pre>
    <h5>Valid JSON PING Response Example</h5>
    <pre>{"success":true,"reason":"Successful ping.","authorization":"..."}</pre>
@endif

<h5>Valid XML Response Example</h5>
<pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;response&gt;
    &lt;success&gt;true&lt;/success&gt;
    &lt;reason&gt;Successfully inserted new record.&lt;/reason&gt;
&lt;/response&gt;</pre>

<h5>Valid JSON Response Example</h5>
<pre>{"success":true,"reason":"Successfully inserted new record."}</pre>

<h5>Invalid XML Response Examples</h5>
<pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;response&gt;
    &lt;success&gt;false&lt;/success&gt;
    &lt;reason&gt;Unauthorized access.&lt;/reason&gt;
&lt;/response&gt;</pre>

<pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;response&gt;
    &lt;success&gt;false&lt;/success&gt;
    &lt;reason&gt;Email is a required field, and may not be empty.&lt;/reason&gt;
&lt;/response&gt;</pre>

<h5>Invalid JSON Response Examples</h5>
<pre>{"success":false,"reason":"Unauthorized access"}</pre>
<pre>{"success":false,"reason":"Email is a required field, and may not be empty."}</pre>

</body>
</html>
