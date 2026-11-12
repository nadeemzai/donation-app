<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Receipt;
use App\Models\TaxCertificate;
use App\Services\ReceiptService;
use App\Services\TaxCertificateService;
use App\Support\Cnic;
use App\Support\FiscalYear;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My donations" (Week 6 Day 4): a logged-in donor's donations, receipts, tax
 * certificates and preferences. Every query is limited to the logged-in donor;
 * someone else's receipt or certificate is a 404, never a 403, so ids can't be probed.
 *
 * Name, phone and email are not editable here — changing them could redirect receipts,
 * so donors ask us. A CNIC can be added once (for tax certificates), not changed.
 */
class DonorPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $donor = $this->donor($request);
        $year = FiscalYear::containing(now());

        $counted = Donation::query()->counted()->where('donor_id', $donor->id)->get(['amount', 'refunded_amount', 'paid_at']);
        $total = '0.00';
        $thisYear = '0.00';

        foreach ($counted as $donation) {
            $total = bcadd($total, $donation->netAmount(), 2);

            if ($donation->paid_at !== null && $donation->paid_at->between($year->start(), $year->end())) {
                $thisYear = bcadd($thisYear, $donation->netAmount(), 2);
            }
        }

        return view('site.donor.dashboard', [
            'donor' => $donor,
            'donations' => Donation::query()
                ->where('donor_id', $donor->id)
                ->with(['fund', 'campaign', 'receipt'])
                ->latest('created_at')
                ->latest('id')
                ->paginate(15),
            'certificates' => TaxCertificate::query()->valid()->where('donor_id', $donor->id)->orderByDesc('fiscal_year')->get(),
            'total' => $total,
            'thisYear' => $thisYear,
            'year' => $year,
            'count' => $counted->count(),
        ]);
    }

    public function receipt(Request $request, Receipt $receipt, ReceiptService $receipts): StreamedResponse
    {
        $donor = $this->donor($request);
        $receipt->loadMissing('donation');

        abort_unless($receipt->donation->donor_id === $donor->id && ! $receipt->isVoid(), 404);

        $file = $receipts->file($receipt);

        return Storage::disk($file['disk'])->download($file['path'], $file['name']);
    }

    public function certificate(Request $request, TaxCertificate $certificate, TaxCertificateService $certificates): StreamedResponse
    {
        $donor = $this->donor($request);

        abort_unless($certificate->donor_id === $donor->id && ! $certificate->isVoid(), 404);

        $file = $certificates->file($certificate);

        return Storage::disk($file['disk'])->download($file['path'], $file['name']);
    }

    public function profile(Request $request): View
    {
        return view('site.donor.profile', ['donor' => $this->donor($request)]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $donor = $this->donor($request);
        $canAddCnic = blank($donor->cnic);

        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'in:en,ur'],
            'whatsapp_opt_in' => ['boolean'],
            'email_opt_in' => ['boolean'],
            'cnic' => $canAddCnic ? ['nullable', 'string', 'max:20', $this->cnicRule($donor)] : ['prohibited'],
        ], [
            'cnic.prohibited' => __('Your CNIC is already on record. Please contact us to change it.'),
        ]);

        $donor->city = filled($data['city'] ?? null) ? trim((string) $data['city']) : null;
        $donor->preferences = array_merge($donor->preferences ?? [], [
            'language' => (string) $data['language'],
            'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
            'email_opt_in' => $request->boolean('email_opt_in'),
        ]);

        if ($canAddCnic && filled($data['cnic'] ?? null)) {
            $digits = Cnic::normalize((string) $data['cnic']);
            $donor->cnic = substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
        }

        $donor->save(); // audited: the change history shows the donor made it

        return redirect()->route('donor.profile')->with('status', __('Your details are saved.'));
    }

    private function cnicRule(Donor $donor): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($donor): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) || ! Cnic::isValid($value)) {
                $fail(__('Enter the 13-digit CNIC, e.g. 35202-1234567-1.'));

                return;
            }

            if (Donor::withTrashed()->where('cnic_hash', Cnic::hash($value))->whereKeyNot($donor->id)->exists()) {
                $fail(__('This CNIC is already on another record. Please contact us and we will sort it out.'));
            }
        };
    }

    private function donor(Request $request): Donor
    {
        $donor = $request->user('donor');
        abort_unless($donor instanceof Donor, 403);

        return $donor;
    }
}
