@php
    $initials = strtoupper(substr($user->name, 0, 2));
    $colors = ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-orange-500'];
    $color = $colors[$user->id % count($colors)];
    
    $sizeClasses = match($size ?? 'md') {
        'xs' => 'w-6 h-6 text-xs',
        'sm' => 'w-8 h-8 text-xs',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-12 h-12 text-sm',
        'xl' => 'w-16 h-16 text-lg',
        default => 'w-10 h-10 text-sm'
    };
@endphp

<div class="{{ $sizeClasses }} {{ $color }} rounded-full flex items-center justify-center flex-shrink-0 shadow-sm relative">
    <span class="text-white font-medium">{{ $initials }}</span>
    
    @if(($showStatus ?? true) && $user->isOnline())
        <div class="absolute w-3 h-3 bg-green-400 border-2 border-white dark:border-gray-800 rounded-full -bottom-0.5 -right-0.5"></div>
    @endif
</div>
