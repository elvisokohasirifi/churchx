@extends(backpack_view('blank'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="mb-1">Help Center</h1>
                    <p class="text-muted mb-2">Definitions, field requirements, and instructions matched to what your account can access.</p>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($roleNames as $roleName)
                            <span class="badge bg-blue-lt text-blue"><i class="la la-user-shield me-1"></i>{{ $roleName }}</span>
                        @empty
                            <span class="badge bg-secondary-lt text-secondary">No active role</span>
                        @endforelse
                        <span class="badge bg-green-lt text-green">{{ $articleCount }} {{ Str::plural('guide', $articleCount) }}</span>
                    </div>
                </div>
                <a class="btn btn-outline-secondary" href="{{ backpack_url('dashboard') }}"><i class="la la-arrow-left me-1"></i> Dashboard</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <label class="form-label" for="help-search">Search help</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="la la-search"></i></span>
                        <input id="help-search" class="form-control form-control-lg" type="search" placeholder="Search models, fields, or tasks…" autocomplete="off" data-help-search>
                    </div>
                    <div class="form-hint mt-2">Try “member”, “required”, “expense approval”, or a field name. Press Escape to clear.</div>
                </div>
            </div>

            <div class="row g-4">
                <aside class="col-lg-3 d-print-none">
                    <div class="card position-sticky help-navigation-card">
                        <div class="card-header"><h2 class="card-title">In this guide</h2></div>
                        <div class="list-group list-group-flush" data-help-navigation>
                            @foreach($sections as $section)
                                <div data-help-nav-section>
                                    <div class="px-3 pt-3 pb-1 text-uppercase text-muted small fw-bold">{{ $section['title'] }}</div>
                                    @foreach($section['articles'] as $article)
                                        <a class="list-group-item list-group-item-action py-2" href="#{{ $article['id'] }}" data-help-nav-link="{{ $article['id'] }}">{{ $article['title'] }}</a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </aside>

                <div class="col-lg-9">
                    <div class="alert alert-info d-none" data-help-empty>
                        <i class="la la-info-circle me-1"></i> No visible help articles match that search.
                    </div>

                    @foreach($sections as $section)
                        <section class="mb-4" data-help-section>
                            <h2 class="mb-3">{{ $section['title'] }}</h2>
                            @foreach($section['articles'] as $article)
                                <article
                                    id="{{ $article['id'] }}"
                                    class="card mb-4 scroll-margin-top"
                                    data-help-article="{{ $article['id'] }}"
                                    data-help-search-text="{{ Str::lower($section['title'].' '.$article['title'].' '.$article['definition'].' '.collect($article['models'])->map(fn ($definition, $model) => $model.' '.$definition)->implode(' ').' '.collect($article['fields'])->pluck('name')->implode(' ').' '.collect($article['fields'])->pluck('definition')->implode(' ').' '.collect($article['tasks'])->pluck('title')->implode(' ')) }}"
                                >
                                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                        <div>
                                            <div class="text-uppercase text-muted small fw-semibold">{{ $section['title'] }}</div>
                                            <h3 class="card-title fs-2 mb-0">{{ $article['title'] }}</h3>
                                        </div>
                                        @if($article['link'])
                                            <a class="btn btn-sm btn-primary ms-auto" href="{{ $article['link'] }}">Open page <i class="la la-arrow-right ms-1"></i></a>
                                        @endif
                                    </div>
                                    <div class="card-body">
                                        <p class="fs-3">{{ $article['definition'] }}</p>

                                        @if($article['models'] !== [])
                                            <h4 class="mt-4">Model definitions</h4>
                                            <dl class="row mb-0">
                                                @foreach($article['models'] as $model => $definition)
                                                    <dt class="col-md-3"><code>{{ $model }}</code></dt>
                                                    <dd class="col-md-9">{{ $definition }}</dd>
                                                @endforeach
                                            </dl>
                                        @endif

                                        @if($article['fields'] !== [])
                                            <h4 class="mt-4">Fields and requirements</h4>
                                            <div class="table-responsive border rounded">
                                                <table class="table table-vcenter mb-0">
                                                    <thead><tr><th>Field</th><th>Definition</th><th>Requirement</th></tr></thead>
                                                    <tbody>
                                                        @foreach($article['fields'] as $field)
                                                            <tr>
                                                                <td class="fw-semibold text-nowrap">{{ $field['name'] }}</td>
                                                                <td>{{ $field['definition'] }}</td>
                                                                <td>
                                                                    @if($field['requirement'] === 'Optional')
                                                                        <span class="badge bg-secondary-lt text-secondary">Optional</span>
                                                                    @else
                                                                        <span class="badge bg-orange-lt text-orange">{{ $field['requirement'] }}</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        @if($article['tasks'] !== [])
                                            <h4 class="mt-4">How to perform tasks</h4>
                                            <div class="accordion" id="tasks-{{ $article['id'] }}">
                                                @foreach($article['tasks'] as $taskIndex => $task)
                                                    <div class="accordion-item">
                                                        <h5 class="accordion-header">
                                                            <button class="accordion-button {{ $taskIndex === 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#task-{{ $article['id'] }}-{{ $taskIndex }}" aria-expanded="{{ $taskIndex === 0 ? 'true' : 'false' }}">
                                                                {{ $task['title'] }}
                                                            </button>
                                                        </h5>
                                                        <div id="task-{{ $article['id'] }}-{{ $taskIndex }}" class="accordion-collapse collapse {{ $taskIndex === 0 ? 'show' : '' }}" data-bs-parent="#tasks-{{ $article['id'] }}">
                                                            <div class="accordion-body">
                                                                <ol class="mb-0 ps-3">
                                                                    @foreach($task['steps'] as $step)
                                                                        <li class="mb-2">{{ $step }}</li>
                                                                    @endforeach
                                                                </ol>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@pushOnce('after_styles')
    <style>
        .scroll-margin-top {
            scroll-margin-top: 1rem;
        }

        .help-navigation-card {
            top: 1rem;
            max-height: calc(100vh - 2rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .help-navigation-card .card-header {
            flex: 0 0 auto;
        }

        .help-navigation-card [data-help-navigation] {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
        }

        @media (max-width: 991.98px) {
            .help-navigation-card {
                position: static !important;
                max-height: min(26rem, 60vh);
            }
        }
    </style>
@endPushOnce

@pushOnce('after_scripts')
    <script src="{{ asset('js/help-center.js') }}?v=1"></script>
@endPushOnce
