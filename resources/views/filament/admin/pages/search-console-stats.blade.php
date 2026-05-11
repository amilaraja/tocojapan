<x-filament-panels::page>
    @if (! $isConfigured)
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-8">
            <div class="max-w-2xl mx-auto text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-cog-6-tooth class="w-8 h-8 text-blue-600" />
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Google Search Console Not Configured</h2>
                <p class="text-gray-600 mb-6">Follow these steps to connect this site to Google Search Console:</p>

                <div class="text-left bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="flex gap-3">
                        <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold flex-shrink-0">1</div>
                        <div>
                            <p class="font-medium text-gray-900">Create a Google Cloud project + service account</p>
                            <p class="text-sm text-gray-600">Enable the Search Console API and download the JSON key.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold flex-shrink-0">2</div>
                        <div>
                            <p class="font-medium text-gray-900">Add the service account email to Search Console</p>
                            <p class="text-sm text-gray-600">Settings → Users and permissions → Add user with "Full" permission.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold flex-shrink-0">3</div>
                        <div>
                            <p class="font-medium text-gray-900">Drop the credentials JSON + set .env</p>
                            <pre class="mt-2 bg-gray-800 text-gray-100 p-3 rounded text-xs overflow-x-auto">storage/app/google-credentials.json
GOOGLE_SEARCH_CONSOLE_SITE_URL=sc-domain:tocojapan.com</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        @php
            $hasAnyData = ($summary && ($summary['clicks'] > 0 || $summary['impressions'] > 0))
                || ! empty($topQueries) || ! empty($topPages);
        @endphp

        <div class="space-y-6" x-data="gscDashboard()" x-init="init()">
            {{-- Header --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="text-sm text-gray-500">Property: <span class="font-medium text-gray-700">{{ $siteUrl }}</span></p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex bg-gray-100 rounded-lg p-0.5">
                        <button @click="activeTab = 'performance'" :class="activeTab === 'performance' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">Performance</button>
                        <button @click="activeTab = 'content'" :class="activeTab === 'content' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">Your Content</button>
                        <button @click="activeTab = 'insights'" :class="activeTab === 'insights' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">Insights</button>
                        <button @click="activeTab = 'indexing'" :class="activeTab === 'indexing' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">Indexing</button>
                    </div>
                    <select x-model="activeTab" class="sm:hidden rounded-lg border-gray-300 text-sm">
                        <option value="performance">Performance</option>
                        <option value="content">Your Content</option>
                        <option value="insights">Insights</option>
                        <option value="indexing">Indexing</option>
                    </select>
                    <select wire:model.live="dateRange" class="rounded-lg border-gray-300 text-sm">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="28">28 days</option>
                        <option value="90">3 months</option>
                    </select>
                    <x-filament::button wire:click="refreshData" color="gray" icon="heroicon-m-arrow-path" size="sm">
                        Refresh
                    </x-filament::button>
                </div>
            </div>

            @if ($error)
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                        <span>Error: {{ $error }}</span>
                    </div>
                </div>
            @endif

            {{-- ============================================ --}}
            {{-- PERFORMANCE TAB --}}
            {{-- ============================================ --}}
            <div x-show="activeTab === 'performance'" x-cloak>
                @if (! $hasAnyData)
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-12 text-center">
                        <x-heroicon-o-chart-bar class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 class="text-base font-semibold text-gray-900 mb-1">No search data for this period</h3>
                        <p class="text-sm text-gray-500">Try a longer date range, or wait — Google data has a 2–3 day delay.</p>
                    </div>
                @else
                    @if ($summary)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Clicks</span>
                                </div>
                                <div class="text-3xl font-bold text-blue-600">{{ number_format($summary['clicks']) }}</div>
                            </div>
                            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Impressions</span>
                                </div>
                                <div class="text-3xl font-bold text-purple-600">{{ number_format($summary['impressions']) }}</div>
                            </div>
                            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Avg CTR</span>
                                </div>
                                <div class="text-3xl font-bold text-emerald-600">{{ $summary['ctr'] }}%</div>
                            </div>
                            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Position</span>
                                </div>
                                <div class="text-3xl font-bold text-orange-600">{{ $summary['position'] }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 mb-6">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">Performance Over Time</h3>
                        @if ($dailyData && count($dailyData) > 0)
                            <div class="h-72">
                                <canvas x-ref="performanceChart"></canvas>
                            </div>
                        @else
                            <div class="h-32 flex items-center justify-center text-sm text-gray-400">No daily breakdown available yet.</div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Top Search Queries</h3>
                            @if ($topQueries && count($topQueries) > 0)
                                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-white">
                                            <tr class="border-b text-left text-gray-500">
                                                <th class="py-2 font-medium">Query</th>
                                                <th class="py-2 font-medium text-right">Clicks</th>
                                                <th class="py-2 font-medium text-right">Impr.</th>
                                                <th class="py-2 font-medium text-right">CTR</th>
                                                <th class="py-2 font-medium text-right">Pos.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (array_slice($topQueries, 0, 25) as $query)
                                                <tr class="border-b border-gray-50 hover:bg-gray-50">
                                                    <td class="py-2 font-medium text-gray-900 max-w-[200px] truncate" title="{{ $query['key'] }}">{{ Str::limit($query['key'], 35) }}</td>
                                                    <td class="py-2 text-right text-blue-600 font-semibold">{{ number_format($query['clicks']) }}</td>
                                                    <td class="py-2 text-right text-gray-500">{{ number_format($query['impressions']) }}</td>
                                                    <td class="py-2 text-right text-gray-500">{{ $query['ctr'] }}%</td>
                                                    <td class="py-2 text-right">
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $query['position'] <= 3 ? 'bg-green-100 text-green-700' : ($query['position'] <= 10 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                                            {{ $query['position'] }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-400 py-6 text-center">No queries returned by Search Console.</p>
                            @endif
                        </div>

                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Top Pages</h3>
                            @if ($topPages && count($topPages) > 0)
                                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-white">
                                            <tr class="border-b text-left text-gray-500">
                                                <th class="py-2 font-medium">Page</th>
                                                <th class="py-2 font-medium text-right">Clicks</th>
                                                <th class="py-2 font-medium text-right">CTR</th>
                                                <th class="py-2 font-medium text-right">Pos.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (array_slice($topPages, 0, 25) as $page)
                                                @php $path = parse_url($page['key'], PHP_URL_PATH) ?: '/'; @endphp
                                                <tr class="border-b border-gray-50 hover:bg-gray-50">
                                                    <td class="py-2 max-w-[250px]">
                                                        <a href="{{ $page['key'] }}" target="_blank" class="text-blue-600 hover:underline truncate block text-xs" title="{{ $path }}">{{ Str::limit($path, 40) }}</a>
                                                    </td>
                                                    <td class="py-2 text-right text-blue-600 font-semibold">{{ number_format($page['clicks']) }}</td>
                                                    <td class="py-2 text-right text-gray-500">{{ $page['ctr'] }}%</td>
                                                    <td class="py-2 text-right">
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $page['position'] <= 3 ? 'bg-green-100 text-green-700' : ($page['position'] <= 10 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                                            {{ $page['position'] }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-400 py-6 text-center">No pages returned by Search Console.</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">By Device</h3>
                            @if ($deviceData && count($deviceData) > 0)
                                <div class="h-48 mb-4">
                                    <canvas x-ref="deviceChart"></canvas>
                                </div>
                                <div class="space-y-2">
                                    @foreach ($deviceData as $device)
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-2">
                                                @if (strtolower($device['key']) === 'mobile')
                                                    <x-heroicon-o-device-phone-mobile class="w-4 h-4 text-blue-500" />
                                                @elseif (strtolower($device['key']) === 'desktop')
                                                    <x-heroicon-o-computer-desktop class="w-4 h-4 text-purple-500" />
                                                @else
                                                    <x-heroicon-o-device-tablet class="w-4 h-4 text-emerald-500" />
                                                @endif
                                                <span class="text-gray-700">{{ $this->formatDevice($device['key']) }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-semibold text-gray-900">{{ number_format($device['clicks']) }}</span>
                                                <span class="text-gray-400 text-xs ml-1">clicks</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-400 py-6 text-center">No device data.</p>
                            @endif
                        </div>

                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 md:col-span-2">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">By Country</h3>
                            @if ($countryData && count($countryData) > 0)
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($countryData as $country)
                                        @php
                                            $totalClicks = collect($countryData)->sum('clicks') ?: 1;
                                            $pct = round(($country['clicks'] / $totalClicks) * 100, 1);
                                        @endphp
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                            <span class="text-lg">{{ $this->getCountryFlag($country['key']) }}</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-medium text-sm uppercase text-gray-700">{{ $country['key'] }}</span>
                                                    <span class="font-semibold text-sm text-gray-900">{{ number_format($country['clicks']) }}</span>
                                                </div>
                                                <div class="mt-1 w-full bg-gray-200 rounded-full h-1.5">
                                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5">{{ number_format($country['impressions']) }} impr.</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-400 py-6 text-center">No country data.</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- ============================================ --}}
            {{-- YOUR CONTENT TAB --}}
            {{-- ============================================ --}}
            <div x-show="activeTab === 'content'" x-cloak>
                @if ($contentBreakdown && count($contentBreakdown) > 0)
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 lg:col-span-1">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Clicks by Section</h3>
                            <div class="h-56">
                                <canvas x-ref="contentClicksChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 lg:col-span-2">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Content Performance by Section</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-left text-gray-500">
                                            <th class="py-3 font-medium">Section</th>
                                            <th class="py-3 font-medium text-right">Pages</th>
                                            <th class="py-3 font-medium text-right">Clicks</th>
                                            <th class="py-3 font-medium text-right">Impressions</th>
                                            <th class="py-3 font-medium text-right">Avg CTR</th>
                                            <th class="py-3 font-medium text-center">Share</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $totalClicks = collect($contentBreakdown)->sum('clicks') ?: 1; @endphp
                                        @foreach ($contentBreakdown as $name => $section)
                                            @php
                                                $ctr = $section['impressions'] > 0 ? round(($section['clicks'] / $section['impressions']) * 100, 2) : 0;
                                                $share = round(($section['clicks'] / $totalClicks) * 100, 1);
                                            @endphp
                                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                                <td class="py-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $section['color'] }}"></div>
                                                        <span class="font-semibold text-gray-900">{{ $name }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-right text-gray-600">{{ $section['pages'] }}</td>
                                                <td class="py-3 text-right font-semibold text-blue-600">{{ number_format($section['clicks']) }}</td>
                                                <td class="py-3 text-right text-gray-500">{{ number_format($section['impressions']) }}</td>
                                                <td class="py-3 text-right text-gray-500">{{ $ctr }}%</td>
                                                <td class="py-3">
                                                    <div class="flex items-center gap-2 justify-center">
                                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                                            <div class="h-2 rounded-full" style="width: {{ min($share, 100) }}%; background-color: {{ $section['color'] }}"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-500 w-10 text-right">{{ $share }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Top Pages per Section --}}
                    @foreach ($contentBreakdown as $name => $section)
                        @if ($section['pages'] > 0)
                            @php
                                $prefix = $section['prefix'];
                                $sectionPages = array_filter($topPages ?? [], function ($p) use ($prefix, $name) {
                                    $path = parse_url($p['key'], PHP_URL_PATH) ?? '/';
                                    if ($name === 'Homepage') return $path === '/' || $path === '';
                                    if ($name === 'CMS pages') {
                                        return $path !== '/' && ! str_starts_with($path, '/vehicles') && ! str_starts_with($path, '/cif');
                                    }
                                    return $prefix && str_starts_with($path, $prefix);
                                });
                                $sectionPages = array_slice($sectionPages, 0, 10);
                            @endphp
                            @if (count($sectionPages) > 0)
                                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 mb-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $section['color'] }}"></div>
                                        <h3 class="text-base font-semibold text-gray-900">Top {{ $name }}</h3>
                                        <span class="text-xs text-gray-400">({{ $section['pages'] }} pages appearing in search)</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="border-b text-left text-gray-500">
                                                    <th class="py-2 font-medium">#</th>
                                                    <th class="py-2 font-medium">Page</th>
                                                    <th class="py-2 font-medium text-right">Clicks</th>
                                                    <th class="py-2 font-medium text-right">Impressions</th>
                                                    <th class="py-2 font-medium text-right">CTR</th>
                                                    <th class="py-2 font-medium text-right">Position</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($sectionPages as $i => $page)
                                                    @php $path = parse_url($page['key'], PHP_URL_PATH) ?: '/'; @endphp
                                                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                                                        <td class="py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                                        <td class="py-2">
                                                            <a href="{{ $page['key'] }}" target="_blank" class="text-blue-600 hover:underline text-xs" title="{{ $path }}">{{ Str::limit($path, 55) }}</a>
                                                        </td>
                                                        <td class="py-2 text-right font-semibold text-blue-600">{{ number_format($page['clicks']) }}</td>
                                                        <td class="py-2 text-right text-gray-500">{{ number_format($page['impressions']) }}</td>
                                                        <td class="py-2 text-right text-gray-500">{{ $page['ctr'] }}%</td>
                                                        <td class="py-2 text-right">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $page['position'] <= 3 ? 'bg-green-100 text-green-700' : ($page['position'] <= 10 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                                                {{ $page['position'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endforeach
                @else
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-12 text-center">
                        <x-heroicon-o-document-magnifying-glass class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 class="text-base font-semibold text-gray-900 mb-1">No content performance data yet</h3>
                        <p class="text-sm text-gray-500">Once your pages start picking up impressions, you'll see a breakdown by section here.</p>
                    </div>
                @endif
            </div>

            {{-- ============================================ --}}
            {{-- INSIGHTS TAB --}}
            {{-- ============================================ --}}
            <div x-show="activeTab === 'insights'" x-cloak>
                @if (empty($topQueries))
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-12 text-center">
                        <x-heroicon-o-light-bulb class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 class="text-base font-semibold text-gray-900 mb-1">No query data yet</h3>
                        <p class="text-sm text-gray-500">Insights need queries to analyse. Extend the date range or come back once impressions accumulate.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        @if ($positionDistribution)
                            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6" x-data="{ expandedBracket: null }">
                                <h3 class="text-base font-semibold text-gray-900 mb-2">Ranking Distribution</h3>
                                <p class="text-xs text-gray-500 mb-4">Click a bracket to see queries in that range</p>
                                <div class="h-48 mb-4">
                                    <canvas x-ref="positionChart"></canvas>
                                </div>
                                @php
                                    $posColors = ['1-3' => 'bg-green-500', '4-10' => 'bg-emerald-400', '11-20' => 'bg-yellow-400', '21-50' => 'bg-orange-400', '50+' => 'bg-red-400'];
                                    $posBorderColors = ['1-3' => 'border-green-500', '4-10' => 'border-emerald-400', '11-20' => 'border-yellow-400', '21-50' => 'border-orange-400', '50+' => 'border-red-400'];
                                    $posLabels = ['1-3' => 'Page 1 Top', '4-10' => 'Page 1', '11-20' => 'Page 2', '21-50' => 'Page 3-5', '50+' => 'Beyond'];
                                    $posBounds = ['1-3' => [0, 3], '4-10' => [3, 10], '11-20' => [10, 20], '21-50' => [20, 50], '50+' => [50, 999]];
                                @endphp
                                <div class="grid grid-cols-5 gap-2 text-center mb-4">
                                    @foreach ($positionDistribution as $range => $count)
                                        <button
                                            @click="expandedBracket = expandedBracket === '{{ $range }}' ? null : '{{ $range }}'"
                                            class="rounded-lg p-2 transition-all cursor-pointer hover:shadow-md"
                                            :class="expandedBracket === '{{ $range }}' ? 'ring-2 {{ $posBorderColors[$range] ?? 'border-gray-400' }} bg-gray-50 shadow' : 'hover:bg-gray-50'"
                                        >
                                            <div class="w-3 h-3 rounded-full {{ $posColors[$range] ?? 'bg-gray-400' }} mx-auto mb-1"></div>
                                            <div class="text-lg font-bold text-gray-900">{{ $count }}</div>
                                            <div class="text-xs text-gray-500">{{ $posLabels[$range] ?? $range }}</div>
                                        </button>
                                    @endforeach
                                </div>

                                @foreach ($positionDistribution as $range => $count)
                                    @php
                                        [$minPos, $maxPos] = $posBounds[$range];
                                        $bracketQueries = collect($topQueries ?? [])->filter(fn ($q) => $q['position'] > $minPos && $q['position'] <= $maxPos)->sortBy('position')->values();
                                        if ($range === '1-3') {
                                            $bracketQueries = collect($topQueries ?? [])->filter(fn ($q) => $q['position'] <= 3)->sortBy('position')->values();
                                        }
                                    @endphp
                                    <div x-show="expandedBracket === '{{ $range }}'" x-collapse x-cloak class="mt-2">
                                        <div class="border-t pt-3">
                                            <div class="flex items-center gap-2 mb-3">
                                                <div class="w-2.5 h-2.5 rounded-full {{ $posColors[$range] ?? 'bg-gray-400' }}"></div>
                                                <span class="text-sm font-semibold text-gray-700">{{ $posLabels[$range] ?? $range }} — {{ $bracketQueries->count() }} queries</span>
                                            </div>
                                            @if ($bracketQueries->isNotEmpty())
                                                <div class="max-h-64 overflow-y-auto space-y-1">
                                                    @foreach ($bracketQueries as $q)
                                                        <div class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-gray-50 text-sm">
                                                            <span class="text-gray-700 truncate max-w-[55%]" title="{{ $q['key'] }}">{{ Str::limit($q['key'], 35) }}</span>
                                                            <div class="flex items-center gap-3 text-xs">
                                                                <span class="text-blue-600 font-semibold">{{ $q['clicks'] }} clicks</span>
                                                                <span class="text-gray-400">{{ number_format($q['impressions']) }} impr</span>
                                                                <span class="text-gray-400">{{ $q['ctr'] }}%</span>
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded font-medium {{ $q['position'] <= 3 ? 'bg-green-100 text-green-700' : ($q['position'] <= 10 ? 'bg-yellow-100 text-yellow-700' : ($q['position'] <= 20 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600')) }}">
                                                                    {{ $q['position'] }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs text-gray-400 italic">No queries in this range</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">CTR vs Position</h3>
                            <p class="text-xs text-gray-500 mb-4">Click-through rate relative to ranking position</p>
                            <div class="h-64">
                                <canvas x-ref="ctrPositionChart"></canvas>
                            </div>
                        </div>
                    </div>

                    @if ($quickWins && count($quickWins) > 0)
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 mb-6">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                    <x-heroicon-o-light-bulb class="w-5 h-5 text-amber-600" />
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">Quick Win Opportunities</h3>
                                    <p class="text-xs text-gray-500">Queries with high impressions but ranking 4-20 — optimise these for more clicks</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto mt-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-left text-gray-500">
                                            <th class="py-2 font-medium">Query</th>
                                            <th class="py-2 font-medium text-right">Impressions</th>
                                            <th class="py-2 font-medium text-right">Clicks</th>
                                            <th class="py-2 font-medium text-right">CTR</th>
                                            <th class="py-2 font-medium text-right">Position</th>
                                            <th class="py-2 font-medium text-right">Potential</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($quickWins as $qw)
                                            @php
                                                $potentialClicks = round($qw['impressions'] * 0.10) - $qw['clicks'];
                                                if ($potentialClicks < 0) $potentialClicks = 0;
                                            @endphp
                                            <tr class="border-b border-gray-50 hover:bg-amber-50">
                                                <td class="py-2 font-medium text-gray-900 max-w-[250px] truncate" title="{{ $qw['key'] }}">{{ Str::limit($qw['key'], 40) }}</td>
                                                <td class="py-2 text-right text-purple-600 font-semibold">{{ number_format($qw['impressions']) }}</td>
                                                <td class="py-2 text-right text-blue-600">{{ number_format($qw['clicks']) }}</td>
                                                <td class="py-2 text-right text-gray-500">{{ $qw['ctr'] }}%</td>
                                                <td class="py-2 text-right">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">{{ $qw['position'] }}</span>
                                                </td>
                                                <td class="py-2 text-right">
                                                    @if ($potentialClicks > 0)
                                                        <span class="text-green-600 font-semibold">+{{ $potentialClicks }}</span>
                                                        <span class="text-xs text-gray-400">clicks</span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-6 h-6 bg-green-100 rounded flex items-center justify-center">
                                    <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-green-600" />
                                </div>
                                <h3 class="text-base font-semibold text-gray-900">Best CTR Queries</h3>
                            </div>
                            @php
                                $bestCtr = collect($topQueries)->filter(fn ($q) => $q['impressions'] >= 5)->sortByDesc('ctr')->take(10)->values();
                            @endphp
                            @if ($bestCtr->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach ($bestCtr as $q)
                                        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-green-50">
                                            <span class="text-sm text-gray-700 truncate max-w-[200px]" title="{{ $q['key'] }}">{{ Str::limit($q['key'], 30) }}</span>
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs text-gray-400">pos {{ $q['position'] }}</span>
                                                <span class="font-bold text-green-600 text-sm">{{ $q['ctr'] }}%</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-400 py-4 text-center">No queries with enough impressions yet.</p>
                            @endif
                        </div>

                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-6 h-6 bg-purple-100 rounded flex items-center justify-center">
                                    <x-heroicon-o-eye class="w-4 h-4 text-purple-600" />
                                </div>
                                <h3 class="text-base font-semibold text-gray-900">Most Visible Queries</h3>
                            </div>
                            @php
                                $mostVisible = collect($topQueries)->sortByDesc('impressions')->take(10)->values();
                            @endphp
                            <div class="space-y-2">
                                @foreach ($mostVisible as $q)
                                    <div class="flex items-center justify-between p-2 rounded-lg hover:bg-purple-50">
                                        <span class="text-sm text-gray-700 truncate max-w-[200px]" title="{{ $q['key'] }}">{{ Str::limit($q['key'], 30) }}</span>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400">pos {{ $q['position'] }}</span>
                                            <span class="font-bold text-purple-600 text-sm">{{ number_format($q['impressions']) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ============================================ --}}
            {{-- INDEXING TAB --}}
            {{-- ============================================ --}}
            <div x-show="activeTab === 'indexing'" x-cloak>
                @if ($indexCoverage)
                    @php
                        $totalVehicles = \App\Models\Vehicle::query()->where('status', 'published')->count();
                        $totalMakes = \App\Models\Make::where('is_active', true)->count();
                        $totalBodyTypes = \App\Models\BodyType::where('is_active', true)->count();
                        $totalCmsPages = \App\Models\Page::where('status', 'published')->count();
                        $totalPages = $totalVehicles + $totalMakes + $totalBodyTypes + $totalCmsPages + 3;
                    @endphp

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Index Status</h3>
                            <div class="text-center py-4">
                                <div class="text-5xl font-bold text-emerald-600 mb-1">{{ number_format($indexCoverage['total_indexed']) }}</div>
                                <div class="text-sm text-gray-500">Total Indexed Pages</div>
                            </div>

                            <div class="mt-4 pt-4 border-t">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-500">Your content</span>
                                    <span class="font-medium text-gray-900">{{ number_format($totalPages) }} pages</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    @php $indexPct = $totalPages > 0 ? min(round(($indexCoverage['total_indexed'] / $totalPages) * 100), 100) : 0; @endphp
                                    <div class="bg-emerald-500 h-3 rounded-full transition-all" style="width: {{ $indexPct }}%"></div>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">{{ $indexPct }}% indexed</div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 lg:col-span-2">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Content Inventory</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div class="bg-blue-50 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ number_format($totalVehicles) }}</div>
                                    <div class="text-xs text-blue-500 font-medium mt-1">Published vehicles</div>
                                </div>
                                <div class="bg-purple-50 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-purple-600">{{ number_format($totalMakes) }}</div>
                                    <div class="text-xs text-purple-500 font-medium mt-1">Active makes</div>
                                </div>
                                <div class="bg-emerald-50 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-emerald-600">{{ number_format($totalBodyTypes) }}</div>
                                    <div class="text-xs text-emerald-500 font-medium mt-1">Body types</div>
                                </div>
                                <div class="bg-amber-50 rounded-xl p-4 text-center">
                                    <div class="text-2xl font-bold text-amber-600">{{ number_format($totalCmsPages) }}</div>
                                    <div class="text-xs text-amber-500 font-medium mt-1">CMS pages</div>
                                </div>
                            </div>

                            @if ($topPages)
                                @php $pagesInSearch = count($topPages); @endphp
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-2 text-sm">
                                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400" />
                                        <span class="text-gray-600"><strong class="text-gray-900">{{ $pagesInSearch }}</strong> pages appearing in Google search results</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if (! empty($indexCoverage['sitemaps']))
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Sitemaps</h3>
                            <div class="space-y-3">
                                @foreach ($indexCoverage['sitemaps'] as $sitemap)
                                    @php
                                        $submitted = (int) ($sitemap['submitted'] ?? 0);
                                        $indexed = (int) ($sitemap['indexed'] ?? 0);
                                        $sitemapPct = $submitted > 0 ? round(($indexed / $submitted) * 100) : 0;
                                    @endphp
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                                                <a href="{{ $sitemap['path'] }}" target="_blank" class="text-sm text-blue-600 hover:underline font-medium">{{ basename($sitemap['path']) }}</a>
                                            </div>
                                            <span class="text-xs text-gray-400">
                                                @if ($sitemap['last_downloaded'])
                                                    Last crawled: {{ \Carbon\Carbon::parse($sitemap['last_downloaded'])->diffForHumans() }}
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="flex-1">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $sitemapPct }}%"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 text-sm">
                                                <span class="text-gray-500">{{ number_format($submitted) }} submitted</span>
                                                <span class="text-emerald-600 font-semibold">{{ number_format($indexed) }} indexed</span>
                                                <span class="text-xs text-gray-400">({{ $sitemapPct }}%)</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 text-center">
                            <p class="text-sm text-gray-500">No sitemaps submitted to Search Console yet.</p>
                            <p class="text-xs text-gray-400 mt-1">Submit your sitemap.xml in Search Console → Sitemaps to track indexing.</p>
                        </div>
                    @endif
                @else
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-12 text-center">
                        <x-heroicon-o-document-magnifying-glass class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 class="text-base font-semibold text-gray-900 mb-1">No indexing data available</h3>
                        <p class="text-sm text-gray-500">Google hasn't returned sitemap data for this property yet.</p>
                    </div>
                @endif
            </div>

            <div class="p-3 bg-blue-50 rounded-lg text-xs text-blue-600">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-information-circle class="w-4 h-4 flex-shrink-0" />
                    <span>Data cached for 1 hour. Click Refresh for latest. Google data has a 2-3 day delay.</span>
                </div>
            </div>
        </div>

        {{-- Chart.js loaded synchronously, then dashboard payload + renderer --}}
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
            <script>
                window.gscData = {
                    daily: @json($dailyData ?? []),
                    device: @json($deviceData ?? []),
                    content: @json($contentBreakdown ?? (object) []),
                    position: @json($positionDistribution ?? (object) []),
                    queries: @json($topQueries ?? []),
                };

                function gscDashboard() {
                    return {
                        activeTab: 'performance',
                        charts: {},
                        init() {
                            this.$nextTick(() => this.renderCharts());
                            this.$watch('activeTab', () => {
                                this.$nextTick(() => this.renderCharts());
                            });
                            document.addEventListener('livewire:updated', () => {
                                this.$nextTick(() => this.renderCharts());
                            });
                        },
                        renderCharts() {
                            if (typeof Chart === 'undefined') return;
                            Object.values(this.charts).forEach(c => { try { c.destroy(); } catch (e) {} });
                            this.charts = {};

                            if (this.activeTab === 'performance') {
                                this.renderPerformanceChart();
                                this.renderDeviceChart();
                            } else if (this.activeTab === 'content') {
                                this.renderContentClicksChart();
                            } else if (this.activeTab === 'insights') {
                                this.renderPositionChart();
                                this.renderCtrPositionChart();
                            }
                        },
                        renderPerformanceChart() {
                            const el = this.$refs.performanceChart;
                            const data = window.gscData.daily;
                            if (!el || !data || !data.length) return;

                            this.charts.performance = new Chart(el.getContext('2d'), {
                                type: 'line',
                                data: {
                                    labels: data.map(d => {
                                        const date = new Date(d.date);
                                        return date.toLocaleDateString('en', { month: 'short', day: 'numeric' });
                                    }),
                                    datasets: [{
                                        label: 'Clicks',
                                        data: data.map(d => d.clicks),
                                        borderColor: '#3B82F6',
                                        backgroundColor: 'rgba(59, 130, 246, 0.08)',
                                        fill: true,
                                        tension: 0.4,
                                        borderWidth: 2,
                                        pointRadius: 3,
                                        pointBackgroundColor: '#3B82F6',
                                        yAxisID: 'y'
                                    }, {
                                        label: 'Impressions',
                                        data: data.map(d => d.impressions),
                                        borderColor: '#8B5CF6',
                                        backgroundColor: 'transparent',
                                        borderDash: [5, 5],
                                        tension: 0.4,
                                        borderWidth: 2,
                                        pointRadius: 2,
                                        yAxisID: 'y1'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } } },
                                    scales: {
                                        y: { position: 'left', title: { display: true, text: 'Clicks', color: '#3B82F6' }, grid: { color: 'rgba(0,0,0,0.04)' } },
                                        y1: { position: 'right', title: { display: true, text: 'Impressions', color: '#8B5CF6' }, grid: { drawOnChartArea: false } },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        },
                        renderDeviceChart() {
                            const el = this.$refs.deviceChart;
                            const data = window.gscData.device;
                            if (!el || !data || !data.length) return;

                            const colors = { 'MOBILE': '#3B82F6', 'DESKTOP': '#8B5CF6', 'TABLET': '#10B981' };
                            this.charts.device = new Chart(el.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: data.map(d => d.key.charAt(0) + d.key.slice(1).toLowerCase()),
                                    datasets: [{
                                        data: data.map(d => d.clicks),
                                        backgroundColor: data.map(d => colors[d.key] || '#6B7280'),
                                        borderWidth: 0,
                                        spacing: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '65%',
                                    plugins: { legend: { display: false } }
                                }
                            });
                        },
                        renderContentClicksChart() {
                            const el = this.$refs.contentClicksChart;
                            const data = window.gscData.content;
                            const entries = Object.entries(data || {}).filter(([_, v]) => v.clicks > 0);
                            if (!el || !entries.length) return;

                            this.charts.contentClicks = new Chart(el.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: entries.map(([k]) => k),
                                    datasets: [{
                                        data: entries.map(([_, v]) => v.clicks),
                                        backgroundColor: entries.map(([_, v]) => v.color),
                                        borderWidth: 0,
                                        spacing: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '60%',
                                    plugins: {
                                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                                    }
                                }
                            });
                        },
                        renderPositionChart() {
                            const el = this.$refs.positionChart;
                            const data = window.gscData.position;
                            if (!el || !data) return;
                            const labels = Object.keys(data);
                            const values = Object.values(data);
                            if (!labels.length) return;
                            const colors = ['#22C55E', '#34D399', '#FBBF24', '#FB923C', '#EF4444'];

                            this.charts.position = new Chart(el.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: values,
                                        backgroundColor: colors,
                                        borderRadius: 6,
                                        borderSkipped: false,
                                        barThickness: 36
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { stepSize: 1 } },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        },
                        renderCtrPositionChart() {
                            const el = this.$refs.ctrPositionChart;
                            const all = window.gscData.queries || [];
                            const queries = all.filter(q => q.impressions >= 5).slice(0, 50);
                            if (!el || !queries.length) return;

                            this.charts.ctrPosition = new Chart(el.getContext('2d'), {
                                type: 'scatter',
                                data: {
                                    datasets: [{
                                        label: 'Queries',
                                        data: queries.map(q => ({ x: q.position, y: q.ctr, label: q.key })),
                                        backgroundColor: queries.map(q => {
                                            if (q.position <= 3) return 'rgba(34, 197, 94, 0.7)';
                                            if (q.position <= 10) return 'rgba(59, 130, 246, 0.7)';
                                            return 'rgba(156, 163, 175, 0.5)';
                                        }),
                                        pointRadius: queries.map(q => Math.max(3, Math.min(12, Math.sqrt(q.impressions) / 2))),
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: (ctx) => {
                                                    const q = queries[ctx.dataIndex];
                                                    return `${q.key} — Pos: ${q.position}, CTR: ${q.ctr}%, ${q.impressions} impr.`;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: 'Position' }, reverse: false, grid: { color: 'rgba(0,0,0,0.04)' } },
                                        y: { title: { display: true, text: 'CTR %' }, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }
                                    }
                                }
                            });
                        }
                    };
                }
            </script>
        @endpush
    @endif
</x-filament-panels::page>
