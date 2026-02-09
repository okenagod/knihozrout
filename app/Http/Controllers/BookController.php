<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    public function index()
    {
        $books = Auth::user()->books()->latest()->get();
        return view('books.index', compact('books'));
    }

    public function create()
    {
        return view('books.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'main_photo' => ['required', 'image'],
            'photos.*' => ['image'],
            'bin_number' => ['required', 'numeric'],
            'classification' => ['required', 'string'],
            'is_antique' => ['boolean'],
        ]);

        $book = new Book($request->except(['main_photo', 'photos']));
        $book->user_id = Auth::id();
        $book->status = 'new';

        if ($request->hasFile('main_photo')) {
            $book->main_photo = $request->file('main_photo')->store('book-scans', 'public');
        }

        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('book-scans', 'public');
            }
            $book->photos = $photoPaths;
        }

        $book->save();

        return redirect()->route('books.index')->with('success', 'Kniha byla úspěšně přidána.');
    }
}
