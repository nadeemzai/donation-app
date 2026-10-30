<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Imdaad Mobile API v1</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-app" class="tocify-header">
                <li class="tocify-item level-1" data-unique="app">
                    <a href="#app">App</a>
                </li>
                                    <ul id="tocify-subheader-app" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="app-GETapi-v1-health">
                                <a href="#app-GETapi-v1-health">Health check</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="app-GETapi-v1-app-config">
                                <a href="#app-GETapi-v1-app-config">App configuration</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-auth" class="tocify-header">
                <li class="tocify-item level-1" data-unique="auth">
                    <a href="#auth">Auth</a>
                </li>
                                    <ul id="tocify-subheader-auth" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-register">
                                <a href="#auth-POSTapi-v1-auth-register">Register</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-login">
                                <a href="#auth-POSTapi-v1-auth-login">Start login</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-otp-send">
                                <a href="#auth-POSTapi-v1-auth-otp-send">Send the code again</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-otp-verify">
                                <a href="#auth-POSTapi-v1-auth-otp-verify">Verify the code</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout">
                                <a href="#auth-POSTapi-v1-auth-logout">Log out</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-catalog" class="tocify-header">
                <li class="tocify-item level-1" data-unique="catalog">
                    <a href="#catalog">Catalog</a>
                </li>
                                    <ul id="tocify-subheader-catalog" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="catalog-GETapi-v1-banners">
                                <a href="#catalog-GETapi-v1-banners">Home banners</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="catalog-GETapi-v1-funds">
                                <a href="#catalog-GETapi-v1-funds">Funds</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="catalog-GETapi-v1-campaigns">
                                <a href="#catalog-GETapi-v1-campaigns">Campaigns</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="catalog-GETapi-v1-campaigns--slug-">
                                <a href="#catalog-GETapi-v1-campaigns--slug-">One campaign</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="catalog-GETapi-v1-campaigns--slug--updates">
                                <a href="#catalog-GETapi-v1-campaigns--slug--updates">Campaign updates</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-content" class="tocify-header">
                <li class="tocify-item level-1" data-unique="content">
                    <a href="#content">Content</a>
                </li>
                                    <ul id="tocify-subheader-content" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="content-GETapi-v1-impact-updates">
                                <a href="#content-GETapi-v1-impact-updates">Impact updates</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="content-GETapi-v1-pages--slug-">
                                <a href="#content-GETapi-v1-pages--slug-">A content page</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="content-POSTapi-v1-zakat-calculate">
                                <a href="#content-POSTapi-v1-zakat-calculate">Zakat calculator</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-donate" class="tocify-header">
                <li class="tocify-item level-1" data-unique="donate">
                    <a href="#donate">Donate</a>
                </li>
                                    <ul id="tocify-subheader-donate" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="donate-POSTapi-v1-donations">
                                <a href="#donate-POSTapi-v1-donations">Start a donation</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="donate-GETapi-v1-donations--number--status">
                                <a href="#donate-GETapi-v1-donations--number--status">Donation status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="donate-POSTapi-v1-donations--number--proof">
                                <a href="#donate-POSTapi-v1-donations--number--proof">Upload transfer proof</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-me" class="tocify-header">
                <li class="tocify-item level-1" data-unique="me">
                    <a href="#me">Me</a>
                </li>
                                    <ul id="tocify-subheader-me" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="me-GETapi-v1-me">
                                <a href="#me-GETapi-v1-me">My profile</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="me-PUTapi-v1-me">
                                <a href="#me-PUTapi-v1-me">Update my profile</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="me-GETapi-v1-me-donations">
                                <a href="#me-GETapi-v1-me-donations">My donations</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="me-GETapi-v1-me-receipts--receipt--download">
                                <a href="#me-GETapi-v1-me-receipts--receipt--download">Download a receipt (PDF)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="me-GETapi-v1-me-tax-certificates">
                                <a href="#me-GETapi-v1-me-tax-certificates">My tax certificates</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="me-GETapi-v1-me-tax-certificates--certificate--download">
                                <a href="#me-GETapi-v1-me-tax-certificates--certificate--download">Download a tax certificate (PDF)</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-devices" class="tocify-header">
                <li class="tocify-item level-1" data-unique="devices">
                    <a href="#devices">Devices</a>
                </li>
                                    <ul id="tocify-subheader-devices" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="devices-POSTapi-v1-devices">
                                <a href="#devices-POSTapi-v1-devices">Register this device</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="devices-DELETEapi-v1-devices">
                                <a href="#devices-DELETEapi-v1-devices">Remove this device</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: October 30, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>Donor-facing API for the Imdaad mobile app: catalog, donations, payments, receipts and profile.</p>
<aside>
    <strong>Base URL</strong>: <code>localhost:8000</code>
</aside>
<pre><code>Base URL: `/api/v1`. JSON only. Every response has the same envelope:

```json
{ "success": true, "data": {}, "message": "", "errors": null, "meta": {} }
```

- **errors** is filled only on `422` (field → list of messages).
- **meta.pagination** on lists: `current_page`, `per_page`, `total`, `last_page`, `has_more`. Ask for more with `?page=2`.
- **Money** is `{ "amount": "5000.00", "currency": "PKR", "formatted": "Rs 5,000.00" }` — `amount` is a string.
- **Enums** are `{ "value": "paid", "label": "Paid" }`. Times are ISO 8601 in Pakistan time.
- Send `Accept-Language: ur` for Urdu messages. Send `X-Request-Id` and quote it when reporting a problem.
- Too many requests → `429` with `meta.retry_after` (seconds).

Full conventions: `docs/api-conventions.md` in the repository.</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {TOKEN}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Log in with a one-time code (POST /auth/otp/send, then /auth/otp/verify) to get a token. Send it as <code>Authorization: Bearer {TOKEN}</code>.</p>

        <h1 id="app">App</h1>

    <p>GET /api/v1/health — lets the mobile app and monitoring check the API is up.</p>

                                <h2 id="app-GETapi-v1-health">Health check</h2>

<p>
</p>

<p>Is the API up? No login needed.</p>

<span id="example-requests-GETapi-v1-health">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/health" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/health"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-health">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;status&quot;: &quot;ok&quot;,
        &quot;version&quot;: &quot;v1&quot;,
        &quot;time&quot;: &quot;2026-12-14T10:30:00+05:00&quot;
    },
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-health" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-health"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-health"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-health" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-health">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-health" data-method="GET"
      data-path="api/v1/health"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-health', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-health"
                    onclick="tryItOut('GETapi-v1-health');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-health"
                    onclick="cancelTryOut('GETapi-v1-health');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-health"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/health</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="app-GETapi-v1-app-config">App configuration</h2>

<p>
</p>

<p>Organisation details, ways to pay (only those switched on right now), donation
limits, Zakat rates, links and the minimum app version. Call it on every launch —
payment methods can be switched on and off by staff.</p>

