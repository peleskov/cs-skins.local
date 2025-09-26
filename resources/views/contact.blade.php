@extends('layouts.app')

@use(App\Models\SiteSetting)

@section('title', 'Контакты - Связаться с нами')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Связаться с нами'])

<!-- contact section starts -->
<section class="section-b-space">
    <div class="container">
        <div class="title animated-title">
            <div class="loader-line"></div>
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
                    <form class="row g-3">
                        <div class="col-md-6">
                            <label for="inputFirstname" class="form-label mt-0">Имя</label>
                            <input type="text" class="form-control" id="inputFirstname"
                                placeholder="Введите ваше имя">
                        </div>
                        <div class="col-md-6">
                            <label for="inputLastname" class="form-label mt-0">Фамилия</label>
                            <input type="text" class="form-control" id="inputLastname"
                                placeholder="Введите вашу фамилию">
                        </div>
                        <div class="col-md-6">
                            <label for="inputEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="inputEmail" placeholder="Введите ваш email">
                        </div>
                        <div class="col-md-6">
                            <label for="inputPhone" class="form-label">Номер телефона</label>
                            <input type="tel" class="form-control" id="inputPhone" placeholder="Введите ваш номер телефона">
                        </div>
                        <div class="col-md-12">
                            <label for="inputtext" class="form-label">Как мы можем вам помочь?</label>
                            <textarea class="form-control" id="inputtext" rows="3"
                                placeholder="Здравствуйте, я хотел бы...."></textarea>
                        </div>
                    </form>
                    <div class="buttons d-flex align-items-center justify-content-end gap-3">
                        <a href="index.html" class="btn theme-btn mt-0">ОТПРАВИТЬ</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
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