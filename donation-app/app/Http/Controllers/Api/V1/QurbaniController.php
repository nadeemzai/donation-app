<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DonationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QurbaniBookingRequest;
use App\Http\Resources\V1\QurbaniBookingResource;
use App\Http\Resources\V1\QurbaniSeasonResource;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Services\QurbaniService;
use App\Services\Site\WebDonorService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Qurbani
 *
 * Book Qurbani shares (Week 11, FR-QRB-02), then pay with POST /donations and the
 * booking_token. Booking closes 3 days before Eid.
 */
class QurbaniController extends Controller
{
    /**
     * Qurbani season
     *
     * The season open for booking, with prices and shares left. `data` is null when booking is not open.
     *
     * @unauthenticated
     */
    public function show(Request $request): JsonResponse
    {
        $season = QurbaniSeason::current();

        return ApiResponse::success($season !== null ? QurbaniSeasonResource::make($season)->resolve($request) : null);
    }

    /**
     * Book shares
     *
     * Holds the shares for an hour while the donor pays. Pay it with POST /donations,
     * sending `qurbani_booking_token` and a payment method (fund, amount and donor come
     * from the booking).
     *
     * @unauthenticated
     */
    public function store(QurbaniBookingRequest $request, QurbaniService $qurbani, WebDonorService $donors): JsonResponse
    {
        $season = QurbaniSeason::current();
        $offering = $season !== null ? QurbaniOffering::query()->where('season_id', $season->id)->find($request->integer('offering_id')) : null;

        if ($offering === null) {
            return ApiResponse::error(__('Choose the animal.'), 422, ['offering_id' => [__('Choose the animal.')]]);
        }

        try {
            $donor = $request->donor() ?? $donors->resolve([
                'name' => (string) $request->validated('name'),
                'phone' => $request->optionalString('phone'),
                'email' => $request->optionalString('email'),
                'whatsapp_opt_in' => (bool) $request->validated('whatsapp_opt_in'),
            ], app()->getLocale())['donor'];

            $booking = $qurbani->book($offering, $request->names(), $donor, DonationChannel::Mobile);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(QurbaniBookingResource::make($booking->load('offering'))->resolve($request), __('Booked. Please pay to confirm your shares.'), 201);
    }
}
