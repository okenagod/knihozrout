<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col items-center justify-center p-6 text-center">
            <h2 class="text-xl font-bold mb-4">Máte čas na kontrolu?</h2>
            <p class="text-gray-500 mb-6">Systém vybere nejstarší knihu, která čeká na schválení.</p>
            
            <x-filament::button 
                wire:click="startReview" 
                size="xl" 
                color="primary"
                icon="heroicon-o-play"
            >
                Začít kontrolovat
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
