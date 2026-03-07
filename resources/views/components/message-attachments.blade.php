@props(['attachments', 'platform', 'isOutgoing' => false])

@foreach($attachments as $attachment)
    @php
        $fileUrl = $platform === 'telegram'
            ? url('/api/files/' . $attachment->file_id)
            : $attachment->file_id;

        $isImage = in_array($attachment->file_type, ['photo', 'sticker']);
        $isVoice = in_array($attachment->file_type, ['voice', 'audio_message']);
        $isVideo = $attachment->file_type === 'video_note';
    @endphp

    <div class="mt-1">
        @if($isImage)
            <img
                src="{{ $fileUrl }}"
                alt="{{ $attachment->file_type }}"
                class="w-32 h-32 rounded-lg object-cover cursor-zoom-in"
                loading="lazy"
                x-on:click="$dispatch('open-lightbox', { src: '{{ $fileUrl }}' })"
            >
        @elseif($isVoice)
            <audio controls class="max-w-full h-8">
                <source src="{{ $fileUrl }}">
            </audio>
        @elseif($isVideo)
            <video controls class="max-w-[240px] max-h-[240px] rounded-lg">
                <source src="{{ $fileUrl }}">
            </video>
        @else
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                class="inline-flex items-center gap-1 text-xs underline opacity-80 hover:opacity-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                {{ $attachment->file_name ?? $attachment->file_type }}
            </a>
        @endif
    </div>
@endforeach
