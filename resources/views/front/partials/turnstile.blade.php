@php
	$turnstileAction = $action ?? 'form_submission';
	$turnstileTheme = $theme ?? 'auto';
	$turnstileSize = $size ?? 'flexible';
	$turnstileLanguage = $language ?? app()->getLocale();
@endphp

@if (config('services.turnstile.site_key'))
	<input type="hidden" name="requires_turnstile" value="1">
	<div class="cf-turnstile"
		 data-sitekey="{{ config('services.turnstile.site_key') }}"
		 data-theme="{{ $turnstileTheme }}"
		 data-size="{{ $turnstileSize }}"
		 data-action="{{ $turnstileAction }}"
		 data-language="{{ $turnstileLanguage }}"
		 style="margin-bottom:10px;"></div>
@endif