@extends('layouts.mplace')

@use(App\Models\SiteSetting)

@section('title', 'Контакты - Связаться с нами')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Связаться с нами'])

<!-- contact section starts -->
<section class="section-b-space" id="Contacts">
    <div class="container">
        <div class="title animated-title">
            <div class="loader-line d-none d-lg-block"></div>
            <div class="d-flex align-items-center justify-content-between flex-wrap w-100">
                <div>
                    <h2>Контактная информация</h2>
                    <h6>
                        Свяжитесь с нами, если у вас есть какие-либо вопросы или просто хотите поздороваться.
                    </h6>
                </div>
            </div>
        </div>
        <div class="contact-detail">
            <div class="row g-4">
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-building-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-title">
                                <h4>{{ __('navigation.footer.company_info.name') }}</h4>
                            </div>
                            <div class="contact-detail-contain">
                                <p>{{ __('navigation.footer.company_info.inn') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-map-pin-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-contain">
                                <p>{{ __('navigation.footer.company_info.address') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-mail-open-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-title">
                                <h4>Email</h4>
                            </div>
                            <div class="contact-detail-contain">
                                <p>{{ __('navigation.footer.company_info.email') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="contact-form">
                    <div class="d-lg-none">
                        <h4 class="contact-form-title d-flex align-items-center mb-3">
                            <i class="m-ico m-ico-send me-3"></i>
                            Send a Message
                        </h4>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form class="row g-3" method="POST" action="{{ route('contact.send') }}">
                        @csrf
                        <div class="col-md-6">
                            <label for="inputFirstname" class="form-label mt-0">Имя <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                id="inputFirstname" name="first_name" value="{{ old('first_name') }}"
                                placeholder="Введите ваше имя" required>
                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="inputLastname" class="form-label mt-0">Фамилия <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                id="inputLastname" name="last_name" value="{{ old('last_name') }}"
                                placeholder="Введите вашу фамилию" required>
                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="inputEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                id="inputEmail" name="email" value="{{ old('email') }}"
                                placeholder="Введите ваш email" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="inputPhone" class="form-label">Номер телефона</label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                id="inputPhone" name="phone" value="{{ old('phone') }}"
                                placeholder="Введите ваш номер телефона">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label for="inputtext" class="form-label">Как мы можем вам помочь? <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('message') is-invalid @enderror"
                                id="inputtext" name="message" rows="3"
                                placeholder="Здравствуйте, я хотел бы...." required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <div class="buttons d-flex align-items-center justify-content-end gap-3">
                                <button type="submit" class="btn theme-btn mt-0">ОТПРАВИТЬ</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col col-xl-4">
                <iframe
                    src="{{ SiteSetting::get('iframe_map') }}"
                    width="600" height="450" class="contact-map border-0 w-100 h-100" allowfullscreen=""
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</section>
<!-- contact section end -->

@endsection