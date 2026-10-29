<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Settings\GeneralSettings;
use App\Support\PakistanPhone;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The website contact form (Week 6 Day 5). Saved for staff (Website → Messages) and,
 * when the organisation email is set, copied there. Spam guard: a hidden "website"
 * field real people never fill in, plus a rate limit on the route.
 */
class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // A bot filled the hidden field: pretend it worked, keep nothing.
        if (filled($request->input('website'))) {
            return back()->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! PakistanPhone::isValid($value))) {
                    $fail(__('Enter a Pakistani mobile number, e.g. 0300-1234567.'));
                }
            }],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'email.required_without' => __('Give us an email address or a mobile number so we can reply.'),
            'phone.required_without' => __('Give us a mobile number or an email address so we can reply.'),
        ]);

        $message = ContactMessage::query()->create([
            'name' => trim((string) $data['name']),
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
            'phone' => filled($data['phone'] ?? null) ? PakistanPhone::normalize((string) $data['phone']) : null,
            'subject' => filled($data['subject'] ?? null) ? trim((string) $data['subject']) : null,
            'message' => trim((string) $data['message']),
            'ip_address' => $request->ip(),
        ]);

        $inbox = app(GeneralSettings::class)->email;

        if (filled($inbox)) {
            try {
                Mail::to((string) $inbox)->queue(new ContactMessageReceived($message));
            } catch (Throwable $e) {
                report($e); // the message is saved; the copy is a convenience
            }
        }

        return back()->with('contact_sent', true);
    }
}
