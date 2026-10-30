<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\SendCodeRequest;
use App\Http\Requests\Api\V1\VerifyCodeRequest;
use App\Http\Resources\V1\DonorResource;
use App\Models\Donor;
use App\Services\Site\DonorLoginService;
use App\Services\Site\WebDonorService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Auth
 *
 * Donors log in with a one-time code — no passwords (Week 7 Day 2).
 *
 * 1. `POST /auth/login` (or `/auth/register` for a new donor) with a mobile number or email.
 *    A 6-digit code goes to WhatsApp (mobile number, when WhatsApp is set up) or email.
 * 2. `POST /auth/otp/verify` with the code → an API token. Send it as `Authorization: Bearer …`.
 *
 * The answer to step 1 is the same whether or not the number belongs to a donor, so the
 * API can't be used to find out who gives. Codes last 10 minutes, work once and die after
 * 5 wrong tries. Tokens last 90 days.
 */
class AuthController extends Controller
{
    public const TOKEN_DAYS = 90;

    public function __construct(private readonly DonorLoginService $login) {}

    /**
     * Start login
     *
     * Sends a one-time code to the mobile number or email.
     *
     * @unauthenticated
     *
     * @response 200 {"success":true,"data":{"sent_to":"0300•••4567","expires_in":600},"message":"If this number or email is on our records, we have sent a 6-digit code.","errors":null,"meta":{}}
     * @response 422 {"success":false,"data":null,"message":"Too many codes asked for. Please try again in 9 minutes.","errors":null,"meta":{}}
     */
    public function login(SendCodeRequest $request): JsonResponse
    {
        $identifier = $request->identifier();
        $this->login->requestCode($identifier); // DomainException (too many) → 422

        return $this->codeSent($identifier);
    }

    /**
     * Send the code again
     *
     * Same as start login; use it for the "send a new code" button.
     *
     * @unauthenticated
     */
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * Register
     *
     * Creates a donor record for a new mobile number and sends the login code. If the number
     * (or email) already belongs to a donor, nothing is changed and the code goes to them —
     * the answer looks the same either way.
     *
     * @unauthenticated
     *
     * @response 200 {"success":true,"data":{"sent_to":"0300•••4567","expires_in":600},"message":"If this number or email is on our records, we have sent a 6-digit code.","errors":null,"meta":{}}
     */
    public function register(RegisterRequest $request, WebDonorService $donors): JsonResponse
    {
        $phone = (string) DonorLoginService::normalise((string) $request->validated('phone'));
        $language = $request->optionalString('language') ?? app()->getLocale();

        try {
            ['donor' => $donor] = $donors->resolve([
                'name' => (string) $request->validated('name'),
                'phone' => $phone,
                'email' => $request->optionalString('email'),
                'whatsapp_opt_in' => (bool) $request->validated('whatsapp_opt_in'),
            ], $language);

            if ($donor->wasRecentlyCreated && $request->optionalString('city') !== null) {
                $donor->city = $request->optionalString('city');
                $donor->save();
            }
        } catch (DomainException) {
            // An archived donor: say nothing different, send nothing.
            return $this->codeSent($phone);
        }

        $this->login->requestCode($phone);

        return $this->codeSent($phone);
    }

    /**
     * Verify the code
     *
     * Swaps the code for an API token.
     *
     * @unauthenticated
     *
     * @response 200 {"success":true,"data":{"token":"12|f3k…","token_type":"Bearer","expires_at":"2027-03-14T10:30:00+05:00","donor":{"id":42,"donor_code":"DNR-2026-000042","name":"Ayesha Khan"}},"message":"Logged in.","errors":null,"meta":{}}
     * @response 422 {"success":false,"data":null,"message":"That code is wrong or has expired.","errors":{"code":["That code is wrong or has expired. Check it, or ask for a new one."]},"meta":{}}
     */
    public function verify(VerifyCodeRequest $request): JsonResponse
    {
        $identifier = $request->identifier();
        $donor = $this->login->verify($identifier, (string) $request->validated('code'));

        if ($donor === null) {
            $message = __('That code is wrong or has expired. Check it, or ask for a new one.');

            return ApiResponse::error(__('That code is wrong or has expired.'), 422, ['code' => [$message]]);
        }

        $expiresAt = now()->addDays(self::TOKEN_DAYS);
        $token = $donor->createToken(mb_substr((string) $request->validated('device_name'), 0, 100), ['donor'], $expiresAt);

        $this->login->recordLogin($donor, $identifier, 'app');

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->setTimezone('Asia/Karachi')->toIso8601String(),
            'donor' => DonorResource::make($donor->fresh() ?? $donor)->resolve($request),
        ], __('Logged in.'));
    }

    /**
     * Log out
     *
     * Ends this device's token. Send `all=true` to log out of every device.
     *
     * @authenticated
     *
     * @bodyParam all boolean Log out everywhere. Example: false
     *
     * @response 200 {"success":true,"data":null,"message":"Logged out.","errors":null,"meta":{}}
     */
    public function logout(Request $request): JsonResponse
    {
        $donor = $request->user();
        abort_unless($donor instanceof Donor, 401);

        if ($request->boolean('all')) {
            $donor->tokens()->delete();
        } else {
            $donor->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, __('Logged out.'));
    }

    private function codeSent(string $identifier): JsonResponse
    {
        return ApiResponse::success([
            'sent_to' => DonorLoginService::mask($identifier),
            'expires_in' => DonorLoginService::CODE_TTL_MINUTES * 60,
        ], __('If this number or email is on our records, we have sent a 6-digit code.'));
    }
}
