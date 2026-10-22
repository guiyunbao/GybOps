@php
    $adminUiV3Enabled = (bool) config('geoflow.admin_ui_v3_enabled', false);
    $sectionIndex = [];
    foreach ($document['groups'] as $group) {
        foreach ($group['sections'] as $section) {
            $sectionIndex[$section['title']] = $section['id'];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document['title'] }} · {{ $adminSiteName }}</title>
    @if ($adminUiV3Enabled)
        @vite(['resources/css/app.css'])
    @else
        <script src="{{ asset('js/tailwindcss.play-cdn.js') }}"></script>
    @endif
    <script src="{{ asset('js/lucide.min.js') }}"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <a href="#getting-started-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">跳到内容</a>
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-700 text-white">
                    <i data-lucide="book-open" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm text-slate-500">{{ $adminSiteName }}</p>
                    <p class="truncate text-base font-semibold text-slate-950">{{ $document['title'] }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="sr-only" for="getting-started-locale">{{ __('admin.login.language_label') }}</label>
                <select id="getting-started-locale" aria-label="{{ __('admin.login.language_label') }}" onchange="window.location.href=this.value" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600">
                    @foreach (\App\Support\AdminWeb::supportedLocales() as $localeCode => $localeLabel)
                        <option value="{{ route('admin.locale.switch', ['locale' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>
                            {{ $localeLabel }}
                        </option>
                    @endforeach
                </select>
                @if (auth('admin')->check())
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">{{ __('admin.getting_started.back_to_admin') }}</a>
                @else
                    <a href="{{ route('admin.login') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">{{ __('admin.getting_started.back_to_login') }}</a>
                @endif
                <a href="{{ url('/') }}" class="text-sm text-slate-600 hover:text-slate-900">{{ __('admin.login.back_home') }}</a>
            </div>
        </div>
    </header>

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <nav aria-label="{{ $document['title'] }}" class="lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                @foreach ($document['groups'] as $group)
                    <div class="{{ $loop->first ? '' : 'mt-4' }}">
                        <a href="#group-{{ $group['id'] }}" class="text-xs font-semibold tracking-wide text-slate-500 hover:text-slate-800">{{ $group['title'] }}</a>
                        <ul class="mt-2 grid gap-1">
                            @foreach ($group['sections'] as $section)
                                <li>
                                    <a href="#{{ $section['id'] }}" class="block rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-950">{{ $section['title'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <main id="getting-started-content" class="min-w-0">
            @if ($showLanguageNote)
                <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $document['language_note'] }}</p>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ $document['title'] }}</h1>
                <p class="mt-3 max-w-3xl text-base leading-7 text-slate-600">{{ $document['lede'] }}</p>
                <div class="mt-6 rounded-2xl bg-slate-50 p-4">
                    <x-admin.getting-started.flow :steps="$document['pipeline']" marker="pipeline" />
                </div>
            </section>

            @foreach ($document['groups'] as $group)
                <section id="group-{{ $group['id'] }}" class="mt-10 scroll-mt-24">
                    <h2 class="text-xl font-semibold text-slate-950">{{ $group['title'] }}</h2>
                    <div class="mt-4 grid gap-5">
                        @foreach ($group['sections'] as $section)
                            <article id="{{ $section['id'] }}" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-slate-950">{{ $section['title'] }}</h3>
                                    @if ($section['protected'])
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">受保护权限</span>
                                    @endif
                                </div>
                                <div class="mt-4">
                                    <x-admin.getting-started.flow :steps="$section['flow']" :marker="$section['id']" />
                                </div>
                                @if (is_array($section['map']))
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($section['map'] as $column)
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                <p class="text-xs font-semibold text-slate-500">{{ $column['group'] }}</p>
                                                <ul class="mt-2 grid gap-1">
                                                    @foreach ($column['items'] as $item)
                                                        <li class="text-sm font-medium text-slate-800">{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <dl class="mt-5 grid gap-4">
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-950">作用</dt>
                                        <dd class="mt-1 text-sm leading-6 text-slate-700">{{ $section['purpose'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-950">从哪进</dt>
                                        <dd class="mt-1 text-sm leading-6 text-slate-700">{{ $section['place'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-950">使用流程</dt>
                                        <dd class="mt-2">
                                            <ol class="grid gap-2">
                                                @foreach ($section['steps'] as $step)
                                                    <li class="flex gap-3 text-sm leading-6 text-slate-700">
                                                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{{ $loop->iteration }}</span>
                                                        <span>{{ $step }}</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-950">做完应看到</dt>
                                        <dd class="mt-1 text-sm leading-6 text-slate-700">{{ $section['outcome'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-950">和谁有关</dt>
                                        <dd class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($section['related'] as $related)
                                                @if (isset($sectionIndex[$related]))
                                                    <a href="#{{ $sectionIndex[$related] }}" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-blue-800 hover:bg-blue-50">{{ $related }}</a>
                                                @else
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $related }}</span>
                                                @endif
                                            @endforeach
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
