<header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <a href="/" class="font-bold text-xl">{{ config('app.name', 'Knihozrout') }}</a>
                </div>
            </div>
            <div class="flex items-center">
                @auth
                    <a href="{{ route('books.index') }}" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">Moje knihy</a>
                    <a href="{{ route('books.create') }}" class="ml-4 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">Přidat knihu</a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-4">
                        @csrf
                        <button type="submit" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">Odhlásit se</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">Přihlásit se</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="ml-4 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">Registrovat</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</header>
