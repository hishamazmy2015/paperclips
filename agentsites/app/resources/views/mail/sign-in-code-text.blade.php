{{ __('platform.mail.code_intro') }}

{{ $code }}

{{ __('platform.mail.code_expires', ['minutes' => \App\Auth\OtpService::TTL_MINUTES]) }}

{{ __('platform.mail.code_link') }}: {{ $magicUrl }}

{{ __('platform.mail.ignore') }}
