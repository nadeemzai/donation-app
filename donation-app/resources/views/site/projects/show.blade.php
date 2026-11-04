@extends('layouts.site')

@php
    $fundUrl = route('funds.show', strtolower($project->fund->code));
    $donateUrl = Route::has('donate') && $project->fund->is_active ? route('donate', ['fund' => strtolower($project->fund->code)]) : null;
@endphp

@section('title', $project->name)
@section('description', $project->summary ?: __('Progress and news from :name.', ['name' => $project->name]))

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        <nav class="text-sm text-ink-500" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('projects.index') }}" class="hover:text-brand-700">{{ __('Projects') }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $fundUrl }}" class="hover:text-brand-700">{{ $project->fund->name }}</a>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-brand-600">{{ $project->status->label() }}@if ($project->location) · {{ $project->location }}@endif</span>
                <h1 class="mt-2 font-display text-3xl font-bold leading-tight sm:text-4xl">{{ $project->name }}</h1>
                @if ($project->summary)
                    <p class="mt-3 text-lg text-ink-700">{{ $project->summary }}</p>
                @endif
                @if ($project->description)
                    <div class="mt-6 whitespace-pre-line text-ink-800">{{ $project->description }}</div>
                @endif

                <h2 class="mt-12 font-display text-xl font-semibold">{{ __('Updates') }}</h2>
                @if ($updates->isEmpty())
                    <p class="mt-3 text-sm text-ink-500">{{ __('News from this project will appear here.') }}</p>
                @else
                    <ol class="mt-4 space-y-8 border-l-2 border-brand-100 pl-6">
                        @foreach ($updates as $update)
                            <li id="update-{{ $update->id }}">
                                <time class="text-xs font-semibold uppercase tracking-wider text-brand-600" datetime="{{ $update->published_at?->toDateString() }}">{{ $update->published_at?->format('d M Y') }}</time>
                                <h3 class="mt-1 font-display text-lg font-semibold">{{ $update->title }}</h3>
                                <div class="prose-story mt-2 text-sm">{!! \App\Support\SafeHtml::clean($update->body) !!}</div>
                                @php($photos = $update->getMedia(\App\Models\ImpactUpdate::PHOTOS))
                                @if ($photos->isNotEmpty())
                                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        @foreach ($photos as $photo)
                                            <img src="{{ $photo->getUrl() }}" alt="" loading="lazy" class="aspect-square w-full rounded-2xl object-cover">
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-3xl bg-white p-6 shadow-lg ring-1 ring-ink-900/5">
                    <p class="text-sm text-ink-500">{{ __('Budget used') }}</p>
                    <p class="font-display text-3xl font-bold">{{ $project->spentPercent() }}%</p>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-brand-100" role="progressbar" aria-valuenow="{{ $project->spentPercent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Budget used') }}">
                        <div class="h-full rounded-full bg-brand-600" style="width: {{ $project->spentPercent() }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-ink-500">{{ __('of a :budget budget, paid from the :fund fund.', ['budget' => \App\Support\Money::rupees((string) $project->budget), 'fund' => $project->fund->name]) }}</p>

                    @if ($project->milestones->isNotEmpty())
                        <h2 class="mt-6 font-display text-base font-semibold">{{ __('Milestones') }} <span class="text-sm font-normal text-ink-500">· {{ $project->milestoneProgress() }}</span></h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($project->milestones as $milestone)
                                <li class="flex items-start gap-2">
                                    <span @class(['mt-1 h-3 w-3 shrink-0 rounded-full', 'bg-brand-600' => $milestone->status === \App\Enums\MilestoneStatus::Done, 'bg-amber-400' => $milestone->status === \App\Enums\MilestoneStatus::InProgress, 'bg-ink-900/15' => $milestone->status === \App\Enums\MilestoneStatus::Pending]) aria-hidden="true"></span>
                                    <span>
                                        <span class="font-medium">{{ $milestone->title }}</span>
                                        <span class="block text-xs text-ink-500">{{ $milestone->status->label() }}@if ($milestone->completed_at) · {{ $milestone->completed_at->format('d M Y') }}@elseif ($milestone->due_on) · {{ __('due :date', ['date' => $milestone->due_on->format('d M Y')]) }}@endif</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($donateUrl && ! $project->status->isClosed())
                        <a href="{{ $donateUrl }}" class="btn-primary mt-6 w-full py-3.5 text-base">{{ __('Give to the :fund fund', ['fund' => $project->fund->name]) }}</a>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection
