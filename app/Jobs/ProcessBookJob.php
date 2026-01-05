<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\BookOcrService;
use App\Services\LibraryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBookJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public function __construct(
		public Book $book
	) {}

	public function handle(BookOcrService $ocrService, LibraryService $libraryService): void
	{
		// 1. Spustíme OCR nad hlavní fotkou
		$ocrService->processBook($this->book);

		// 2. Po OCR máme (snad) ISBN, zkusíme najít metadata v knihovnách
		// Musíme znovu načíst model, aby měl v sobě nové ISBN z OCR
		$this->book->refresh();
		$libraryService->processBook($this->book);
	}
}