<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on built frontend assets (public/build is not in git / CI).
        $this->withoutVite();

        // Every confirmed donation now issues a receipt PDF (Week 5). Never start Chrome or
        // write real files in tests; tests that check PDFs call Pdf::fake() again themselves.
        Pdf::fake();
        Storage::fake('local');
    }
}
