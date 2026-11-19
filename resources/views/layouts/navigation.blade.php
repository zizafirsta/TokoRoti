<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Left Side -->
            <div class="flex">

                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('shop.index') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">

                    <!-- Dashboard -->
                    @auth
                        <x-nav-link
                            :href="route('dashboard')"
                            :active="request()->routeIs('dashboard')"
                            wire:navigate
                        >
                            {{ __('Dashboard') }}
                        </x-nav-link>
                    @endauth

                    <!-- Katalog Roti -->
                    <x-nav-link
                        :href="route('shop.index')"
                        :active="request()->routeIs('shop.*')"
                        wire:navigate
                    >
                        {{ __('Katalog Roti') }}
                    </x-nav-link>

                    @auth
                        <!-- Keranjang -->
                        <x-nav-link
                            :href="route('cart.index')"
                            :active="request()->routeIs('cart.*')"
                            wire:navigate
                        >
                            {{ __('Keranjang') }}
                            <span x-data
                                  x-show="$store.cart.count > 0"
                                  x-cloak
                                  x-text="$store.cart.count"
                                  :class="{ 'scale-125': $store.cart.bump }"
                                  class="ms-1.5 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-600 px-1.5 text-xs font-bold text-white transition-transform">{{ (int) collect(session('cart', []))->sum('quantity') }}</span>
                        </x-nav-link>

                        <!-- Pesanan Saya -->
                        <x-nav-link
                            :href="route('orders.index')"
                            :active="request()->routeIs('orders.*')"
                            wire:navigate
                        >
                            {{ __('Pesanan Saya') }}
                        </x-nav-link>

                        <!-- Menu Khusus Admin -->
                        @if(auth()->user()->role === 'admin')
                            <!-- Kelola Produk -->
                            <x-nav-link
                                :href="route('admin.products.index')"
                                :active="request()->routeIs('admin.products.*')"
                                wire:navigate
                            >
                                {{ __('Kelola Produk') }}
                            </x-nav-link>

                            <!-- Kelola Transaksi -->
                            <x-nav-link
                                :href="route('admin.orders.index')"
                                :active="request()->routeIs('admin.orders.*')"
                                wire:navigate
                            >
                                {{ __('Kelola Transaksi') }}
                            </x-nav-link>
                        @endif
                    @endauth

                </div>
            </div>

            <!-- Right Side -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">

                @auth
                    <!-- Settings Dropdown -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                            >
                                <div>
                                    {{ Auth::user()->name }}
                                </div>

                                <div class="ms-1">
                                    <svg
                                        class="fill-current h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <!-- Profile -->
                            <x-dropdown-link :href="route('profile.edit')" wire:navigate>
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <!-- Logout -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link
                                    :href="route('logout')"
                                    onclick="event.preventDefault();
                                        this.closest('form').submit();"
                                >
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <!-- Guest Links -->
                    <div class="flex items-center space-x-4">
                        <a
                            href="{{ route('login') }}"
                            class="text-sm text-gray-700 hover:text-gray-900"
                            wire:navigate
                        >
                            {{ __('Login') }}
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="text-sm text-gray-700 hover:text-gray-900"
                                wire:navigate
                            >
                                {{ __('Register') }}
                            </a>
                        @endif
                    </div>
                @endauth

            </div>

            <!-- Hamburger Button -->
            <div class="-me-2 flex items-center sm:hidden">
                <button
                    @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                >
                    <svg
                        class="h-6 w-6"
                        stroke="currentColor"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            :class="{
                                'hidden': open,
                                'inline-flex': ! open
                            }"
                            class="inline-flex"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                        <path
                            :class="{
                                'hidden': ! open,
                                'inline-flex': open
                            }"
                            class="hidden"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div
        :class="{
            'block': open,
            'hidden': ! open
        }"
        class="hidden sm:hidden"
    >
        <div class="pt-2 pb-3 space-y-1">

            <!-- Dashboard -->
            @auth
                <x-responsive-nav-link
                    :href="route('dashboard')"
                    :active="request()->routeIs('dashboard')"
                    wire:navigate
                >
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            @endauth

            <!-- Katalog Roti -->
            <x-responsive-nav-link
                :href="route('shop.index')"
                :active="request()->routeIs('shop.*')"
                wire:navigate
            >
                {{ __('Katalog Roti') }}
            </x-responsive-nav-link>

            @auth
                <!-- Keranjang -->
                <x-responsive-nav-link
                    :href="route('cart.index')"
                    :active="request()->routeIs('cart.*')"
                    wire:navigate
                >
                    {{ __('Keranjang') }}
                            <span x-data
                                  x-show="$store.cart.count > 0"
                                  x-cloak
                                  x-text="$store.cart.count"
                                  :class="{ 'scale-125': $store.cart.bump }"
                                  class="ms-1.5 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-600 px-1.5 text-xs font-bold text-white transition-transform">{{ (int) collect(session('cart', []))->sum('quantity') }}</span>
                </x-responsive-nav-link>

                <!-- Pesanan Saya -->
                <x-responsive-nav-link
                    :href="route('orders.index')"
                    :active="request()->routeIs('orders.*')"
                    wire:navigate
                >
                    {{ __('Pesanan Saya') }}
                </x-responsive-nav-link>

                <!-- Menu Admin -->
                @if(auth()->user()->role === 'admin')
                    <!-- Kelola Produk -->
                    <x-responsive-nav-link
                        :href="route('admin.products.index')"
                        :active="request()->routeIs('admin.products.*')"
                        wire:navigate
                    >
                        {{ __('Kelola Produk') }}
                    </x-responsive-nav-link>

                    <!-- Kelola Transaksi -->
                    <x-responsive-nav-link
                        :href="route('admin.orders.index')"
                        :active="request()->routeIs('admin.orders.*')"
                        wire:navigate
                    >
                        {{ __('Kelola Transaksi') }}
                    </x-responsive-nav-link>
                @endif
            @endauth

        </div>

        <!-- Responsive Settings -->
        @auth
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">
                        {{ Auth::user()->name }}
                    </div>
                    <div class="font-medium text-sm text-gray-500">
                        {{ Auth::user()->email }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        {{ ucfirst(Auth::user()->role) }}
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <!-- Profile -->
                    <x-responsive-nav-link
                        :href="route('profile.edit')"
                        wire:navigate
                    >
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Logout -->
                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf
                        <x-responsive-nav-link
                            :href="route('logout')"
                            onclick="event.preventDefault();
                                this.closest('form').submit();"
                        >
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            <!-- Guest Mobile Menu -->
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="space-y-1">
                    <x-responsive-nav-link
                        :href="route('login')"
                        wire:navigate
                    >
                        {{ __('Login') }}
                    </x-responsive-nav-link>

                    @if (Route::has('register'))
                        <x-responsive-nav-link
                            :href="route('register')"
                            wire:navigate
                        >
                            {{ __('Register') }}
                        </x-responsive-nav-link>
                    @endif
                </div>
            </div>
        @endauth

    </div>
</nav>
