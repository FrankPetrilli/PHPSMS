PHPSMS
======

What is it?
-----------
PHPSMS is an outgoing SMS API that uses carrier-specific gateways to deliver your text messages for free, and without ads.

Dependencies:
-------------
The API uses MySQL / MariaDB as a backend store to keep track of current state. The PHP mail() function is used to perform the actual sending.

How do I send a request?
------------------------

```
$ curl -X POST https://example.com/api/phone/text.php -d country=us -d number=5551234567 -d "message=I sent this message via PHPSMS" -d key=example_key
```

Success and Failure
-------------------
Responses are standard JSON objects

Sample success:

```
{
"success": true
}
```

Sample failure:

```
{
"error": "You've exceeded your threshold for today.",
"success": false
}
```
