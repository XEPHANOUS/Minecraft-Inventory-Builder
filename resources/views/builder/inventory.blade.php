@extends('layouts.base')

@push('styles')
    @viteReactRefresh
    @vite(['resources/js/builder/inventory.js'])
@endpush

@section('title', 'Inventory Builder')

@section('app')

    <div class="p-4">
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-diamond"></i> The Inventory Builder is currently under development. The site is
            on <a href="https://github.com/Maxlego08/Minecraft-Inventory-Builder" target="_blank">Github <i
                    class="bi bi-github"></i></a>, if you want to participate to improve it do not hesitate !
        </div>
    </div>

    <div id="builder">
    </div>
@endsection

@push('footer-scripts')
    <script>
        window.Content = {!! json_encode([
    'inventory' => $inventory,
    'versions' => $versions,
  ]) !!};
    </script>
@endpush
