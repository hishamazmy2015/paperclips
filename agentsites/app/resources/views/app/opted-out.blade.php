<x-layouts.app :title="__('platform.reminders.opted_out_title')">
    <section class="app-card">
        <h1 class="app-h1">{{ __('platform.reminders.opted_out_title') }}</h1>
        <p class="app-lead">{{ __('platform.reminders.opted_out') }}</p>
        <a href="{{ route('start') }}" class="btn-primary">{{ __('platform.home.resume') }}</a>
    </section>
</x-layouts.app>