<span id="example-requests-GETapi-v1-app-config">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/app/config" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/app/config"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-app-config">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 736f282b-6c33-486c-950c-e47032059c7f
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;organisation&quot;: {
            &quot;name&quot;: &quot;Imdaad&quot;,
            &quot;legal_name&quot;: null,
            &quot;registration_no&quot;: null,
            &quot;tax_exemption_ref&quot;: null,
            &quot;phone&quot;: null,
            &quot;email&quot;: null,
            &quot;address&quot;: null,
            &quot;website&quot;: &quot;localhost:8000&quot;,
            &quot;logo_url&quot;: &quot;localhost:8000/images/imdaad-mark.svg&quot;,
            &quot;brand_color&quot;: &quot;#e5254b&quot;
        },
        &quot;donate&quot;: {
            &quot;currency&quot;: &quot;PKR&quot;,
            &quot;min&quot;: {
                &quot;amount&quot;: &quot;100.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 100.00&quot;
            },
            &quot;max&quot;: {
                &quot;amount&quot;: &quot;5000000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 5,000,000.00&quot;
            },
            &quot;presets&quot;: [
                {
                    &quot;amount&quot;: &quot;1000.00&quot;,
                    &quot;currency&quot;: &quot;PKR&quot;,
                    &quot;formatted&quot;: &quot;Rs 1,000.00&quot;
                },
                {
                    &quot;amount&quot;: &quot;2500.00&quot;,
                    &quot;currency&quot;: &quot;PKR&quot;,
                    &quot;formatted&quot;: &quot;Rs 2,500.00&quot;
                },
                {
                    &quot;amount&quot;: &quot;5000.00&quot;,
                    &quot;currency&quot;: &quot;PKR&quot;,
                    &quot;formatted&quot;: &quot;Rs 5,000.00&quot;
                },
                {
                    &quot;amount&quot;: &quot;10000.00&quot;,
                    &quot;currency&quot;: &quot;PKR&quot;,
                    &quot;formatted&quot;: &quot;Rs 10,000.00&quot;
                },
                {
                    &quot;amount&quot;: &quot;25000.00&quot;,
                    &quot;currency&quot;: &quot;PKR&quot;,
                    &quot;formatted&quot;: &quot;Rs 25,000.00&quot;
                }
            ],
            &quot;proof_max_kb&quot;: 5120,
            &quot;methods&quot;: [
                {
                    &quot;value&quot;: &quot;bank_transfer&quot;,
                    &quot;label&quot;: &quot;Bank transfer / Raast&quot;,
                    &quot;kind&quot;: &quot;bank_transfer&quot;,
                    &quot;direct_wallet&quot;: false,
                    &quot;needs_proof&quot;: true
                }
            ],
            &quot;bank&quot;: {
                &quot;bank_name&quot;: &quot;Meezan Bank&quot;,
                &quot;account_title&quot;: &quot;Imdaad pvt ltd&quot;,
                &quot;iban&quot;: &quot;PK36SCBL0000001123456702&quot;,
                &quot;raast_id&quot;: null
            }
        },
        &quot;zakat&quot;: {
            &quot;gold_rate_per_tola&quot;: {
                &quot;amount&quot;: &quot;421000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 421,000.00&quot;
            },
            &quot;silver_rate_per_tola&quot;: {
                &quot;amount&quot;: &quot;6000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 6,000.00&quot;
            },
            &quot;rates_date&quot;: &quot;2026-10-29&quot;,
            &quot;nisab_basis&quot;: &quot;silver&quot;,
            &quot;nisab&quot;: {
                &quot;amount&quot;: &quot;315000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 315,000.00&quot;
            },
            &quot;nisab_tola&quot;: {
                &quot;gold&quot;: &quot;7.5&quot;,
                &quot;silver&quot;: &quot;52.5&quot;
            },
            &quot;rate&quot;: &quot;0.025&quot;
        },
        &quot;login&quot;: {
            &quot;code_length&quot;: 6,
            &quot;code_expires_in&quot;: 600
        },
        &quot;links&quot;: {
            &quot;about&quot;: &quot;localhost:8000/about&quot;,
            &quot;contact&quot;: &quot;localhost:8000/contact&quot;,
            &quot;privacy&quot;: null,
            &quot;donor_portal&quot;: &quot;localhost:8000/my-donations&quot;
        },
        &quot;app&quot;: {
            &quot;min_version&quot;: &quot;1.0.0&quot;,
            &quot;latest_version&quot;: &quot;1.0.0&quot;,
            &quot;android_url&quot;: null,
            &quot;ios_url&quot;: null
        },
        &quot;languages&quot;: [
            &quot;en&quot;,
            &quot;ur&quot;
        ]
    },
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-app-config" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-app-config"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-app-config"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-app-config" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-app-config">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-app-config" data-method="GET"
      data-path="api/v1/app/config"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-app-config', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-app-config"
                    onclick="tryItOut('GETapi-v1-app-config');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-app-config"
                    onclick="cancelTryOut('GETapi-v1-app-config');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-app-config"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/app/config</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-app-config"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-app-config"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="auth">Auth</h1>

    <p>Donors log in with a one-time code — no passwords (Week 7 Day 2).</p>
<ol>
<li><code>POST /auth/login</code> (or <code>/auth/register</code> for a new donor) with a mobile number or email.
A 6-digit code goes to WhatsApp (mobile number, when WhatsApp is set up) or email.</li>
<li><code>POST /auth/otp/verify</code> with the code → an API token. Send it as <code>Authorization: Bearer …</code>.</li>
</ol>
<p>The answer to step 1 is the same whether or not the number belongs to a donor, so the
API can't be used to find out who gives. Codes last 10 minutes, work once and die after
5 wrong tries. Tokens last 90 days.</p>

                                <h2 id="auth-POSTapi-v1-auth-register">Register</h2>

<p>
</p>

<p>Creates a donor record for a new mobile number and sends the login code. If the number
(or email) already belongs to a donor, nothing is changed and the code goes to them —
the answer looks the same either way.</p>

<span id="example-requests-POSTapi-v1-auth-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/auth/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Ayesha Khan\",
    \"phone\": \"0300-1234567\",
    \"email\": \"ayesha@example.com\",
    \"city\": \"Lahore\",
    \"language\": \"en\",
    \"whatsapp_opt_in\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/auth/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Ayesha Khan",
    "phone": "0300-1234567",
    "email": "ayesha@example.com",
    "city": "Lahore",
    "language": "en",
    "whatsapp_opt_in": true
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-register">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;sent_to&quot;: &quot;0300&bull;&bull;&bull;4567&quot;,
        &quot;expires_in&quot;: 600
    },
    &quot;message&quot;: &quot;If this number or email is on our records, we have sent a 6-digit code.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-register" data-method="POST"
      data-path="api/v1/auth/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-register"
                    onclick="tryItOut('POSTapi-v1-auth-register');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-register"
                    onclick="cancelTryOut('POSTapi-v1-auth-register');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-register"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-auth-register"
               value="Ayesha Khan"
               data-component="body">
    <br>
<p>Full name. Must be at least 2 characters. Must not be greater than 120 characters. Example: <code>Ayesha Khan</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-auth-register"
               value="0300-1234567"
               data-component="body">
    <br>
<p>Pakistani mobile number. The login code goes here. Must not be greater than 20 characters. Example: <code>0300-1234567</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-register"
               value="ayesha@example.com"
               data-component="body">
    <br>
<p>Optional. Receipts are also emailed here. Must be a valid email address. Must not be greater than 255 characters. Example: <code>ayesha@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="city"                data-endpoint="POSTapi-v1-auth-register"
               value="Lahore"
               data-component="body">
    <br>
<p>Optional. Must not be greater than 100 characters. Example: <code>Lahore</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>language</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="language"                data-endpoint="POSTapi-v1-auth-register"
               value="en"
               data-component="body">
    <br>
<p>en or ur — language for receipts and messages. Example: <code>en</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>en</code></li> <li><code>ur</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>whatsapp_opt_in</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-auth-register" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="true"
                   data-endpoint="POSTapi-v1-auth-register"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-auth-register" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="false"
                   data-endpoint="POSTapi-v1-auth-register"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Receipts and updates on WhatsApp. Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-login">Start login</h2>

<p>
</p>

<p>Sends a one-time code to the mobile number or email.</p>

<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"identifier\": \"0300-1234567\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "identifier": "0300-1234567"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;sent_to&quot;: &quot;0300&bull;&bull;&bull;4567&quot;,
        &quot;expires_in&quot;: 600
    },
    &quot;message&quot;: &quot;If this number or email is on our records, we have sent a 6-digit code.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Too many codes asked for. Please try again in 9 minutes.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-login"
                    onclick="tryItOut('POSTapi-v1-auth-login');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-login"
                    onclick="cancelTryOut('POSTapi-v1-auth-login');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-login"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>identifier</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="identifier"                data-endpoint="POSTapi-v1-auth-login"
               value="0300-1234567"
               data-component="body">
    <br>
<p>The donor's Pakistani mobile number or email address. Must not be greater than 255 characters. Example: <code>0300-1234567</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-otp-send">Send the code again</h2>

<p>
</p>

<p>Same as start login; use it for the "send a new code" button.</p>

<span id="example-requests-POSTapi-v1-auth-otp-send">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/auth/otp/send" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"identifier\": \"0300-1234567\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/auth/otp/send"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "identifier": "0300-1234567"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-otp-send">
</span>
<span id="execution-results-POSTapi-v1-auth-otp-send" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-otp-send"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-otp-send"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-otp-send" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-otp-send">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-otp-send" data-method="POST"
      data-path="api/v1/auth/otp/send"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-otp-send', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-otp-send"
                    onclick="tryItOut('POSTapi-v1-auth-otp-send');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-otp-send"
                    onclick="cancelTryOut('POSTapi-v1-auth-otp-send');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-otp-send"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/otp/send</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-otp-send"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-otp-send"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>identifier</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="identifier"                data-endpoint="POSTapi-v1-auth-otp-send"
               value="0300-1234567"
               data-component="body">
    <br>
<p>The donor's Pakistani mobile number or email address. Must not be greater than 255 characters. Example: <code>0300-1234567</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-otp-verify">Verify the code</h2>

<p>
</p>

<p>Swaps the code for an API token.</p>

