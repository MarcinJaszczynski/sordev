@extends('front.layout.master')

@section('main_content')
    <div class="page-top">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('documents.global') }}">Dokumenty</a></li>
                            <li class="breadcrumb-item active">{{ $document->title }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container pt_50">
        <h1>{{ $document->title }}</h1>
        @if($document->excerpt)
            <div class="doc-excerpt">{!! $document->excerpt !!}</div>
        @endif

        <div class="doc-content">{!! $document->content !!}</div>

        @if($document->attachments && $document->attachments->count())
            <h3>Załączniki</h3>
            <ul class="doc-attachments">
                @foreach($document->attachments as $att)
                    <li>
                        <a href="{{ asset('storage/' . ltrim($att->path, '/')) }}" target="_blank" rel="noopener">{{ $att->original_name ?? basename($att->path) }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
