<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\DonationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\QurbaniBookingRequest;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Services\QurbaniService;
use App\Services\Site\WebDonorService;
use App\Support\QurbaniToken;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public Qurbani booking (Week 11, FR-QRB-02): choose the animal, give a name per share and your
 * contact details; the booking then goes to the normal donate page to be paid.
 */
class QurbaniController extends Controller
{
    public function __construct(
        private readonly QurbaniService $qurbani,
        private readonly WebDonorService $donors,
    ) {}

    public function show(): View
    {
        $season = QurbaniSeason::current();

        return view('site.qurbani', [
            'season' => $season,
            'offerings' => $season?->offerings->where('is_active', true)->values() ?? collect(),
            'maxShares' => QurbaniService::MAX_SHARES,
        ]);
    }

    public function store(QurbaniBookingRequest $request): RedirectResponse
    {
        $season = QurbaniSeason::current();
        $offering = $season !== null
            ? QurbaniOffering::query()->where('season_id', $season->id)->find($request->integer('offering_id'))
            : null;

        if ($offering === null) {
            return back()->withInput()->withErrors(['offering_id' => __('Choose the animal.')]);
        }

        try {
            ['donor' => $donor] = $this->donors->resolve([
                'name' => (string) $request->validated('name'),
                'phone' => $request->optionalString('phone'),
                'email' => $request->optionalString('email'),
                'whatsapp_opt_in' => (bool) $request->validated('whatsapp_opt_in'),
            ], app()->getLocale());

            $booking = $this->qurbani->book($offering, $request->names(), $donor, DonationChannel::Web);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['form' => $e->getMessage()]);
        }

        return redirect()->route('donate', ['qurbani' => QurbaniToken::for($booking)]);
    }
}