<span id="example-requests-POSTapi-v1-auth-otp-verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/auth/otp/verify" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"identifier\": \"0300-1234567\",
    \"code\": \"482913\",
    \"device_name\": \"Ayesha\'s Galaxy A54\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/auth/otp/verify"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "identifier": "0300-1234567",
    "code": "482913",
    "device_name": "Ayesha's Galaxy A54"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-otp-verify">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;token&quot;: &quot;12|f3k&hellip;&quot;,
        &quot;token_type&quot;: &quot;Bearer&quot;,
        &quot;expires_at&quot;: &quot;2027-03-14T10:30:00+05:00&quot;,
        &quot;donor&quot;: {
            &quot;id&quot;: 42,
            &quot;donor_code&quot;: &quot;DNR-2026-000042&quot;,
            &quot;name&quot;: &quot;Ayesha Khan&quot;
        }
    },
    &quot;message&quot;: &quot;Logged in.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;That code is wrong or has expired.&quot;,
    &quot;errors&quot;: {
        &quot;code&quot;: [
            &quot;That code is wrong or has expired. Check it, or ask for a new one.&quot;
        ]
    },
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-otp-verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-otp-verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-otp-verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-otp-verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-otp-verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-otp-verify" data-method="POST"
      data-path="api/v1/auth/otp/verify"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-otp-verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-otp-verify"
                    onclick="tryItOut('POSTapi-v1-auth-otp-verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-otp-verify"
                    onclick="cancelTryOut('POSTapi-v1-auth-otp-verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-otp-verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/otp/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>identifier</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="identifier"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="0300-1234567"
               data-component="body">
    <br>
<p>The same mobile number or email the code was sent to. Must not be greater than 255 characters. Example: <code>0300-1234567</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="482913"
               data-component="body">
    <br>
<p>The 6-digit code from WhatsApp or email. Must be 6 digits. Example: <code>482913</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="Ayesha's Galaxy A54"
               data-component="body">
    <br>
<p>Shown to the donor in their list of logged-in devices. Must not be greater than 100 characters. Example: <code>Ayesha's Galaxy A54</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-logout">Log out</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Ends this device's token. Send <code>all=true</code> to log out of every device.</p>

<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/auth/logout" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"all\": false
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/auth/logout"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "all": false
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Logged out.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-logout"
                    onclick="tryItOut('POSTapi-v1-auth-logout');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-logout"
                    onclick="cancelTryOut('POSTapi-v1-auth-logout');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-logout"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-auth-logout"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>all</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-auth-logout" style="display: none">
            <input type="radio" name="all"
                   value="true"
                   data-endpoint="POSTapi-v1-auth-logout"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-auth-logout" style="display: none">
            <input type="radio" name="all"
                   value="false"
                   data-endpoint="POSTapi-v1-auth-logout"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Log out everywhere. Example: <code>false</code></p>
        </div>
        </form>

                <h1 id="catalog">Catalog</h1>

    <p>What donors can give to (Week 7 Day 3). Public; drafts never appear.</p>

                                <h2 id="catalog-GETapi-v1-banners">Home banners</h2>

<p>
</p>

<p>The slider on the home screen: active banners inside their dates, in order.</p>

<span id="example-requests-GETapi-v1-banners">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/banners" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/banners"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-banners">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 94a00296-05b5-44ea-bbc7-01d30dcc0bff
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-banners" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-banners"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-banners"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-banners" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-banners">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-banners" data-method="GET"
      data-path="api/v1/banners"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-banners', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-banners"
                    onclick="tryItOut('GETapi-v1-banners');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-banners"
                    onclick="cancelTryOut('GETapi-v1-banners');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-banners"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/banners</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-banners"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-banners"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="catalog-GETapi-v1-funds">Funds</h2>

<p>
</p>

<p>Active funds (Zakat, Sadqa…) in display order. Not paged — there are only a few.</p>

<span id="example-requests-GETapi-v1-funds">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/funds" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/funds"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-funds">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: f346267e-b33f-424b-bd86-72b23f038bed
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;code&quot;: &quot;ZAKAT&quot;,
            &quot;name&quot;: &quot;Zakat&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;zakat&quot;,
                &quot;label&quot;: &quot;Zakat&quot;
            },
            &quot;is_zakat&quot;: true,
            &quot;description&quot;: &quot;Obligatory alms. Spent only on Zakat-eligible beneficiaries.&quot;,
            &quot;restriction&quot;: null,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/zakat.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/zakat&quot;
        },
        {
            &quot;id&quot;: 2,
            &quot;code&quot;: &quot;SADQA&quot;,
            &quot;name&quot;: &quot;Sadqa / Atiya&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;sadqa&quot;,
                &quot;label&quot;: &quot;Sadqa / Atiya&quot;
            },
            &quot;is_zakat&quot;: false,
            &quot;description&quot;: &quot;Voluntary charity for general welfare.&quot;,
            &quot;restriction&quot;: null,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/sadqa.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/sadqa&quot;
        },
        {
            &quot;id&quot;: 3,
            &quot;code&quot;: &quot;FITRANA&quot;,
            &quot;name&quot;: &quot;Fitrana&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;fitrana&quot;,
                &quot;label&quot;: &quot;Fitrana&quot;
            },
            &quot;is_zakat&quot;: false,
            &quot;description&quot;: &quot;Sadaqat-ul-Fitr, collected before Eid-ul-Fitr.&quot;,
            &quot;restriction&quot;: null,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/fitrana.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/fitrana&quot;
        },
        {
            &quot;id&quot;: 4,
            &quot;code&quot;: &quot;FIDYA&quot;,
            &quot;name&quot;: &quot;Fidya&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;fidya&quot;,
                &quot;label&quot;: &quot;Fidya&quot;
            },
            &quot;is_zakat&quot;: false,
            &quot;description&quot;: &quot;Compensation for missed fasts, used to feed the needy.&quot;,
            &quot;restriction&quot;: null,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/fidya.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/fidya&quot;
        },
        {
            &quot;id&quot;: 5,
            &quot;code&quot;: &quot;GENERAL&quot;,
            &quot;name&quot;: &quot;General Fund&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;general&quot;,
                &quot;label&quot;: &quot;General&quot;
            },
            &quot;is_zakat&quot;: false,
            &quot;description&quot;: &quot;Unrestricted donations, including running costs.&quot;,
            &quot;restriction&quot;: null,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/general.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/general&quot;
        },
        {
            &quot;id&quot;: 6,
            &quot;code&quot;: &quot;QURBANI&quot;,
            &quot;name&quot;: &quot;Qurbani&quot;,
            &quot;type&quot;: {
                &quot;value&quot;: &quot;restricted&quot;,
                &quot;label&quot;: &quot;Restricted&quot;
            },
            &quot;is_zakat&quot;: false,
            &quot;description&quot;: &quot;Collected for Eid-ul-Adha Qurbani.&quot;,
            &quot;restriction&quot;: &quot;Qurbani animals, slaughter and meat distribution only.&quot;,
            &quot;image_url&quot;: &quot;localhost:8000/images/funds/restricted.svg&quot;,
            &quot;web_url&quot;: &quot;localhost:8000/ways-to-give/qurbani&quot;
        }
    ],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-funds" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-funds"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-funds"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-funds" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-funds">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-funds" data-method="GET"
      data-path="api/v1/funds"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-funds', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-funds"
                    onclick="tryItOut('GETapi-v1-funds');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-funds"
                    onclick="cancelTryOut('GETapi-v1-funds');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-funds"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/funds</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-funds"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-funds"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="catalog-GETapi-v1-campaigns">Campaigns</h2>

<p>
</p>

<p>Live campaigns, featured first then ending soonest. Paged.</p>

<span id="example-requests-GETapi-v1-campaigns">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/campaigns?fund=ZAKAT&amp;featured=1&amp;q=winter&amp;status=live&amp;page=1&amp;per_page=20" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/campaigns"
);

