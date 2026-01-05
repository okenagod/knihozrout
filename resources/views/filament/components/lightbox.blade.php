<div class="space-y-4 max-h-[80vh] overflow-y-auto">
	@foreach($record->photos as $photo)
	<div class="flex flex-col items-center">
		<img src="{{ asset('storage/' . $photo) }}"
		     class="w-full h-auto rounded-lg shadow-xl"
		     style="max-width: 1000px;">
		<p class="mt-2 text-sm text-gray-500 italic">Snímek č. {{ $loop->iteration }}</p>
	</div>
	@endforeach
</div>