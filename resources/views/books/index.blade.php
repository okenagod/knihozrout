@extends('layouts.frontend')

@section('title', 'Moje knihy')

@section('content')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <h1 class="text-2xl font-bold mb-6">Moje knihy</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($books as $book)
                    <div class="border rounded-lg p-4 shadow-sm">
                        @if($book->main_photo)
                            <img src="{{ asset('storage/' . $book->main_photo) }}" alt="Titulní fotka" class="w-full h-48 object-cover rounded-md mb-4">
                        @endif
                        <h2 class="text-xl font-bold">{{ $book->title ?? 'Bez názvu' }}</h2>
                        <p class="text-gray-600">{{ $book->author ?? 'Neznámý autor' }}</p>
                        <div class="mt-2">
                            <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">
                                Stav: {{ $book->status }}
                            </span>
                            <span class="inline-block bg-blue-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">
                                Přepravka: {{ $book->bin_number }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full">Zatím jste nepřidali žádné knihy.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