const params = {
    "fund": "ZAKAT",
    "featured": "1",
    "q": "winter",
    "status": "live",
    "page": "1",
    "per_page": "20",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-campaigns">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 04d0ebb2-e574-4082-9159-d8610dfa78c3
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 20,
            &quot;total&quot;: 0,
            &quot;last_page&quot;: 1,
            &quot;has_more&quot;: false
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-campaigns" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-campaigns"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-campaigns"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-campaigns" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-campaigns">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-campaigns" data-method="GET"
      data-path="api/v1/campaigns"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-campaigns', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-campaigns"
                    onclick="tryItOut('GETapi-v1-campaigns');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-campaigns"
                    onclick="cancelTryOut('GETapi-v1-campaigns');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-campaigns"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/campaigns</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-campaigns"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-campaigns"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>fund</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="fund"                data-endpoint="GETapi-v1-campaigns"
               value="ZAKAT"
               data-component="query">
    <br>
<p>Only campaigns in this fund (fund code). Example: <code>ZAKAT</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>featured</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="GETapi-v1-campaigns" style="display: none">
            <input type="radio" name="featured"
                   value="1"
                   data-endpoint="GETapi-v1-campaigns"
                   data-component="query"             >
            <code>true</code>
        </label>
        <label data-endpoint="GETapi-v1-campaigns" style="display: none">
            <input type="radio" name="featured"
                   value="0"
                   data-endpoint="GETapi-v1-campaigns"
                   data-component="query"             >
            <code>false</code>
        </label>
    <br>
<p>Only featured campaigns. Example: <code>true</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>q</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="q"                data-endpoint="GETapi-v1-campaigns"
               value="winter"
               data-component="query">
    <br>
<p>Search in the title. Example: <code>winter</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-campaigns"
               value="live"
               data-component="query">
    <br>
<p>live (default) or closed. Example: <code>live</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-v1-campaigns"
               value="1"
               data-component="query">
    <br>
<p>Page number. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-campaigns"
               value="20"
               data-component="query">
    <br>
<p>1–50, default 20. Example: <code>20</code></p>
            </div>
                </form>

                    <h2 id="catalog-GETapi-v1-campaigns--slug-">One campaign</h2>

<p>
</p>

<p>Live or closed campaign with its story and gallery. A closed campaign's page stays
so shared links keep working; <code>is_open</code> is false and donations go to its fund.</p>

<span id="example-requests-GETapi-v1-campaigns--slug-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/campaigns/winter-relief-2026" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/campaigns/winter-relief-2026"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-campaigns--slug-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: c2f0fbc5-3c31-402c-aae6-b803b6bb1e2d
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;slug&quot;: &quot;winter-relief-2026&quot;,
        &quot;title&quot;: &quot;Winter Relief 2026&quot;,
        &quot;summary&quot;: &quot;Winter Relief 2026 - Need help.&quot;,
        &quot;status&quot;: {
            &quot;value&quot;: &quot;live&quot;,
            &quot;label&quot;: &quot;Live&quot;
        },
        &quot;is_featured&quot;: false,
        &quot;is_open&quot;: true,
        &quot;goal&quot;: {
            &quot;amount&quot;: &quot;500000.00&quot;,
            &quot;currency&quot;: &quot;PKR&quot;,
            &quot;formatted&quot;: &quot;Rs 500,000.00&quot;
        },
        &quot;raised&quot;: {
            &quot;amount&quot;: &quot;0.00&quot;,
            &quot;currency&quot;: &quot;PKR&quot;,
            &quot;formatted&quot;: &quot;Rs 0.00&quot;
        },
        &quot;progress_percent&quot;: 0,
        &quot;donor_count&quot;: 0,
        &quot;starts_on&quot;: &quot;2026-10-28&quot;,
        &quot;ends_on&quot;: &quot;2026-11-27&quot;,
        &quot;image_url&quot;: &quot;localhost:8000/storage/1/5hcqutCOn0kPeYSqqMRVGig8A9ZMVPmBz5S4WROO.jpg&quot;,
        &quot;fund&quot;: {
            &quot;id&quot;: 2,
            &quot;code&quot;: &quot;SADQA&quot;,
            &quot;name&quot;: &quot;Sadqa / Atiya&quot;,
            &quot;is_zakat&quot;: false
        },
        &quot;web_url&quot;: &quot;localhost:8000/campaigns/winter-relief-2026&quot;,
        &quot;story_html&quot;: &quot;&lt;p&gt;Winter Relief 2026 - test story&lt;/p&gt;&quot;,
        &quot;gallery&quot;: [
            &quot;localhost:8000/storage/2/A3MmoEpQvJ3xXsSCzAduIg8zF6ddY2fF1QHNnaHl.jpg&quot;
        ]
    },
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-campaigns--slug-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-campaigns--slug-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-campaigns--slug-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-campaigns--slug-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-campaigns--slug-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-campaigns--slug-" data-method="GET"
      data-path="api/v1/campaigns/{slug}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-campaigns--slug-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-campaigns--slug-"
                    onclick="tryItOut('GETapi-v1-campaigns--slug-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-campaigns--slug-"
                    onclick="cancelTryOut('GETapi-v1-campaigns--slug-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-campaigns--slug-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/campaigns/{slug}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-campaigns--slug-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-campaigns--slug-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>slug</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="slug"                data-endpoint="GETapi-v1-campaigns--slug-"
               value="winter-relief-2026"
               data-component="url">
    <br>
<p>Example: <code>winter-relief-2026</code></p>
            </div>
                    </form>

                    <h2 id="catalog-GETapi-v1-campaigns--slug--updates">Campaign updates</h2>

<p>
</p>

<p>Published progress updates on one campaign, newest first. Paged.</p>

<span id="example-requests-GETapi-v1-campaigns--slug--updates">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/campaigns/winter-relief-2026/updates" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/campaigns/winter-relief-2026/updates"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-campaigns--slug--updates">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 6edb42c4-98f5-4aef-9f6a-5de2df3189f5
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 10,
            &quot;total&quot;: 0,
            &quot;last_page&quot;: 1,
            &quot;has_more&quot;: false
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-campaigns--slug--updates" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-campaigns--slug--updates"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-campaigns--slug--updates"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-campaigns--slug--updates" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-campaigns--slug--updates">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-campaigns--slug--updates" data-method="GET"
      data-path="api/v1/campaigns/{slug}/updates"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-campaigns--slug--updates', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-campaigns--slug--updates"
                    onclick="tryItOut('GETapi-v1-campaigns--slug--updates');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-campaigns--slug--updates"
                    onclick="cancelTryOut('GETapi-v1-campaigns--slug--updates');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-campaigns--slug--updates"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/campaigns/{slug}/updates</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-campaigns--slug--updates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-campaigns--slug--updates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>slug</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="slug"                data-endpoint="GETapi-v1-campaigns--slug--updates"
               value="winter-relief-2026"
               data-component="url">
    <br>
<p>Example: <code>winter-relief-2026</code></p>
            </div>
                    </form>

                <h1 id="content">Content</h1>

    <p>Pages edited in the admin, and the Zakat calculator (Week 7 Day 3).</p>

                                <h2 id="content-GETapi-v1-impact-updates">Impact updates</h2>

<p>
</p>

<p>The latest published updates from every public campaign — "where your money went".
Each item says which campaign it belongs to. Paged.</p>

