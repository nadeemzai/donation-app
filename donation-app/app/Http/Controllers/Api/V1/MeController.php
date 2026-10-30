<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DonationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\V1\DonationResource;
use App\Http\Resources\V1\DonorResource;
use App\Http\Resources\V1\TaxCertificateResource;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Receipt;
use App\Models\TaxCertificate;
use App\Services\ReceiptService;
use App\Services\TaxCertificateService;
use App\Support\ApiFormat;
use App\Support\ApiResponse;
use App\Support\Cnic;
use App\Support\FiscalYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Me
 *
 * @authenticated
 *
 * The logged-in donor (Week 7 Day 4). Needs `Authorization: Bearer {token}`.
 * Someone else's receipt or certificate is a 404, so ids can't be probed.
 */
class MeController extends Controller
{
    /**
     * My profile
     *
     * Profile plus totals: everything given, and this tax year.
     *
     * @response 200 {"success":true,"data":{"id":42,"donor_code":"DNR-2026-000042","name":"Ayesha Khan","type":{"value":"individual","label":"Individual"},"phone":"03001234567","email":"ayesha@example.com","city":"Lahore","cnic_masked":"35202-•••••••-1","has_cnic":true,"phone_verified":true,"email_verified":false,"preferences":{"language":"en","whatsapp_opt_in":true,"email_opt_in":true},"member_since":"2026-11-02","totals":{"given":{"amount":"7500.00","currency":"PKR","formatted":"Rs 7,500.00"},"this_tax_year":{"amount":"7500.00","currency":"PKR","formatted":"Rs 7,500.00"},"tax_year":"2026-27","donations":2}},"message":"","errors":null,"meta":{}}
     */
    public function show(Request $request): JsonResponse
    {
        $donor = $this->donor($request);
        $year = FiscalYear::containing(now());
        $total = '0.00';
        $thisYear = '0.00';
        $counted = Donation::query()->counted()->where('donor_id', $donor->id)->get(['amount', 'refunded_amount', 'paid_at']);

        foreach ($counted as $donation) {
            $total = bcadd($total, $donation->netAmount(), 2);

            if ($donation->paid_at !== null && $donation->paid_at->between($year->start(), $year->end())) {
                $thisYear = bcadd($thisYear, $donation->netAmount(), 2);
            }
        }

        return ApiResponse::success([
            ...DonorResource::make($donor)->resolve($request),
            'totals' => [
                'given' => ApiFormat::money($total),
                'this_tax_year' => ApiFormat::money($thisYear),
                'tax_year' => $year->label(),
                'donations' => $counted->count(),
            ],
        ]);
    }

    /**
     * Update my profile
     *
     * Send only what changes.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $donor = $this->donor($request);
        $data = $request->validated();

        if (array_key_exists('city', $data)) {
            $donor->city = filled($data['city']) ? trim((string) $data['city']) : null;
        }

        $prefs = $donor->preferences ?? [];

        foreach (['language', 'whatsapp_opt_in', 'email_opt_in'] as $key) {
            if (array_key_exists($key, $data)) {
                $prefs[$key] = $key === 'language' ? (string) $data[$key] : (bool) $data[$key];
            }
        }

        $donor->preferences = $prefs;

        if (filled($data['cnic'] ?? null) && blank($donor->cnic)) {
            $digits = Cnic::normalize((string) $data['cnic']);
            $donor->cnic = substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
        }

        $donor->save(); // audited

        return ApiResponse::success(DonorResource::make($donor->fresh() ?? $donor)->resolve($request), __('Your details are saved.'));
    }

    /**
     * My donations
     *
     * Newest first. Paged.
     *
     * @queryParam status string Only this status, e.g. paid or pending. Example: paid
     * @queryParam page integer Example: 1
     * @queryParam per_page integer 1–50, default 20. Example: 20
     *
     * @response 200 {"success":true,"data":[{"number":"DN-2026-000123","status":{"value":"paid","label":"Paid"},"amount":{"amount":"5000.00","currency":"PKR","formatted":"Rs 5,000.00"},"refunded":{"amount":"0.00","currency":"PKR","formatted":"Rs 0.00"},"net":{"amount":"5000.00","currency":"PKR","formatted":"Rs 5,000.00"},"payment_method":{"value":"jazzcash","label":"JazzCash"},"channel":{"value":"mobile","label":"Mobile app"},"fund":{"code":"ZAKAT","name":"Zakat","is_zakat":true},"campaign":null,"is_anonymous":false,"dedication":null,"created_at":"2026-12-14T10:30:00+05:00","paid_at":"2026-12-14T10:31:12+05:00","receipt":{"id":15,"number":"RC-2026-000015","download_url":"https://…/api/v1/me/receipts/15/download"}}],"message":"","errors":null,"meta":{"pagination":{"current_page":1,"per_page":20,"total":1,"last_page":1,"has_more":false}}}
     */
    public function donations(Request $request): JsonResponse
    {
        $donor = $this->donor($request);
        $status = DonationStatus::tryFrom($request->string('status')->toString());

        $page = Donation::query()
            ->where('donor_id', $donor->id)
            ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
            ->with(['fund', 'campaign', 'receipt'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(ApiResponse::perPage($request));

        return ApiResponse::paginated($page, DonationResource::class);
    }

    /**
     * Download a receipt (PDF)
     *
     * Returns the PDF file, not JSON.
     *
     * @urlParam receipt integer required The receipt id from a donation. Example: 15
     */
    public function receipt(Request $request, int $receipt, ReceiptService $receipts): StreamedResponse
    {
        $donor = $this->donor($request);
        $found = Receipt::query()->with('donation')->find($receipt);

        abort_unless($found !== null && $found->donation->donor_id === $donor->id && ! $found->isVoid(), 404);

        $file = $receipts->file($found);

        return Storage::disk($file['disk'])->download($file['path'], $file['name'], ['Content-Type' => 'application/pdf']);
    }

    /**
     * My tax certificates
     *
     * One per tax year (July–June), newest first.
     */
    public function certificates(Request $request): JsonResponse
    {
        $donor = $this->donor($request);

        $certificates = TaxCertificate::query()->valid()->where('donor_id', $donor->id)->orderByDesc('fiscal_year')->get();

        return ApiResponse::success(TaxCertificateResource::collection($certificates)->resolve($request));
    }

    /**
     * Download a tax certificate (PDF)
     *
     * Returns the PDF file, not JSON.
     *
     * @urlParam certificate integer required Example: 4
     */
    public function certificate(Request $request, int $certificate, TaxCertificateService $certificates): StreamedResponse
    {
        $donor = $this->donor($request);
        $found = TaxCertificate::query()->find($certificate);

        abort_unless($found !== null && $found->donor_id === $donor->id && ! $found->isVoid(), 404);

        $file = $certificates->file($found);

        return Storage::disk($file['disk'])->download($file['path'], $file['name'], ['Content-Type' => 'application/pdf']);
    }

    private function donor(Request $request): Donor
    {
        $donor = $request->user();
        abort_unless($donor instanceof Donor, 401);

        return $donor;
    }
}
