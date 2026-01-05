<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('books', function (Blueprint $table)
		{
			// Přidáme boolean sloupec (checkbox), výchozí hodnota bude false (0)
			$table->boolean('is_antique')->default(false)->after('status');
		});
	}

	public function down(): void
	{
		Schema::table('books', function (Blueprint $table)
		{
			$table->dropColumn('is_antique');
		});
	}
};
