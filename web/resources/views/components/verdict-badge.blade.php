@props(['value'])

{{-- شارة موحّدة: لون + أيقونة + نص. تقبل Classification أو RequestStatus أو RiskLevel أو UserStatus. --}}
<span {{ $attributes->class(['verdict-badge', $value->cssClass()]) }}>
    @if (method_exists($value, 'icon'))
        <i class="bi {{ $value->icon() }}" aria-hidden="true"></i>
    @endif
    {{ $value->label() }}
</span>
