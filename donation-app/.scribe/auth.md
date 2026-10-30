# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Log in with a one-time code (POST /auth/otp/send, then /auth/otp/verify) to get a token. Send it as <code>Authorization: Bearer {TOKEN}</code>.
