<nav x-data="{ open: false }" class="sticky top-0 z-[1100] border-b border-[rgba(0,255,136,0.14)] bg-[#061412]/72 backdrop-blur-xl">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8">
        <!-- Left: logo + links -->
        <div class="flex items-center gap-6">
            <!-- Logo -->
            <a href="{{ route('dashboard') }}" class="group flex items-center gap-2.5" aria-label="CicleVibes · Inicio">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d] shadow-[0_0_18px_rgba(0,255,136,0.35)] transition group-hover:shadow-[0_0_26px_rgba(0,255,136,0.5)]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                        <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                    </svg>
                </span>
                <span class="text-xl font-extrabold tracking-tight text-[#f1fff9]">
                    Cicle<span class="text-[#00ff88] drop-shadow-[0_0_10px_rgba(0,255,136,0.5)]">Vibes</span>
                </span>
            </a>

            <!-- Navigation Links -->
            <div class="hidden items-center gap-1.5 text-sm font-semibold sm:flex">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-nav-link>

                <x-nav-link :href="route('mapa')" :active="request()->routeIs('mapa')">
                    {{ __('Mapa') }}
                </x-nav-link>
            </div>
        </div>

        <!-- Settings Dropdown -->
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2.5 rounded-xl border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.6)] px-3 py-1.5 text-sm font-semibold text-[#f1fff9] shadow-sm transition hover:border-[#00ff88] hover:shadow-[0_0_18px_rgba(0,255,136,0.2)] focus:outline-none">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[linear-gradient(135deg,#00ff88,#00e5ff)] text-xs font-black text-[#02140d]" aria-hidden="true">
                                {{ strtoupper(substr(trim(Auth::user()->nombre ?? 'U'), 0, 1)) }}
                            </span>
                            <span class="hidden max-w-[12rem] truncate text-[#f1fff9] md:block">{{ Auth::user()->nombre_completo }}</span>
                            <svg class="h-4 w-4 text-[#a7b8b2]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-lg p-2 text-[#a7b8b2] transition hover:bg-[rgba(0,255,136,0.1)] hover:text-[#00ff88] focus:outline-none" aria-label="Abrir menú">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-[rgba(0,255,136,0.12)] sm:hidden">
        <div class="space-y-1 px-4 pt-2 pb-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('mapa')" :active="request()->routeIs('mapa')">
                {{ __('Mapa') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-[rgba(0,255,136,0.12)] pt-4 pb-1">
            <div class="px-4">
                <div class="text-base font-bold text-[#f1fff9]">{{ Auth::user()->nombre_completo }}</div>
                <div class="text-sm text-[#a7b8b2]">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>