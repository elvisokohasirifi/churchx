<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChurchX — Church management, beautifully organized</title>
    <meta name="description" content="ChurchX brings members, services, finances, departments, events, and reporting into one secure church operations platform.">
    <meta name="theme-color" content="#07110f">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 font-sans text-white antialiased selection:bg-lime-300 selection:text-ink-950">
    <div class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[58rem] bg-[radial-gradient(circle_at_18%_14%,rgba(192,240,90,0.16),transparent_28%),radial-gradient(circle_at_80%_10%,rgba(91,194,255,0.12),transparent_30%)]"></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-20 h-[58rem] bg-[linear-gradient(rgba(255,255,255,0.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.035)_1px,transparent_1px)] [background-size:64px_64px] [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>

        <header class="relative z-50 border-b border-white/10 bg-ink-950/75 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8" aria-label="Main navigation">
                <a href="#top" class="group flex items-center gap-3" aria-label="ChurchX home">
                    <span class="grid size-10 place-items-center rounded-xl bg-lime-300 text-ink-950 shadow-[0_0_30px_rgba(192,240,90,0.18)] transition-transform group-hover:-rotate-3">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18M5 8h14" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/></svg>
                    </span>
                    <span class="text-lg font-semibold tracking-tight">Church<span class="text-lime-300">X</span></span>
                </a>
                <div class="hidden items-center gap-8 text-sm text-white/65 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#operations" class="transition hover:text-white">Operations</a>
                    <a href="#security" class="transition hover:text-white">Security</a>
                </div>
                <a href="{{ route('backpack.auth.login') }}" class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-4 py-2.5 text-sm font-semibold transition hover:border-lime-300/50 hover:bg-lime-300 hover:text-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-300">
                    Admin login
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </nav>
        </header>

        <main id="top">
            <section class="mx-auto grid max-w-7xl items-center gap-14 px-6 pb-24 pt-20 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:pb-32 lg:pt-28">
                <div>
                    <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-lime-300/20 bg-lime-300/8 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-lime-200">
                        <span class="size-1.5 rounded-full bg-lime-300 shadow-[0_0_12px_rgba(192,240,90,0.9)]"></span>
                        Church operations, beautifully organized
                    </div>
                    <h1 class="max-w-3xl text-balance text-5xl font-semibold leading-[0.96] tracking-[-0.055em] sm:text-6xl lg:text-7xl">One calm command center for <span class="text-lime-300">every branch.</span></h1>
                    <p class="mt-7 max-w-xl text-pretty text-lg leading-8 text-white/60">ChurchX gives ministry teams a clear, secure way to care for people, run services, manage finances, and understand what is happening across the church.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('backpack.auth.login') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-lime-300 px-6 py-3.5 text-sm font-bold text-ink-950 shadow-[0_14px_45px_rgba(192,240,90,0.16)] transition hover:-translate-y-0.5 hover:bg-lime-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-300 motion-reduce:transform-none">
                            Open admin portal
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="#features" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-6 py-3.5 text-sm font-semibold transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Explore the platform</a>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-white/55">
                        @foreach (['Multi-branch ready', 'Role-based access', 'Audit-ready records'] as $benefit)
                            <span class="inline-flex items-center gap-2"><svg class="size-4 text-lime-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $benefit }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="relative lg:pl-8">
                    <div class="absolute -inset-6 -z-10 rounded-[3rem] bg-gradient-to-br from-lime-300/12 via-transparent to-sky-400/10 blur-2xl"></div>
                    <div class="overflow-hidden rounded-[1.75rem] border border-white/12 bg-[#0b1815]/90 shadow-2xl shadow-black/40 ring-1 ring-white/5 backdrop-blur">
                        <div class="flex items-center justify-between border-b border-white/8 px-5 py-4 sm:px-6">
                            <div class="flex gap-2"><span class="size-2.5 rounded-full bg-[#ff6b5f]"></span><span class="size-2.5 rounded-full bg-[#f4c95d]"></span><span class="size-2.5 rounded-full bg-lime-300"></span></div>
                            <span class="rounded-full bg-white/5 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">Live overview</span>
                        </div>
                        <div class="p-5 sm:p-7">
                            <div class="flex items-end justify-between gap-6">
                                <div><p class="text-xs font-medium uppercase tracking-[0.16em] text-white/40">This month</p><p class="mt-2 text-2xl font-semibold tracking-tight">Ministry overview</p></div>
                                <div class="hidden items-center gap-2 text-xs text-lime-200 sm:flex"><span class="size-2 rounded-full bg-lime-300"></span>All systems current</div>
                            </div>
                            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ([['Members', '2,864', '+84 this month', 'text-lime-300'], ['Attendance', '91%', 'Across 6 services', 'text-sky-300'], ['Giving', '+12.4%', 'Verified & posted', 'text-amber-300']] as [$label, $value, $detail, $color])
                                    <div class="rounded-2xl border border-white/8 bg-white/[0.035] p-4 last:col-span-2 sm:last:col-span-1">
                                        <p class="text-xs text-white/45">{{ $label }}</p><p class="mt-4 text-2xl font-semibold">{{ $value }}</p><p class="mt-1 text-[11px] {{ $color }}">{{ $detail }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-[1.35fr_0.65fr]">
                                <div class="rounded-2xl border border-white/8 bg-white/[0.035] p-5">
                                    <div class="flex justify-between"><p class="text-sm font-medium">Weekly attendance</p><span class="text-[10px] uppercase tracking-wider text-white/35">7 weeks</span></div>
                                    <div class="mt-7 flex h-24 items-end gap-2" aria-label="Attendance growth chart">
                                        @foreach ([44, 58, 48, 69, 62, 78, 91] as $height)<span class="flex-1 rounded-t-md bg-gradient-to-t from-lime-300/25 to-lime-300" style="height: {{ $height }}%"></span>@endforeach
                                    </div>
                                    <div class="mt-2 flex justify-between text-[9px] uppercase tracking-wider text-white/25"><span>W1</span><span>W2</span><span>W3</span><span>W4</span><span>W5</span><span>W6</span><span>W7</span></div>
                                </div>
                                <div class="rounded-2xl border border-white/8 bg-white/[0.035] p-5">
                                    <p class="text-sm font-medium">Branch health</p>
                                    <div class="mt-5 space-y-4">
                                        @foreach ([['Central', '88%'], ['North', '72%'], ['East', '64%']] as [$branch, $progress])
                                            <div><div class="mb-1.5 flex justify-between text-[10px] text-white/45"><span>{{ $branch }}</span><span>{{ $progress }}</span></div><div class="h-1.5 overflow-hidden rounded-full bg-white/8"><div class="h-full rounded-full bg-lime-300" style="width: {{ $progress }}"></div></div></div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="border-y border-white/8 bg-white/[0.025] py-24 sm:py-32">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-lime-300">Everything in its place</p>
                        <h2 class="mt-4 text-balance text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Built for the full rhythm of church life.</h2>
                        <p class="mt-5 text-lg leading-8 text-white/55">From a first-time visitor to a final financial report, every team works from one dependable record.</p>
                    </div>
                    @php
                        $features = [
                            ['People & membership', 'Keep complete member, visitor, household, and transfer records so care never becomes guesswork.', 'M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-6 9v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2m2-8a4 4 0 0 1 6 3.5V20'],
                            ['Services & attendance', 'Plan services, record attendance quickly, and see engagement patterns across branches.', 'M2 12h5l2-7 5 14 3-7h5'],
                            ['Finance with integrity', 'Track offerings, income, expenses, pledges, and transfers through clear approval workflows.', 'M3 7h18v13H3V7Zm3 0V4h12v3m-1 5h4v4h-4a2 2 0 1 1 0-4Z'],
                            ['Departments & groups', 'Organize ministry teams, leaders, members, and branch departments without scattered spreadsheets.', 'm12 3 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 18l9 5 9-5'],
                            ['Events & broadcasts', 'Coordinate upcoming events and publish important communications from one place.', 'M5 4h14a2 2 0 0 1 2 2v15H3V6a2 2 0 0 1 2-2Zm2-2v4m10-4v4M3 9h18'],
                            ['Assets & accountability', 'Know what the church owns, who is responsible, and which actions were taken through audit logs.', 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3.5-10 2.2 2.2 4.8-5'],
                        ];
                    @endphp
                    <div class="mt-14 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as [$title, $description, $path])
                            <article class="group rounded-3xl border border-white/10 bg-ink-900/60 p-7 transition duration-300 hover:-translate-y-1 hover:border-lime-300/30 hover:bg-ink-900 motion-reduce:transform-none">
                                <div class="grid size-12 place-items-center rounded-2xl border border-lime-300/15 bg-lime-300/8 text-lime-300 transition group-hover:bg-lime-300 group-hover:text-ink-950"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $path }}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                <h3 class="mt-6 text-xl font-semibold tracking-tight">{{ $title }}</h3>
                                <p class="mt-3 leading-7 text-white/50">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="operations" class="mx-auto max-w-7xl px-6 py-24 lg:px-8 lg:py-32">
                <div class="grid gap-14 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
                    <div class="lg:sticky lg:top-28">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-lime-300">A trustworthy workflow</p>
                        <h2 class="mt-4 text-balance text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">From Sunday count to audited record.</h2>
                        <p class="mt-5 max-w-lg text-lg leading-8 text-white/55">ChurchX turns daily administration into a simple, accountable flow—giving leaders confidence without adding complexity for their teams.</p>
                    </div>
                    <ol class="space-y-4">
                        @foreach ([['01', 'Capture', 'Teams record people, attendance, offerings, expenses, and ministry activity where the work happens.'], ['02', 'Verify', 'Leaders review submissions, supporting details, and responsibility before records move forward.'], ['03', 'Post', 'Approved information becomes part of the church’s dependable operational history.'], ['04', 'Understand', 'Dashboards and reports reveal progress, needs, and the next wise decision.']] as [$number, $title, $description])
                            <li class="grid gap-5 rounded-3xl border border-white/9 bg-white/[0.03] p-6 sm:grid-cols-[auto_1fr] sm:p-8"><span class="grid size-11 place-items-center rounded-full border border-lime-300/20 bg-lime-300/8 text-xs font-bold text-lime-300">{{ $number }}</span><div><h3 class="text-xl font-semibold">{{ $title }}</h3><p class="mt-2 max-w-2xl leading-7 text-white/50">{{ $description }}</p></div></li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="security" class="border-y border-white/8 bg-[#0a1614] py-24 sm:py-32">
                <div class="mx-auto grid max-w-7xl gap-14 px-6 lg:grid-cols-2 lg:items-center lg:px-8">
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-ink-950 p-7 sm:p-9">
                        <div class="absolute right-0 top-0 size-56 translate-x-1/3 -translate-y-1/3 rounded-full bg-lime-300/10 blur-3xl"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between border-b border-white/8 pb-6"><div><p class="text-xs uppercase tracking-[0.18em] text-white/35">Access control</p><p class="mt-2 text-xl font-semibold">The right view for every role</p></div><div class="grid size-12 place-items-center rounded-2xl bg-lime-300 text-ink-950"><svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 10V7a6 6 0 0 1 12 0v3m-14 0h16v11H4V10Zm8 4v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div></div>
                            <div class="mt-6 space-y-3">
                                @foreach ([['SA', 'Super Admin', 'Complete oversight'], ['BA', 'Branch Admin', 'Branch operations'], ['FO', 'Finance Officer', 'Financial workflows'], ['DL', 'Department Leader', 'Ministry team access']] as [$initials, $role, $scope])
                                    <div class="flex items-center gap-4 rounded-2xl border border-white/8 bg-white/[0.035] p-4"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/8 text-xs font-bold text-white/65">{{ $initials }}</span><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $role }}</p><p class="mt-0.5 text-xs text-white/40">{{ $scope }}</p></div><svg class="size-5 text-lime-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-lime-300">Secure by design</p>
                        <h2 class="mt-4 text-balance text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Open to your team. Protected from everyone else.</h2>
                        <p class="mt-6 text-lg leading-8 text-white/55">Every person sees only the branches and responsibilities assigned to them. Sensitive workflows remain controlled, and important actions remain traceable.</p>
                        <div class="mt-9 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl border border-white/10 p-5"><p class="text-3xl font-semibold text-lime-300">One</p><p class="mt-2 text-sm leading-6 text-white/50">secure source of truth across every ministry and branch</p></div><div class="rounded-2xl border border-white/10 p-5"><p class="text-3xl font-semibold text-lime-300">Every</p><p class="mt-2 text-sm leading-6 text-white/50">meaningful action recorded for clarity and accountability</p></div></div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-24 lg:px-8 lg:py-32">
                <div class="relative overflow-hidden rounded-[2rem] border border-lime-300/20 bg-lime-300 px-6 py-16 text-center text-ink-950 shadow-[0_30px_100px_rgba(192,240,90,0.12)] sm:px-12">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_10%_10%,rgba(255,255,255,0.7),transparent_30%),linear-gradient(120deg,transparent_55%,rgba(7,17,15,0.08))]"></div>
                    <div class="relative mx-auto max-w-3xl"><p class="text-sm font-bold uppercase tracking-[0.2em] text-ink-950/55">Lead with clarity</p><h2 class="mt-4 text-balance text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Your church’s work deserves a system built with care.</h2><p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-ink-950/65">Sign in to manage your church, support your teams, and keep every branch moving together.</p><a href="{{ route('backpack.auth.login') }}" class="mt-8 inline-flex items-center justify-center gap-2 rounded-full bg-ink-950 px-7 py-3.5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-ink-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-950 motion-reduce:transform-none">Continue to admin login <span aria-hidden="true">→</span></a></div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/8">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-6 py-8 text-sm text-white/40 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="flex items-center gap-2.5 text-white"><span class="grid size-8 place-items-center rounded-lg bg-lime-300 text-ink-950"><svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18M5 8h14" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/></svg></span><span class="font-semibold">Church<span class="text-lime-300">X</span></span></div>
                <p>&copy; {{ now()->year }} ChurchX. Purpose-built for healthy church operations.</p>
                <a href="{{ route('backpack.auth.login') }}" class="font-medium text-white/60 transition hover:text-lime-300">Admin login</a>
            </div>
        </footer>
    </div>
</body>
</html>
