@extends('layouts.frontend')

@section('title', 'Přidat novou knihu')

@section('content')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <h1 class="text-2xl font-bold mb-6">Přidat novou knihu</h1>

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Chyba!</strong>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('books.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div>
                    <label for="bin_number" class="block text-sm font-medium text-gray-700">Číslo přepravky</label>
                    <input type="number" name="bin_number" id="bin_number" value="{{ old('bin_number') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="classification" class="block text-sm font-medium text-gray-700">Klasifikace</label>
                    <select name="classification" id="classification" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="1">1 - Jako nová</option>
                        <option value="2">2 - Velmi dobrý</option>
                        <option value="3">3 - Opotřebená</option>
                        <option value="4">4 - Poškozená</option>
                        <option value="5">5 - Salátové vydání / Torzo</option>
                    </select>
                </div>

                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input id="is_antique" name="is_antique" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_antique" class="font-medium text-gray-700">Kniha nemá ISBN (vydáno před r. 1989)</label>
                    </div>
                </div>

                <div>
                    <label for="main_photo" class="block text-sm font-medium text-gray-700">Hlavní fotka (Tiráž/ISBN)</label>
                    <input type="file" name="main_photo" id="main_photo" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <div>
                    <label for="photos" class="block text-sm font-medium text-gray-700">Ostatní fotky</label>
                    <input type="file" name="photos[]" id="photos" multiple class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <div>
                    <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Uložit knihu
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