<span id="example-requests-GETapi-v1-impact-updates">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/impact-updates" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/impact-updates"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-impact-updates">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 8da4e7a3-791d-4b7e-905b-9c8788cc52f4
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [
        {
            &quot;id&quot;: 10,
            &quot;title&quot;: &quot;Repudiandae consectetur sed facere fugit dolore laborum.&quot;,
            &quot;body_html&quot;: &quot;Possimus consectetur qui sunt qui. Laudantium atque hic fuga quo commodi ipsam. Ea aperiam aliquam voluptas consequatur nulla cupiditate. Dolorem minus corrupti dicta et fuga maiores.\n\nEaque sit nihil ea corrupti ullam illum dolorum tempora. Qui ex eligendi iure corrupti voluptas ad omnis sed.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;orphan-education-ongoing&quot;,
                &quot;title&quot;: &quot;Orphan Education (ongoing)&quot;
            }
        },
        {
            &quot;id&quot;: 9,
            &quot;title&quot;: &quot;Veniam aperiam qui repudiandae impedit occaecati.&quot;,
            &quot;body_html&quot;: &quot;Fugiat ut ipsa qui explicabo et magni. Tempora quos exercitationem maxime. Voluptatum ipsam aut architecto ipsum. Delectus molestias voluptatibus perspiciatis itaque sint.\n\nQui assumenda reiciendis sunt quia. Quia quo hic atque blanditiis sunt soluta eius deserunt. Illum id reprehenderit doloremque libero. Sunt mollitia alias iusto qui rerum voluptas omnis quia.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;orphan-education-ongoing&quot;,
                &quot;title&quot;: &quot;Orphan Education (ongoing)&quot;
            }
        },
        {
            &quot;id&quot;: 8,
            &quot;title&quot;: &quot;Enim est vitae repudiandae repellat.&quot;,
            &quot;body_html&quot;: &quot;Et quaerat numquam dolorem aut voluptas. Autem et eos perferendis distinctio. Enim nemo inventore aut ullam placeat ut deserunt quas. Molestias nobis officia ut.\n\nNumquam animi ea eveniet asperiores necessitatibus. Suscipit eos quasi animi et est. Fugiat id dolor sunt. Corrupti ut tempora asperiores facilis quos voluptatem dolorem.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;flood-emergency-appeal&quot;,
                &quot;title&quot;: &quot;Flood Emergency Appeal&quot;
            }
        },
        {
            &quot;id&quot;: 7,
            &quot;title&quot;: &quot;Aut aperiam enim dolorum facilis.&quot;,
            &quot;body_html&quot;: &quot;Iusto enim et omnis voluptates id sed. Voluptatem totam modi ut ut aut. Qui eum suscipit quia ut. Mollitia eligendi saepe et similique.\n\nIure corporis unde earum impedit quae sit cupiditate sit. At libero similique molestiae ut dolores blanditiis. Illum eos sunt pariatur nihil est autem. Cum libero pariatur quo molestiae velit.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;flood-emergency-appeal&quot;,
                &quot;title&quot;: &quot;Flood Emergency Appeal&quot;
            }
        },
        {
            &quot;id&quot;: 6,
            &quot;title&quot;: &quot;Nam aspernatur quas ipsam eligendi placeat illo.&quot;,
            &quot;body_html&quot;: &quot;Repudiandae dolores et ut sint et. Ab alias ut at non magnam magni. Unde culpa distinctio reiciendis vel sit. Et modi consequuntur ducimus similique.\n\nOdio aliquid similique et quia ipsam molestiae. Exercitationem quia quod error ut quae quod vitae. Ipsa molestiae ducimus assumenda nobis quam et sunt. Iste earum omnis provident porro. Dolor asperiores reprehenderit ipsa eaque ut.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;clean-water-wells&quot;,
                &quot;title&quot;: &quot;Clean Water Wells&quot;
            }
        },
        {
            &quot;id&quot;: 5,
            &quot;title&quot;: &quot;Qui et non esse illo saepe.&quot;,
            &quot;body_html&quot;: &quot;Eaque magnam non minus perferendis sunt consectetur eos. Impedit sed molestiae ratione sint dolores. Libero eum unde quas expedita quia aut. Ipsum architecto nemo non in. Alias recusandae necessitatibus quo repellendus voluptatum.\n\nNihil natus ea hic est accusantium sint dicta. Tempore quo aut exercitationem laborum. Cupiditate recusandae hic et facilis labore modi inventore. Hic consequatur eum voluptates est corrupti ipsum iste.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;clean-water-wells&quot;,
                &quot;title&quot;: &quot;Clean Water Wells&quot;
            }
        },
        {
            &quot;id&quot;: 4,
            &quot;title&quot;: &quot;Quia quae vero consequatur.&quot;,
            &quot;body_html&quot;: &quot;Qui voluptatem quia voluptatem. Dolor pariatur voluptatem excepturi voluptates iusto ut ipsum. Minus autem cum magnam ducimus modi expedita voluptas.\n\nIllo et est dolore vel in. Repellat laudantium doloribus cum omnis. Autem officia architecto eos consequuntur architecto atque soluta vero.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;zakat-for-widows&quot;,
                &quot;title&quot;: &quot;Zakat for Widows&quot;
            }
        },
        {
            &quot;id&quot;: 3,
            &quot;title&quot;: &quot;Voluptatem a cupiditate soluta qui.&quot;,
            &quot;body_html&quot;: &quot;Nam inventore quia eius ipsa velit qui. Consequatur et qui aut quo. Vero ut magni delectus est aut rerum vitae.\n\nEarum quod vero esse minus. Delectus aliquam velit accusantium ut eum voluptate dolorem. Vel unde voluptas eius modi cupiditate atque incidunt est.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;zakat-for-widows&quot;,
                &quot;title&quot;: &quot;Zakat for Widows&quot;
            }
        },
        {
            &quot;id&quot;: 2,
            &quot;title&quot;: &quot;Rerum minima expedita in.&quot;,
            &quot;body_html&quot;: &quot;Rerum et explicabo sunt provident aut. Veritatis sapiente neque eveniet qui est ullam. Atque ut unde aut praesentium cum corrupti.\n\nEx sit et modi vero. Eligendi fugit sunt perspiciatis. Dignissimos ut sit sapiente voluptates aut ducimus. Quisquam autem veritatis dolorem sint sed quae.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;winter-relief-2026-2&quot;,
                &quot;title&quot;: &quot;Winter Relief 2026&quot;
            }
        },
        {
            &quot;id&quot;: 1,
            &quot;title&quot;: &quot;Quia corrupti ut asperiores cupiditate.&quot;,
            &quot;body_html&quot;: &quot;Atque iste pariatur nam qui. Fuga asperiores qui doloribus. Ut neque deleniti quas et maxime debitis aliquam unde.\n\nUt iste rerum molestias et. Quidem dolor qui ipsum ut incidunt voluptatem. Facilis culpa dignissimos ab totam voluptatem omnis et.&quot;,
            &quot;published_at&quot;: &quot;2026-10-28T14:27:43+05:00&quot;,
            &quot;photos&quot;: [],
            &quot;campaign&quot;: {
                &quot;slug&quot;: &quot;winter-relief-2026-2&quot;,
                &quot;title&quot;: &quot;Winter Relief 2026&quot;
            }
        }
    ],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 10,
            &quot;total&quot;: 10,
            &quot;last_page&quot;: 1,
            &quot;has_more&quot;: false
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-impact-updates" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-impact-updates"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-impact-updates"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-impact-updates" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-impact-updates">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-impact-updates" data-method="GET"
      data-path="api/v1/impact-updates"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-impact-updates', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-impact-updates"
                    onclick="tryItOut('GETapi-v1-impact-updates');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-impact-updates"
                    onclick="cancelTryOut('GETapi-v1-impact-updates');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-impact-updates"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/impact-updates</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-impact-updates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-impact-updates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="content-GETapi-v1-pages--slug-">A content page</h2>

<p>
</p>

<p>About us, Contact, Privacy… — published pages only. <code>body_html</code> is already cleaned.</p>

<span id="example-requests-GETapi-v1-pages--slug-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/pages/about" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/pages/about"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pages--slug-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 50663266-efc7-4baf-945c-df5374c828e6
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;slug&quot;: &quot;about&quot;,
        &quot;title&quot;: &quot;About us&quot;,
        &quot;summary&quot;: &quot;Who we are, what we do and how we look after every rupee you give.&quot;,
        &quot;body_html&quot;: &quot;&lt;h2&gt;Who we are&lt;/h2&gt;&lt;p&gt;We are a registered welfare organisation in Pakistan. We collect Zakat, Sadqa and donations and deliver them to families in need.&lt;/p&gt;&lt;h2&gt;How we look after your money&lt;/h2&gt;&lt;ul&gt;&lt;li&gt;Every donation gets a receipt.&lt;/li&gt;&lt;li&gt;Zakat is kept in its own fund and spent only on people who are eligible.&lt;/li&gt;&lt;li&gt;Every fund has its own ledger, checked by our finance team.&lt;/li&gt;&lt;/ul&gt;&lt;p&gt;&lt;em&gt;Please replace this text with your own story in Admin &rarr; Website &rarr; Pages.&lt;/em&gt;&lt;/p&gt;&quot;,
        &quot;has_contact_form&quot;: false,
        &quot;updated_at&quot;: &quot;2026-10-29T17:40:59+05:00&quot;,
        &quot;web_url&quot;: &quot;localhost:8000/about&quot;
    },
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pages--slug-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pages--slug-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pages--slug-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pages--slug-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pages--slug-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pages--slug-" data-method="GET"
      data-path="api/v1/pages/{slug}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pages--slug-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-pages--slug-"
                    onclick="tryItOut('GETapi-v1-pages--slug-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-pages--slug-"
                    onclick="cancelTryOut('GETapi-v1-pages--slug-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-pages--slug-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pages/{slug}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pages--slug-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pages--slug-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>slug</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="slug"                data-endpoint="GETapi-v1-pages--slug-"
               value="about"
               data-component="url">
    <br>
<p>Example: <code>about</code></p>
            </div>
                    </form>

                    <h2 id="content-POSTapi-v1-zakat-calculate">Zakat calculator</h2>

<p>
</p>

<p>Works out the Zakat due with today's rates — the same rules as the website.
Nothing is stored. Weights in tola (1 tola = 11.664 g); money in rupees; commas allowed.
When Zakat is due, <code>pay</code> says which fund and amount to pre-fill on the donate screen.</p>

<span id="example-requests-POSTapi-v1-zakat-calculate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/zakat/calculate" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"gold_tola\": \"2\",
    \"silver_tola\": \"0\",
    \"cash\": \"200000\",
    \"bank\": \"100000\",
    \"business\": \"0\",
    \"receivables\": \"0\",
    \"investments\": \"0\",
    \"debts\": \"100000\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/zakat/calculate"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "gold_tola": "2",
    "silver_tola": "0",
    "cash": "200000",
    "bank": "100000",
    "business": "0",
    "receivables": "0",
    "investments": "0",
    "debts": "100000"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-zakat-calculate">
