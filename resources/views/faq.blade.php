@extends('layouts.app')

@section('title', 'Часто задаваемые вопросы - FAQ')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Часто задаваемые вопросы'])

<!-- faq section starts -->
<section class="faq-section section-b-space">
    <div class="container">
        <div class="faq-title">
            <h2>Часто задаваемые вопросы</h2>
            <p>Ответы на самые популярные вопросы</p>
        </div>
        <div class="row g-4">
            <div class="col-xl-4">
                <div class="side-img">
                    <img class="img-fluid img" src="{{ asset('images/faq.svg') }}" alt="faq">
                </div>
            </div>
            <div class="col-xl-8">
                <div class="accordion accordion-flush help-accordion" id="accordionFlushExample">
                    @php $counter = 1; @endphp
                    
                    {{-- FAQ без категории (показываем сверху) --}}
                    @if(isset($faqsByCategory['no_category']) && $faqsByCategory['no_category']->isNotEmpty())
                        @foreach($faqsByCategory['no_category'] as $faq)
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button {{ $counter == 1 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#flush-collapse{{ $counter }}" aria-expanded="{{ $counter == 1 ? 'true' : 'false' }}">
                                        {{ $faq->question }}
                                    </button>
                                </h2>
                                <div id="flush-collapse{{ $counter }}" class="accordion-collapse collapse {{ $counter == 1 ? 'show' : '' }}"
                                    data-bs-parent="#accordionFlushExample">
                                    <div class="accordion-body">
                                        {!! str($faq->answer)->sanitizeHtml() !!}
                                    </div>
                                </div>
                            </div>
                            @php $counter++; @endphp
                        @endforeach
                    @endif
                    
                    {{-- FAQ с категориями (с подзаголовками) --}}
                    @foreach($categories as $category)
                        @if(isset($faqsByCategory[$category->slug]) && $faqsByCategory[$category->slug]->isNotEmpty())
                            {{-- Подзаголовок категории --}}
                            <div class="mt-4 mb-3">
                                <h4 class="pb-2">{{ $category->name }}</h4>
                            </div>
                            
                            @foreach($faqsByCategory[$category->slug] as $faq)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button {{ $counter == 1 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#flush-collapse{{ $counter }}" aria-expanded="{{ $counter == 1 ? 'true' : 'false' }}">
                                            {{ $faq->question }}
                                        </button>
                                    </h2>
                                    <div id="flush-collapse{{ $counter }}" class="accordion-collapse collapse {{ $counter == 1 ? 'show' : '' }}"
                                        data-bs-parent="#accordionFlushExample">
                                        <div class="accordion-body">
                                            {!! str($faq->answer)->sanitizeHtml() !!}
                                        </div>
                                    </div>
                                </div>
                                @php $counter++; @endphp
                            @endforeach
                        @endif
                    @endforeach

            </div>
        </div>
    </div>
</section>
<!-- faq section end -->
@endsection