</span>
<span id="execution-results-POSTapi-v1-zakat-calculate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-zakat-calculate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-zakat-calculate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-zakat-calculate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-zakat-calculate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-zakat-calculate" data-method="POST"
      data-path="api/v1/zakat/calculate"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-zakat-calculate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-zakat-calculate"
                    onclick="tryItOut('POSTapi-v1-zakat-calculate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-zakat-calculate"
                    onclick="cancelTryOut('POSTapi-v1-zakat-calculate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-zakat-calculate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/zakat/calculate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>gold_tola</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="gold_tola"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="2"
               data-component="body">
    <br>
<p>Gold owned, in tola. Example: <code>2</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>silver_tola</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="silver_tola"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="0"
               data-component="body">
    <br>
<p>Silver owned, in tola. Example: <code>0</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cash</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cash"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="200000"
               data-component="body">
    <br>
<p>Cash at home. Example: <code>200000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>bank</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="bank"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="100000"
               data-component="body">
    <br>
<p>Bank and wallet balances. Example: <code>100000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>business</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="0"
               data-component="body">
    <br>
<p>Business stock at sale price. Example: <code>0</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>receivables</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="receivables"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="0"
               data-component="body">
    <br>
<p>Money others owe you that you expect back. Example: <code>0</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>investments</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="investments"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="0"
               data-component="body">
    <br>
<p>Shares, savings certificates… today's value. Example: <code>0</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>debts</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="debts"                data-endpoint="POSTapi-v1-zakat-calculate"
               value="100000"
               data-component="body">
    <br>
<p>Debts and bills due now. Example: <code>100000</code></p>
        </div>
        </form>

                <h1 id="donate">Donate</h1>

    <p>Giving from the app (Week 7 Day 4).</p>
<ol>
<li><code>POST /donations</code> → the donation, and what to do next (<code>next_step</code>):<ul>
<li><code>wait</code>: a wallet charge was sent — the donor approves it on their phone; poll <code>status_url</code>.</li>
<li><code>open_checkout</code>: open <code>checkout</code> (the gateway's page) in a web view; then poll <code>status_url</code>.</li>
<li><code>upload_proof</code>: bank transfer — show <code>bank</code>, then send the screenshot to <code>proof_url</code>.</li>
</ul>
</li>
<li>Poll <code>GET status_url</code> every few seconds until <code>is_final</code> is true.</li>
</ol>
<p><code>status_url</code> and <code>proof_url</code> are signed links valid for 7 days — use them exactly as given.</p>

                                <h2 id="donate-POSTapi-v1-donations">Start a donation</h2>

<p>
</p>

<p>Works with or without a donor token. Without one, <code>name</code> and <code>phone</code> are required and
the donor is matched by phone/email exactly like the website (an existing donor's
record is never changed from here). Send a fresh UUID as <code>Idempotency-Key</code> for every new
donation; sending the same key again returns the same donation (safe retries).</p>

<span id="example-requests-POSTapi-v1-donations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/donations" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"fund_code\": \"ZAKAT\",
    \"campaign_slug\": \"winter-relief-2026\",
    \"amount\": 5000,
    \"payment_method\": \"jazzcash\",
    \"wallet_number\": \"03001234567\",
    \"cnic_last6\": \"345671\",
    \"name\": \"Ayesha Khan\",
    \"phone\": \"0300-1234567\",
    \"email\": \"ayesha@example.com\",
    \"cnic\": \"35202-1234567-1\",
    \"whatsapp_opt_in\": true,
    \"is_anonymous\": false,
    \"dedication\": \"My late father\",
    \"idempotency_key\": \"3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/donations"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "fund_code": "ZAKAT",
    "campaign_slug": "winter-relief-2026",
    "amount": 5000,
    "payment_method": "jazzcash",
    "wallet_number": "03001234567",
    "cnic_last6": "345671",
    "name": "Ayesha Khan",
    "phone": "0300-1234567",
    "email": "ayesha@example.com",
    "cnic": "35202-1234567-1",
    "whatsapp_opt_in": true,
    "is_anonymous": false,
    "dedication": "My late father",
    "idempotency_key": "3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-donations">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;donation&quot;: {
            &quot;number&quot;: &quot;DN-2026-000123&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            }
        },
        &quot;next_step&quot;: &quot;wait&quot;,
        &quot;payment&quot;: {
            &quot;reference&quot;: &quot;PAY8F3K2&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            }
        },
        &quot;checkout&quot;: {
            &quot;type&quot;: &quot;awaiting_donor&quot;,
            &quot;url&quot;: null,
            &quot;fields&quot;: [],
            &quot;message&quot;: &quot;Approve the payment in your JazzCash app.&quot;
        },
        &quot;status_url&quot;: &quot;https://&hellip;/api/v1/donations/DN-2026-000123/status?expires=&hellip;&amp;signature=&hellip;&quot;,
        &quot;proof_url&quot;: null,
        &quot;bank&quot;: null
    },
    &quot;message&quot;: &quot;Donation started.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-donations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-donations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-donations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-donations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-donations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-donations" data-method="POST"
      data-path="api/v1/donations"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-donations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-donations"
                    onclick="tryItOut('POSTapi-v1-donations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-donations"
                    onclick="cancelTryOut('POSTapi-v1-donations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-donations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/donations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-donations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-donations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>fund_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="fund_code"                data-endpoint="POSTapi-v1-donations"
               value="ZAKAT"
               data-component="body">
    <br>
<p>Give to this fund. Either this or campaign_slug. This field is required when <code>campaign_slug</code> is not present. Must not be greater than 30 characters. Example: <code>ZAKAT</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>campaign_slug</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="campaign_slug"                data-endpoint="POSTapi-v1-donations"
               value="winter-relief-2026"
               data-component="body">
    <br>
<p>Give to this live campaign (its fund is used). Must not be greater than 120 characters. Example: <code>winter-relief-2026</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>amount</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="amount"                data-endpoint="POSTapi-v1-donations"
               value="5000"
               data-component="body">
    <br>
<p>Rupees; commas allowed; up to 2 decimals. Must match the regex /^\d{1,9}(.\d{1,2})?$/. Must be at least 100. Must not be greater than 5000000. Example: <code>5000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>payment_method</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment_method"                data-endpoint="POSTapi-v1-donations"
               value="jazzcash"
               data-component="body">
    <br>
<p>One of the methods in /app/config. Example: <code>jazzcash</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>bank_transfer</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>wallet_number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="wallet_number"                data-endpoint="POSTapi-v1-donations"
               value="03001234567"
               data-component="body">
    <br>
<p>Charge this JazzCash/Easypaisa wallet directly (with cnic_last6). Leave out to get the hosted payment page instead. This field is required when <code>cnic_last6</code> is present. Must match the regex /^03\d{9}$/. Example: <code>03001234567</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cnic_last6</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cnic_last6"                data-endpoint="POSTapi-v1-donations"
               value="345671"
               data-component="body">
    <br>
<p>Last 6 digits of the wallet owner's CNIC. Passed to the gateway, never stored. This field is required when <code>wallet_number</code> is present. Must be 6 digits. Example: <code>345671</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-donations"
               value="Ayesha Khan"
               data-component="body">
    <br>
<p>Required without a donor token. Must be at least 2 characters. Must not be greater than 120 characters. Example: <code>Ayesha Khan</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-donations"
               value="0300-1234567"
               data-component="body">
    <br>
<p>Required without a donor token. Must not be greater than 20 characters. Example: <code>0300-1234567</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-donations"
               value="ayesha@example.com"
               data-component="body">
    <br>
<p>Optional. Must be a valid email address. Must not be greater than 255 characters. Example: <code>ayesha@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cnic</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cnic"                data-endpoint="POSTapi-v1-donations"
               value="35202-1234567-1"
               data-component="body">
    <br>
<p>Optional, for tax certificates (new donors only). Must not be greater than 20 characters. Example: <code>35202-1234567-1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>whatsapp_opt_in</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-donations" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="true"
                   data-endpoint="POSTapi-v1-donations"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-donations" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="false"
                   data-endpoint="POSTapi-v1-donations"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>New donors: receipts on WhatsApp. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_anonymous</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-donations" style="display: none">
            <input type="radio" name="is_anonymous"
                   value="true"
                   data-endpoint="POSTapi-v1-donations"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-donations" style="display: none">
            <input type="radio" name="is_anonymous"
                   value="false"
                   data-endpoint="POSTapi-v1-donations"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Hide the donor's name in public lists. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>dedication</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="dedication"                data-endpoint="POSTapi-v1-donations"
               value="My late father"
               data-component="body">
    <br>
<p>On behalf of / in memory of. Must not be greater than 150 characters. Example: <code>My late father</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>idempotency_key</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="idempotency_key"                data-endpoint="POSTapi-v1-donations"
               value="3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d"
               data-component="body">
    <br>
<p>A UUID; or send it as the Idempotency-Key header. Repeating it returns the same donation. Must be a valid UUID. Example: <code>3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d</code></p>
        </div>
        </form>

                    <h2 id="donate-GETapi-v1-donations--number--status">Donation status</h2>

<p>
</p>

<p>Poll this (the signed <code>status_url</code>) every 5 seconds or so until <code>is_final</code> is true.
If the gateway's answer hasn't arrived after half a minute, we ask the gateway ourselves.</p>

<span id="example-requests-GETapi-v1-donations--number--status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/donations/DN-2026-000123/status" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/donations/DN-2026-000123/status"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-donations--number--status">
            <blockquote>
            <p>Example response (403):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
x-request-id: 2e9fde8a-4fa3-4fe8-8a7b-59a6f6ca60b9
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Invalid signature.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-donations--number--status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-donations--number--status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-donations--number--status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-donations--number--status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-donations--number--status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-donations--number--status" data-method="GET"
      data-path="api/v1/donations/{number}/status"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-donations--number--status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-donations--number--status"
                    onclick="tryItOut('GETapi-v1-donations--number--status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-donations--number--status"
                    onclick="cancelTryOut('GETapi-v1-donations--number--status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-donations--number--status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/donations/{number}/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-donations--number--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-donations--number--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="number"                data-endpoint="GETapi-v1-donations--number--status"
               value="DN-2026-000123"
               data-component="url">
    <br>
<p>Example: <code>DN-2026-000123</code></p>
            </div>
                    </form>

                    <h2 id="donate-POSTapi-v1-donations--number--proof">Upload transfer proof</h2>

<p>
</p>

<p>For a pending bank transfer / Raast donation: the transaction ID and a screenshot.
Finance checks it and the receipt follows. One proof per donation.</p>

<span id="example-requests-POSTapi-v1-donations--number--proof">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/donations/DN-2026-000123/proof" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --form "reference=RAAST-88120044"\
    --form "proof=@/private/var/folders/kf/lkn293jj00bd1j35hm585zwr0000gn/T/phppe7k0j86rebfaxa3PnP" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/donations/DN-2026-000123/proof"
);

const headers = {
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
};

const body = new FormData();
body.append('reference', 'RAAST-88120044');
body.append('proof', document.querySelector('input[name="proof"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-donations--number--proof">
</span>
<span id="execution-results-POSTapi-v1-donations--number--proof" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-donations--number--proof"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-donations--number--proof"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-donations--number--proof" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-donations--number--proof">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-donations--number--proof" data-method="POST"
      data-path="api/v1/donations/{number}/proof"
      data-authed="0"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-donations--number--proof', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-donations--number--proof"
                    onclick="tryItOut('POSTapi-v1-donations--number--proof');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-donations--number--proof"
                    onclick="cancelTryOut('POSTapi-v1-donations--number--proof');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-donations--number--proof"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/donations/{number}/proof</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-donations--number--proof"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-donations--number--proof"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="number"                data-endpoint="POSTapi-v1-donations--number--proof"
               value="DN-2026-000123"
               data-component="url">
    <br>
<p>Example: <code>DN-2026-000123</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reference</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reference"                data-endpoint="POSTapi-v1-donations--number--proof"
               value="RAAST-88120044"
               data-component="body">
    <br>
<p>Transaction ID from the bank or wallet app. Must not be greater than 100 characters. Example: <code>RAAST-88120044</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>proof</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="proof"                data-endpoint="POSTapi-v1-donations--number--proof"
               value=""
               data-component="body">
    <br>
<p>Screenshot or photo (JPG, PNG, WebP or PDF, max 5 MB). Must be a file. Must not be greater than 5120 kilobytes. Example: <code>/private/var/folders/kf/lkn293jj00bd1j35hm585zwr0000gn/T/phppe7k0j86rebfaxa3PnP</code></p>
        </div>
        </form>

                <h1 id="me">Me</h1>

    

                                <h2 id="me-GETapi-v1-me">My profile</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Profile plus totals: everything given, and this tax year.</p>

<span id="example-requests-GETapi-v1-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/me" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-me">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;id&quot;: 42,
        &quot;donor_code&quot;: &quot;DNR-2026-000042&quot;,
        &quot;name&quot;: &quot;Ayesha Khan&quot;,
        &quot;type&quot;: {
            &quot;value&quot;: &quot;individual&quot;,
            &quot;label&quot;: &quot;Individual&quot;
        },
        &quot;phone&quot;: &quot;03001234567&quot;,
        &quot;email&quot;: &quot;ayesha@example.com&quot;,
        &quot;city&quot;: &quot;Lahore&quot;,
        &quot;cnic_masked&quot;: &quot;35202-&bull;&bull;&bull;&bull;&bull;&bull;&bull;-1&quot;,
        &quot;has_cnic&quot;: true,
        &quot;phone_verified&quot;: true,
        &quot;email_verified&quot;: false,
        &quot;preferences&quot;: {
            &quot;language&quot;: &quot;en&quot;,
            &quot;whatsapp_opt_in&quot;: true,
            &quot;email_opt_in&quot;: true
        },
        &quot;member_since&quot;: &quot;2026-11-02&quot;,
        &quot;totals&quot;: {
            &quot;given&quot;: {
                &quot;amount&quot;: &quot;7500.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 7,500.00&quot;
            },
            &quot;this_tax_year&quot;: {
                &quot;amount&quot;: &quot;7500.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 7,500.00&quot;
            },
            &quot;tax_year&quot;: &quot;2026-27&quot;,
            &quot;donations&quot;: 2
        }
    },
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-me" data-method="GET"
      data-path="api/v1/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-me"
                    onclick="tryItOut('GETapi-v1-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-me"
                    onclick="cancelTryOut('GETapi-v1-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-me"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="me-PUTapi-v1-me">Update my profile</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Send only what changes.</p>

<span id="example-requests-PUTapi-v1-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "localhost:8000/api/v1/me" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"city\": \"Lahore\",
    \"language\": \"ur\",
    \"whatsapp_opt_in\": true,
    \"email_opt_in\": true,
    \"cnic\": \"35202-1234567-1\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "city": "Lahore",
    "language": "ur",
    "whatsapp_opt_in": true,
    "email_opt_in": true,
    "cnic": "35202-1234567-1"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-me">
</span>
<span id="execution-results-PUTapi-v1-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-me" data-method="PUT"
      data-path="api/v1/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-me"
                    onclick="tryItOut('PUTapi-v1-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-me"
                    onclick="cancelTryOut('PUTapi-v1-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-me"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="city"                data-endpoint="PUTapi-v1-me"
               value="Lahore"
               data-component="body">
    <br>
<p>City. Must not be greater than 100 characters. Example: <code>Lahore</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>language</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="language"                data-endpoint="PUTapi-v1-me"
               value="ur"
               data-component="body">
    <br>
<p>en or ur. Example: <code>ur</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>en</code></li> <li><code>ur</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>whatsapp_opt_in</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-me" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="true"
                   data-endpoint="PUTapi-v1-me"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-me" style="display: none">
            <input type="radio" name="whatsapp_opt_in"
                   value="false"
                   data-endpoint="PUTapi-v1-me"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Receipts and updates on WhatsApp. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email_opt_in</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-me" style="display: none">
            <input type="radio" name="email_opt_in"
                   value="true"
                   data-endpoint="PUTapi-v1-me"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-me" style="display: none">
            <input type="radio" name="email_opt_in"
                   value="false"
                   data-endpoint="PUTapi-v1-me"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Receipts and updates by email. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cnic</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cnic"                data-endpoint="PUTapi-v1-me"
               value="35202-1234567-1"
               data-component="body">
    <br>
<p>Add once, for tax certificates. Example: <code>35202-1234567-1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-me"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="PUTapi-v1-me"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="PUTapi-v1-me"
               value=""
               data-component="body">
    <br>

        </div>
        </form>

                    <h2 id="me-GETapi-v1-me-donations">My donations</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Newest first. Paged.</p>

<span id="example-requests-GETapi-v1-me-donations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/me/donations?status=paid&amp;page=1&amp;per_page=20" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me/donations"
);

const params = {
    "status": "paid",
    "page": "1",
    "per_page": "20",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-me-donations">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [
        {
            &quot;number&quot;: &quot;DN-2026-000123&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;paid&quot;,
                &quot;label&quot;: &quot;Paid&quot;
            },
            &quot;amount&quot;: {
                &quot;amount&quot;: &quot;5000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 5,000.00&quot;
            },
            &quot;refunded&quot;: {
                &quot;amount&quot;: &quot;0.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 0.00&quot;
            },
            &quot;net&quot;: {
                &quot;amount&quot;: &quot;5000.00&quot;,
                &quot;currency&quot;: &quot;PKR&quot;,
                &quot;formatted&quot;: &quot;Rs 5,000.00&quot;
            },
            &quot;payment_method&quot;: {
                &quot;value&quot;: &quot;jazzcash&quot;,
                &quot;label&quot;: &quot;JazzCash&quot;
            },
            &quot;channel&quot;: {
                &quot;value&quot;: &quot;mobile&quot;,
                &quot;label&quot;: &quot;Mobile app&quot;
            },
            &quot;fund&quot;: {
                &quot;code&quot;: &quot;ZAKAT&quot;,
                &quot;name&quot;: &quot;Zakat&quot;,
                &quot;is_zakat&quot;: true
            },
            &quot;campaign&quot;: null,
            &quot;is_anonymous&quot;: false,
            &quot;dedication&quot;: null,
            &quot;created_at&quot;: &quot;2026-12-14T10:30:00+05:00&quot;,
            &quot;paid_at&quot;: &quot;2026-12-14T10:31:12+05:00&quot;,
            &quot;receipt&quot;: {
                &quot;id&quot;: 15,
                &quot;number&quot;: &quot;RC-2026-000015&quot;,
                &quot;download_url&quot;: &quot;https://&hellip;/api/v1/me/receipts/15/download&quot;
            }
        }
    ],
    &quot;message&quot;: &quot;&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 20,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1,
            &quot;has_more&quot;: false
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-me-donations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-me-donations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-me-donations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-me-donations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-me-donations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-me-donations" data-method="GET"
      data-path="api/v1/me/donations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-me-donations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-me-donations"
                    onclick="tryItOut('GETapi-v1-me-donations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-me-donations"
                    onclick="cancelTryOut('GETapi-v1-me-donations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-me-donations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/me/donations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-me-donations"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-me-donations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-me-donations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-me-donations"
               value="paid"
               data-component="query">
    <br>
<p>Only this status, e.g. paid or pending. Example: <code>paid</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-v1-me-donations"
               value="1"
               data-component="query">
    <br>
<p>Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-me-donations"
               value="20"
               data-component="query">
    <br>
<p>1–50, default 20. Example: <code>20</code></p>
            </div>
                </form>

                    <h2 id="me-GETapi-v1-me-receipts--receipt--download">Download a receipt (PDF)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns the PDF file, not JSON.</p>

<span id="example-requests-GETapi-v1-me-receipts--receipt--download">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/me/receipts/15/download" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me/receipts/15/download"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-me-receipts--receipt--download">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-request-id: 9e1e5284-1d34-4699-94cf-00b312db69ca
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-me-receipts--receipt--download" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-me-receipts--receipt--download"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-me-receipts--receipt--download"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-me-receipts--receipt--download" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-me-receipts--receipt--download">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-me-receipts--receipt--download" data-method="GET"
      data-path="api/v1/me/receipts/{receipt}/download"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-me-receipts--receipt--download', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-me-receipts--receipt--download"
                    onclick="tryItOut('GETapi-v1-me-receipts--receipt--download');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-me-receipts--receipt--download"
                    onclick="cancelTryOut('GETapi-v1-me-receipts--receipt--download');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-me-receipts--receipt--download"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/me/receipts/{receipt}/download</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-me-receipts--receipt--download"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-me-receipts--receipt--download"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-me-receipts--receipt--download"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>receipt</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="receipt"                data-endpoint="GETapi-v1-me-receipts--receipt--download"
               value="15"
               data-component="url">
    <br>
<p>The receipt id from a donation. Example: <code>15</code></p>
            </div>
                    </form>

                    <h2 id="me-GETapi-v1-me-tax-certificates">My tax certificates</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>One per tax year (July–June), newest first.</p>

<span id="example-requests-GETapi-v1-me-tax-certificates">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/me/tax-certificates" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me/tax-certificates"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-me-tax-certificates">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-request-id: 1a1631a0-ac3c-4946-83d0-9ebd0fb829eb
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-me-tax-certificates" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-me-tax-certificates"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-me-tax-certificates"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-me-tax-certificates" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-me-tax-certificates">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-me-tax-certificates" data-method="GET"
      data-path="api/v1/me/tax-certificates"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-me-tax-certificates', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-me-tax-certificates"
                    onclick="tryItOut('GETapi-v1-me-tax-certificates');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-me-tax-certificates"
                    onclick="cancelTryOut('GETapi-v1-me-tax-certificates');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-me-tax-certificates"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/me/tax-certificates</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-me-tax-certificates"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-me-tax-certificates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-me-tax-certificates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="me-GETapi-v1-me-tax-certificates--certificate--download">Download a tax certificate (PDF)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns the PDF file, not JSON.</p>

<span id="example-requests-GETapi-v1-me-tax-certificates--certificate--download">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "localhost:8000/api/v1/me/tax-certificates/4/download" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/me/tax-certificates/4/download"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-me-tax-certificates--certificate--download">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-request-id: 5b7fe115-18c4-443c-8e63-fed857a5a6e3
x-api-version: v1
content-language: en
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;errors&quot;: null,
    &quot;meta&quot;: {}
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-me-tax-certificates--certificate--download" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-me-tax-certificates--certificate--download"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-me-tax-certificates--certificate--download"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-me-tax-certificates--certificate--download" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-me-tax-certificates--certificate--download">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-me-tax-certificates--certificate--download" data-method="GET"
      data-path="api/v1/me/tax-certificates/{certificate}/download"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-me-tax-certificates--certificate--download', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-me-tax-certificates--certificate--download"
                    onclick="tryItOut('GETapi-v1-me-tax-certificates--certificate--download');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-me-tax-certificates--certificate--download"
                    onclick="cancelTryOut('GETapi-v1-me-tax-certificates--certificate--download');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-me-tax-certificates--certificate--download"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/me/tax-certificates/{certificate}/download</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-me-tax-certificates--certificate--download"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-me-tax-certificates--certificate--download"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-me-tax-certificates--certificate--download"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>certificate</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="certificate"                data-endpoint="GETapi-v1-me-tax-certificates--certificate--download"
               value="4"
               data-component="url">
    <br>
<p>Example: <code>4</code></p>
            </div>
                    </form>

                <h1 id="devices">Devices</h1>

    

                                <h2 id="devices-POSTapi-v1-devices">Register this device</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Safe to call on every launch. A token already registered to another donor (a shared
phone) moves to the donor logged in now.</p>

<span id="example-requests-POSTapi-v1-devices">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "localhost:8000/api/v1/devices" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"fcm:dQw4w9WgXcQ…\",
    \"platform\": \"android\",
    \"app_version\": \"1.0.3\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/devices"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "fcm:dQw4w9WgXcQ…",
    "platform": "android",
    "app_version": "1.0.3"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-devices">
</span>
<span id="execution-results-POSTapi-v1-devices" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-devices"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-devices"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-devices" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-devices">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-devices" data-method="POST"
      data-path="api/v1/devices"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-devices', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-devices"
                    onclick="tryItOut('POSTapi-v1-devices');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-devices"
                    onclick="cancelTryOut('POSTapi-v1-devices');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-devices"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/devices</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-devices"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-devices"
               value="fcm:dQw4w9WgXcQ…"
               data-component="body">
    <br>
<p>Firebase Cloud Messaging (or APNs) token. Must not be greater than 255 characters. Example: <code>fcm:dQw4w9WgXcQ…</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-v1-devices"
               value="android"
               data-component="body">
    <br>
<p>android or ios. Example: <code>android</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>android</code></li> <li><code>ios</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>app_version</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="app_version"                data-endpoint="POSTapi-v1-devices"
               value="1.0.3"
               data-component="body">
    <br>
<p>App version string. Must not be greater than 20 characters. Example: <code>1.0.3</code></p>
        </div>
        </form>

                    <h2 id="devices-DELETEapi-v1-devices">Remove this device</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Call before logging out so this phone stops getting notifications.</p>

<span id="example-requests-DELETEapi-v1-devices">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "localhost:8000/api/v1/devices" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"fcm:dQw4w9WgXcQ…\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "localhost:8000/api/v1/devices"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "fcm:dQw4w9WgXcQ…"
};

fetch(url, {
    method: "DELETE",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-devices">
</span>
<span id="execution-results-DELETEapi-v1-devices" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-devices"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-devices"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-devices" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-devices">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-devices" data-method="DELETE"
      data-path="api/v1/devices"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-devices', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-devices"
                    onclick="tryItOut('DELETEapi-v1-devices');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-devices"
                    onclick="cancelTryOut('DELETEapi-v1-devices');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-devices"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/devices</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-devices"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="DELETEapi-v1-devices"
               value="fcm:dQw4w9WgXcQ…"
               data-component="body">
    <br>
<p>The push token to remove. Example: <code>fcm:dQw4w9WgXcQ…</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
